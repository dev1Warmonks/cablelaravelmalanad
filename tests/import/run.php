<?php

/**
 * Import module checks on the NEW app (in-process). Everything runs inside one DB transaction that is rolled back
 * (the import tables are InnoDB); the only MyISAM write (m_user.smscount in the SMS test) is restored by hand.
 *   php tests/import/run.php
 */

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

$dir = sys_get_temp_dir().'/imptest_'.bin2hex(random_bytes(4));
mkdir($dir);
$fail = 0;
$check = function (string $name, bool $ok, string $extra = '') use (&$fail) {
    echo ($ok ? 'PASS ' : 'FAIL ').$name.($extra !== '' ? "  [$extra]" : '')."\n";
    $ok || $fail++;
};

// ---- fixtures from the copy
$lco = DB::selectOne("select n.lcoCode, u.username, u.password, u.id uid, u.smscount, u.sms from m_network n join m_user u on u.id = n.created_by
    where u.type = 2 and u.status = 1 and n.status = 1 and n.lcoCode = 'MLNDMPLYR1' limit 1");
$mine = DB::table('m_customer')->where('lcoCode', $lco->lcoCode)->where('status', 1)->orderBy('id')->limit(3)->get();
$foreign = DB::table('m_customer')->where('lcoCode', '<>', $lco->lcoCode)->where('combo_plan', 0)->first();
$product = DB::table('m_products')->where('bundle_total', '>', 0)->first();
$plainCustomer = DB::table('m_customer')->where('lcoCode', $lco->lcoCode)->where(fn ($q) => $q->where('combo_plan', 0)->orWhereNull('combo_plan'))->whereNotExists(
    fn ($q) => $q->select(DB::raw(1))->from('m_ezybill_invoices')->whereColumn('m_ezybill_invoices.customer_id', 'm_customer.id')->where('invoice_date', '2031-01-01'))->first();
echo "LCO {$lco->lcoCode} user {$lco->username}; product {$product->product_name}\n";

// ---- client
$cookies = [];
$call = function (string $method, string $path, array $post = [], array $files = []) use ($kernel, &$cookies): array {
    $req = Request::create('/'.$path, $method, $post, $cookies, $files, ['REMOTE_ADDR' => '127.0.0.1']);
    $res = $kernel->handle($req);
    foreach ($res->headers->getCookies() as $c) {
        $cookies[$c->getName()] = $c->getValue();
    }
    $kernel->terminate($req, $res);

    return [$res->getStatusCode(), $res instanceof StreamedResponse ? (function () use ($res) {
        ob_start();
        $res->sendContent();

        return ob_get_clean();
    })() : (string) $res->getContent(), $res];
};
[, $html] = $call('GET', 'login');
preg_match('/name="_token"[^>]*value="([^"]+)"|value="([^"]+)"[^>]*name="_token"/', $html, $m);
$call('POST', 'login/process_login', ['_token' => $m[1] ?: $m[2], 'username' => $lco->username, 'password' => $lco->password]);
[, $page] = $call('GET', 'report/date_wise_report');
preg_match('/csrf-token"\s+content="([^"]+)"/', $page, $m);
$token = $m[1];

$xlsx = function (string $file, array $rows) use ($dir): string {
    $book = new Spreadsheet;
    $book->getActiveSheet()->fromArray($rows, null, 'A1', true);
    (new Xlsx($book))->save($dir.'/'.$file);

    return $dir.'/'.$file;
};
$csv = function (string $file, array $rows) use ($dir): string {
    $f = fopen($dir.'/'.$file, 'w');
    foreach ($rows as $r) {
        fputcsv($f, $r);
    }
    fclose($f);

    return $dir.'/'.$file;
};
$up = fn (string $path, string $name, string $mime) => new UploadedFile($path, $name, $mime, null, true);
$balance = fn ($id) => (string) DB::table('subscriberbalance')->where('subscriberid', (string) $id)->value('amountdue');
$stored = fn () => glob(storage_path('app/private/imports/*')) + glob(storage_path('app/imports/*'));

DB::beginTransaction();
try {
    // ================= opening balance (csv, own + foreign customer)
    $c = $mine[0];
    $before = $balance($c->id) ?: '0';
    $f = $csv('ob.csv', [['Customer ID', 'Name', 'LCO', 'Address', 'Opening Balance'], [$c->customer_id, 'x', 'x', 'x', '100.50'], [$foreign->customer_id, 'x', 'x', 'x', '999']]);
    $foreignBefore = $balance($foreign->id);
    [$st, $body] = $call('POST', 'import/updatesubscribersopeningbalance', ['_token' => $token, 'import' => 1], ['file' => $up($f, 'ob.csv', 'text/csv')]);
    $check('opening balance: import completed', $st === 200 && $body === 'import completed', "$st $body");
    $after = $balance($c->id);
    $check('opening balance: own customer balance += 100.50 (existing balance row updated)', bccomp($after, bcadd($before, '100.50', 2), 2) === 0, "$before -> $after");
    $row = DB::table('subscriberpayments')->where('subscriberid', (string) $c->id)->where('transactiontype', 2)->orderByDesc('id')->first();
    $check('opening balance: ledger row written', $row && $row->description === 'Opening balance' && bccomp((string) $row->subscriptioncharge, '100.50', 2) === 0 && bccomp((string) $row->dbafterdiscount, $after, 2) === 0);
    $check('opening balance: customer of another LCO untouched', $balance($foreign->id) === $foreignBefore);
    $check('opening balance: uploaded file deleted after import', count($stored()) === 0);

    // ================= download format
    [$st, $body, $res] = $call('GET', 'import/downloadopeningbalancexcel');
    $check('download: CSV with header and customers', $st === 200 && str_starts_with($body, '"Customer ID","Customer Name","LCO Customer ID","Billing Address","Opening Balance"') && substr_count($body, "\n") > 2, $st.' '.json_encode(substr($body, 0, 150)));
    DB::table('m_customer')->where('id', $mine[1]->id)->update(['customer_name' => '=HYPERLINK("http://evil","x")']);
    [, $body] = $call('GET', 'import/downloadopeningbalancexcel');
    $check('download: formula-looking names are neutralised', str_contains($body, "'=HYPERLINK") && ! preg_match('/,=HYPERLINK/', $body));

    // ================= invoice import
    $evzId = 'TEST-EZY-'.random_int(1000, 9999);
    DB::table('m_customer')->where('id', $plainCustomer->id)->update(['customer_id' => $evzId]);
    $extra = DB::table('m_network')->where('lcoCode', $lco->lcoCode)->where('status', 1)->value('bill_extra_amount');
    $row = array_fill(0, 27, '');
    $row[1] = "Someone($evzId)";
    $row[2] = 'CAF1';
    $row[3] = 'L1';
    $row[5] = $lco->lcoCode;
    $row[9] = 'N';
    $row[11] = 'addr';
    $row[12] = $product->product_name;
    $row[13] = 'SERIAL1';
    $row[14] = 'VC1';
    foreach ([17, 18, 19, 20, 21, 22, 23, 24] as $i) {
        $row[$i] = '1.00';
    }
    $row[25] = (string) $product->total_amount;
    $row[26] = '2031-01-01';
    $foreignRow = $row;
    $foreignRow[1] = "Other({$foreign->customer_id})";
    $foreignRow[5] = $foreign->lcoCode;
    $f = $xlsx('inv.xlsx', [array_fill(0, 27, 'h'), $row, $foreignRow]);
    $dueBefore = $balance($plainCustomer->id) ?: '0';
    $fb = $balance($foreign->id);
    [$st, $body] = $call('POST', 'import/importsubscribers', ['_token' => $token, 'import' => 1], ['file' => [$up($f, 'inv.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')]]);
    $check('invoice import: reports completion', $st === 200 && str_contains($body, 'inv.xlsx import completed'), "$st $body");
    $inv = DB::table('m_ezybill_invoices')->where('customer_id', (string) $plainCustomer->id)->where('invoice_date', '2031-01-01')->get();
    $check('invoice import: one invoice created, current_month = invoice date - 1 month', $inv->count() === 1 && $inv[0]->current_month === '2030-12-01', $inv->count().' '.($inv[0]->current_month ?? ''));
    $expected = bcadd((string) $product->bundle_total, $extra !== null && $extra !== '' ? (string) $extra : '0', 2);
    $check('invoice import: status 1 and billed = bundle total (+ network extra)', $inv->count() === 1 && (int) $inv[0]->status === 1 && bccomp((string) $inv[0]->current_bill_amount, $expected, 2) === 0, ($inv[0]->current_bill_amount ?? '').' vs '.$expected);
    $check('invoice import: balance increased and ledger row written', bccomp($balance($plainCustomer->id), bcadd($dueBefore, $expected, 2), 2) === 0
        && DB::table('subscriberpayments')->where('subscriberid', (string) $plainCustomer->id)->where('transactiontype', 0)->where('description', 'Monthly subscription')->exists());
    $check('invoice import: row for another LCO ignored', $balance($foreign->id) === $fb && ! DB::table('m_ezybill_invoices')->where('customer_id', (string) $foreign->id)->where('invoice_date', '2031-01-01')->exists());
    $call('POST', 'import/importsubscribers', ['_token' => $token, 'import' => 1], ['file' => [$up($f, 'inv.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')]]);
    $check('invoice import: importing the same file again creates no duplicate', DB::table('m_ezybill_invoices')->where('customer_id', (string) $plainCustomer->id)->where('invoice_date', '2031-01-01')->count() === 1);

    // ================= products
    $pn = 'ZZ TEST PRODUCT '.random_int(100, 999);
    $mk = fn (string $name, string $active) => [$name, '', '', '', 'YES', '', '', '', '12', $active, '10.00', '1.80', '11.80'];
    $f = $xlsx('prod.xlsx', [['h'], ['h2'], $mk($pn, 'Active'), $mk($product->product_name, 'Active')]);
    [$st, $body] = $call('POST', 'import/import_products', ['_token' => $token, 'import' => 1], ['file' => $up($f, 'prod.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')]);
    $menus = DB::table('m_user_roles')->where('user_id', $lco->uid)->pluck('menu_id')->all();
    if (in_array(1, $menus)) {
        $check('products: new product inserted', $st === 200 && DB::table('m_products')->where('product_name', $pn)->where('Is_alacarte', 1)->where('status', 1)->exists(), "$st $body");
    } else {
        $check('products: user without menu 1 is refused (403)', $st === 403, (string) $st);
    }

    // ================= EzyBill customer list (format 1)
    $fmt = DB::table('import_format')->where('type', 1)->orderBy('column_no')->get();
    $width = $fmt->max('column_no') + 1;
    $head = [];
    for ($i = 0; $i < $width; $i++) {
        $head[$i] = 'unmapped'.$i;   // columns the format does not know are plain labels
    }
    foreach ($fmt as $x) {
        $head[$x->column_no] = $x->excel_column_name;   // (Pincode's column is mapped to an EMPTY header cell in import_format)
    }
    $mkrow = function (string $cid, string $lcoCode) use ($fmt, $width) {
        $r = array_fill(0, $width, 'z');
        foreach ($fmt as $x) {
            $r[$x->column_no] = match ($x->field_name) {
                'Customer ID' => $cid, 'LCO Code' => $lcoCode, 'Installation Date' => '2026-01-05', 'STBs' => '1', 'Pincode' => '670001', 'Mobile' => '9000000000',
                default => 'v-'.$x->field_name,
            };
        }

        return $r;
    };
    $newId = 'TESTCUST'.random_int(10000, 99999);
    $f = $csv('ez.csv', [$head, $mkrow($newId, $lco->lcoCode), $mkrow($newId.'F', 'SOMEONE_ELSE')]);
    [$st, $body] = $call('POST', 'import/import_suscriberfromezybill', ['_token' => $token, 'import' => 1], ['file' => $up($f, 'ez.csv', 'text/csv')]);
    $check('ezybill list: summary shown, 1 new customer, 1 error (foreign LCO)', $st === 200 && str_contains($body, '<td>New Customer</td><td>1</td>') && str_contains($body, '<td>Error</td><td>1('.$newId.'F,)'), "$st ".substr(strip_tags($body), 0, 200));
    $check('ezybill list: staged row written for own LCO only', DB::table('ezybill_customers')->where('customer_id', $newId)->exists() && ! DB::table('ezybill_customers')->where('customer_id', $newId.'F')->exists());
    $bad = $csv('ez_bad.csv', [['nonsense', 'columns'], ['a', 'b']]);
    [, $body] = $call('POST', 'import/import_suscriberfromezybill', ['_token' => $token, 'import' => 1], ['file' => $up($bad, 'ez_bad.csv', 'text/csv')]);
    $check('ezybill list: unknown header -> invalid excel format, nothing imported', trim($body) === 'invalid excel format', substr($body, 0, 80));

    // ================= upload hardening
    $php = $dir.'/shell.csv';
    file_put_contents($php, "<?php system(\$_GET['c']); ?>\n<?php echo 1;");
    [, $body] = $call('POST', 'import/updatesubscribersopeningbalance', ['_token' => $token, 'import' => 1], ['file' => $up($php, 'shell.csv', 'text/csv')]);
    $check('upload: PHP code disguised as .csv is refused by content type', str_contains($body, 'invalid file type'), substr($body, 0, 80));
    $exe = $dir.'/a.exe';
    file_put_contents($exe, 'MZ binary');
    [, $body] = $call('POST', 'import/updatesubscribersopeningbalance', ['_token' => $token, 'import' => 1], ['file' => $up($exe, 'a.exe', 'application/octet-stream')]);
    $check('upload: .exe refused by extension', str_contains($body, 'invalid file type'));
    [, $body] = $call('POST', 'import/updatesubscribersopeningbalance', ['_token' => $token, 'import' => 1], []);
    $check('upload: no file -> message, no error', str_contains($body, 'invalid file'));
    $check('upload: nothing left in the import folder', count($stored()) === 0);
    [$st] = $call('POST', 'import/updatesubscribersopeningbalance', ['import' => 1], ['file' => $up($f, 'ob.csv', 'text/csv')]);
    $check('upload: POST without CSRF token rejected (419)', $st === 419, (string) $st);

    // ================= update_database is admin only
    [, $body] = $call('POST', 'import/update_database', ['_token' => $token]);
    $check('update_database: an LCO user gets nothing', $body === '');

    // ================= bill SMS queue
    DB::table('m_user')->where('id', $lco->uid)->update(['sms' => 1, 'smscount' => 1]);
    $month = '2030-12-01';
    DB::table('m_customer')->where('id', $plainCustomer->id)->update(['sms' => 1]);
    $inv2 = DB::table('m_ezybill_invoices')->where('customer_id', (string) $plainCustomer->id)->where('invoice_date', '2031-01-01')->first();
    DB::table('m_ezybill_invoices')->where('id', $inv2->id)->update(['sms_status' => 0]);
    [$st, $body] = $call('GET', 'import/send_sms_to_pending_customers/'.$month.'/'.$lco->uid);
    $check('send sms page: shows balance and pending count', $st === 200 && str_contains($body, 'Pending SMS count'), (string) $st);
    [$st] = $call('POST', 'import/sendsmstopendingcustomers/'.$month, ['_token' => $token]);
    $queued = DB::table('m_ezybill_invoices')->where('id', $inv2->id)->first();
    $check('send sms: queued with the old text, balance reduced by one', $st === 200 && (int) $queued->sms_status === 1 && str_starts_with((string) $queued->sms, 'CTOPUP Dear ')
        && (float) DB::table('m_user')->where('id', $lco->uid)->value('smscount') === 0.0, (string) $queued->sms);
    [$st, $body] = $call('GET', 'import/send_sms_to_pending_customers/not-a-month');
    $check('send sms page: bad month gives nothing', $body === '');
} finally {
    DB::rollBack();
    DB::table('m_user')->where('id', $lco->uid)->update(['sms' => $lco->sms, 'smscount' => $lco->smscount]);   // MyISAM: not rolled back
    array_map('unlink', glob($dir.'/*'));
    rmdir($dir);
}
echo $fail ? "\n$fail FAILED\n" : "\nall passed\n";
exit($fail ? 1 : 0);
