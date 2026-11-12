<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Shared\Date;

/**
 * Excel / CSV imports (port of the CI Import controller).
 *
 * The business rules of every import are the old ones (which columns are required, how a row is matched, what is written).
 * What changed is the mechanism:
 *  - uploads are checked by extension AND real content type, size-limited, stored under storage/app/imports with a random
 *    name (the old code kept the client's file name under the web root, assets/uploads/files, where it could be fetched or overwritten)
 *    and deleted after processing; the original name is only kept (truncated) in invoice_import_history;
 *  - all values are bound parameters (the old code pasted sheet cells into SQL);
 *  - an LCO user can only import rows for its own networks / customers (the old code trusted the LCO code and customer id in the sheet);
 *  - one file = one DB transaction, customer rows are locked while their ledger is written, money is bcmath;
 *  - every sheet's first row is its header (the old code only skipped the very first row of the workbook, so the header rows of
 *    later sheets were imported as data).
 */
class ImportService
{
    public const MAX_BYTES = 20 * 1024 * 1024;

    public const MAX_ROWS = 100000;

    private const TYPES = [
        'csv' => ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel', 'text/x-csv', 'application/octet-stream'],
        'xls' => ['application/vnd.ms-excel', 'application/CDFV2', 'application/x-cfb', 'application/octet-stream'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
    ];

    // ------------------------------------------------------------------ upload + reading

    /** Validates and stores an upload; returns [path, originalName] or an error text. @return array{0:string,1:string}|string */
    public function accept(?UploadedFile $file): array|string
    {
        $name = $file?->getClientOriginalName() ?? '';
        if (! $file || ! $file->isValid() || $file->getSize() === 0) {
            return $name.' invalid file';
        }
        $ext = strtolower($file->getClientOriginalExtension());
        $mime = (string) $file->getMimeType();   // detected from the content (finfo), not the browser's claim
        if ($file->getSize() > self::MAX_BYTES || ! isset(self::TYPES[$ext]) || ! in_array($mime, self::TYPES[$ext], true)) {
            return $name.' invalid file type '.$mime;
        }
        $path = $file->storeAs('imports', bin2hex(random_bytes(12)).'.'.$ext, 'local');

        return [Storage::disk('local')->path($path), $name];
    }

    public function discard(string $path): void
    {
        @unlink($path);
    }

    /**
     * All sheets of a workbook as lists of rows (each row a list of strings, '' for blanks; date cells as Y-m-d).
     *
     * @return list<list<list<string>>>
     */
    public function sheets(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadEmptyCells(false);
        $book = $reader->load($path);
        $out = [];
        $total = 0;
        foreach ($book->getWorksheetIterator() as $sheet) {
            $rows = [];
            foreach ($sheet->getRowIterator() as $row) {
                $cells = $row->getCellIterator();
                $cells->setIterateOnlyExistingCells(true);
                $r = [];
                foreach ($cells as $cell) {
                    $value = $cell->getValue();
                    if ($value === null || $value === '') {
                        continue;
                    }
                    $i = Coordinate::columnIndexFromString($cell->getColumn()) - 1;
                    if (is_numeric($value) && Date::isDateTime($cell)) {
                        $value = Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
                    } elseif ($value instanceof RichText) {
                        $value = $value->getPlainText();
                    } elseif (is_float($value)) {
                        $value = floor($value) === $value && abs($value) < 1e15 ? (string) (int) $value : rtrim(rtrim(sprintf('%.10F', $value), '0'), '.');
                    } else {
                        $value = (string) $value;
                    }
                    $r[$i] = $value;
                }
                if ($r === []) {
                    continue;   // blank row
                }
                $max = max(array_keys($r));
                $full = [];
                for ($k = 0; $k <= $max; $k++) {
                    $full[$k] = $r[$k] ?? '';
                }
                if (++$total > self::MAX_ROWS) {
                    throw new \RuntimeException('Too many rows');
                }
                $rows[] = $full;
            }
            $out[] = $rows;
        }
        $book->disconnectWorksheets();

        return $out;
    }

    private static function filled(array $row, int $i): bool
    {
        return isset($row[$i]) && $row[$i] !== '';
    }

    private static function num(mixed $v): ?string
    {
        $v = trim((string) $v);

        return preg_match('/^-?\d+(\.\d+)?$/', $v) ? bcadd($v, '0', 2) : null;
    }

    private function history(string $name): int
    {
        return (int) DB::table('invoice_import_history')->insertGetId(['file_name' => mb_substr($name, 0, 45), 'created_on' => now()->format('Y-m-d H:i:s')]);
    }

    /** lcoCodes the importing user may touch: every network for admin, own networks for an LCO. null = unrestricted. */
    public function allowedLcos(): ?array
    {
        if ((string) session('usertype') === '1') {
            return null;
        }

        return DB::table('m_network')->where('created_by', session('userid'))->pluck('lcoCode')->all();
    }

    // ------------------------------------------------------------------ invoice import (Import/importsubscribers)

    /** @return int number of invoice rows created */
    public function importInvoices(string $path, string $name): int
    {
        $sheets = $this->sheets($path);
        $allowed = $this->allowedLcos();
        $uid = (int) session('userid');
        $products = [];
        $created = 0;

        DB::transaction(function () use ($sheets, $name, $allowed, $uid, &$products, &$created) {
            $importId = $this->history($name);
            foreach ($sheets as $rows) {
                foreach (array_slice($rows, 1) as $Row) {
                    foreach ([1, 2, 5, 9, 12, 13, 14, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26] as $need) {
                        if (! self::filled($Row, $need)) {
                            continue 2;
                        }
                    }
                    $parts = explode('(', $Row[1]);
                    if (count($parts) < 2) {
                        continue;
                    }
                    $ezyId = substr($parts[1], 0, -1);
                    $cust = DB::table('m_customer')->where('customer_id', $ezyId)->first(['id', 'combo_plan', 'lcoCode']);
                    $lco = $Row[5];
                    if (! $cust || ($allowed !== null && (! in_array($lco, $allowed, true) || $cust->lcoCode !== $lco))) {
                        continue;
                    }
                    $net = DB::table('m_network')->where('lcoCode', $lco)->where('status', 1)->get(['id', 'bill_extra_amount']);
                    if ($net->count() !== 1) {
                        continue;
                    }
                    try {
                        $invoiceDate = CarbonImmutable::parse($Row[26])->format('Y-m-d');
                    } catch (\Throwable) {
                        continue;
                    }
                    $money = [];
                    foreach (['bill_amount' => 17, 'discount_amount' => 18, 'tax_amount' => 19, 'cgst' => 20, 'sgst' => 21, 'cess' => 22, 'mso_share' => 23, 'lco_share' => 24, 'total_amount' => 25] as $field => $i) {
                        $money[$field] = self::num($Row[$i]);
                    }
                    if (in_array(null, $money, true)) {
                        continue;
                    }
                    $package = $Row[12];
                    $serial = $Row[13];

                    // --- how much to bill (same rules as before)
                    $current = '0';
                    $status = 0;
                    $packageList = explode(',', $package);
                    $serialCount = count(explode(',', $serial));
                    $find = function (string $value) use (&$products) {
                        $value = str_starts_with($value, ' ') ? substr($value, 1) : $value;

                        return $products[$value] ??= DB::table('m_products')->where('product_name', $value)->get(['bundle_total', 'total_amount']);
                    };
                    if ($serialCount <= 1) {
                        if (count($packageList) === 1) {
                            $p = $find($packageList[0]);
                            if ($p->count() === 1 && bccomp((string) $p[0]->bundle_total, '0', 2) !== 0 && bccomp($money['total_amount'], (string) $p[0]->total_amount, 2) === 0) {
                                $current = bcadd($current, (string) $p[0]->bundle_total, 2);
                                $status = 1;
                            }
                        } else {
                            $packageTotal = '0';
                            $bill = '0';
                            foreach ($packageList as $value) {
                                $p = $find($value);
                                if ($p->count() === 1) {
                                    $packageTotal = bcadd($packageTotal, (string) $p[0]->total_amount, 2);
                                    $bill = bcadd($bill, bccomp((string) $p[0]->bundle_total, '0', 2) !== 0 ? (string) $p[0]->bundle_total : (string) $p[0]->total_amount, 2);
                                }
                            }
                            if (bccomp($money['total_amount'], $packageTotal, 2) === 0) {
                                $current = $bill;
                                $status = 1;
                            }
                        }
                    }
                    $noOfDays = $status === 1 ? 30 : 0;
                    if ($net[0]->bill_extra_amount !== null && $net[0]->bill_extra_amount !== '') {
                        $current = bcadd($current, self::num($net[0]->bill_extra_amount) ?? '0', 2);
                    }
                    $currentMonth = date('Y-m-d', strtotime($invoiceDate.' -1 month'));

                    DB::table('m_customer')->where('id', $cust->id)->lockForUpdate()->first(['id']);
                    if (DB::table('m_ezybill_invoices')->where('customer_id', (string) $cust->id)->where('invoice_date', $invoiceDate)->exists()) {
                        continue;   // already imported
                    }
                    $comboRate = '0';
                    if ($cust->combo_plan !== null && $cust->combo_plan !== '' && (string) $cust->combo_plan !== '0') {
                        $status = 1;
                        $combo = DB::table('m_combo_plan')->where('id', $cust->combo_plan)->get(['rate']);
                        if ($combo->count() === 1) {
                            $comboRate = bcadd((string) $combo[0]->rate, '0', 2);
                        }
                    }
                    DB::table('m_ezybill_invoices')->insert($money + [
                        'customer_id' => $cust->id, 'package' => $package, 'serial_number' => $serial, 'vc_number' => $Row[14],
                        'invoice_date' => $invoiceDate, 'current_bill_amount' => $current, 'current_month' => $currentMonth,
                        'import_id' => $importId, 'status' => $status, 'no_of_days' => $noOfDays,
                        'created_on' => now()->format('Y-m-d H:i:s'), 'created_by' => $uid,
                    ]);
                    $created++;
                    if ($status === 1) {
                        $due = (string) (DB::table('subscriberbalance')->where('subscriberid', (string) $cust->id)->value('amountdue') ?? '0');
                        $due = bcadd($due, '0', 2);
                        $charge = bccomp($comboRate, '0', 2) !== 0 ? $comboRate : $current;
                        $newDue = bcadd($due, $charge, 2);
                        $this->setBalance($cust->id, $newDue);
                        DB::table('subscriberpayments')->insert([
                            'transactiontype' => 0, 'subscriberid' => (string) $cust->id,
                            'description' => bccomp($comboRate, '0', 2) !== 0 ? 'Combo subscription' : 'Monthly subscription',
                            'dueamount' => $due, 'agentid' => 0, 'paidamount' => 0, 'discount' => 0, 'subscriptioncharge' => $charge,
                            'duebalance' => $newDue, 'dbafterdiscount' => $newDue, 'timestamp' => PaymentService::milliseconds(),
                        ]);
                    }
                }
            }
        });

        return $created;
    }

    private function setBalance(int|string $customerId, string $amount): void
    {
        $q = DB::table('subscriberbalance')->where('subscriberid', (string) $customerId);
        if ($q->exists()) {
            $q->update(['amountdue' => $amount]);   // timestamp (last paid) deliberately not touched, as before
        } else {
            DB::table('subscriberbalance')->insert(['subscriberid' => (string) $customerId, 'amountdue' => $amount]);
        }
    }

    // ------------------------------------------------------------------ customer list into ezybill_customers (formats 1 and 3)

    /**
     * @param  int  $type  import_format.type: 1 = customer list, 3 = customer list (format 2)
     * @return array{total:int,new:int,updated:int,errors:string}|null null = the header does not match a known format
     */
    public function importEzybillCustomers(string $path, string $name, int $type): ?array
    {
        $required = $type === 1
            ? ['CAF', 'Customer ID', 'Customer Name', 'LCO Customer ID', 'Installation Address', 'Pincode', 'STBs', 'Mobile', 'City', 'Installation Date', 'LCO Code', 'Serial No', 'VC No']
            : ['CAF', 'Customer Id', 'Customer Name', 'LCO Customer ID', 'Address', 'Mobile', 'Activation Date', 'LCO Code', 'Serial Number', 'VC Number'];
        $sheets = $this->sheets($path);
        $maps = [];
        foreach ($sheets as $s => $rows) {
            $map = [];
            foreach ($rows[0] ?? [] as $k => $cell) {
                $fmt = DB::table('import_format')->where('type', $type)->where('excel_column_name', $cell)->first(['column_no', 'field_name']);
                if ($fmt) {
                    if ($k !== (int) $fmt->column_no || ! in_array($fmt->field_name, $required, true)) {
                        return null;
                    }
                    $map[$fmt->field_name] = $k;
                }
            }
            if (array_diff($required, array_keys($map)) !== []) {
                return null;
            }
            $maps[$s] = $map;
        }
        $allowed = $this->allowedLcos();
        $uid = (int) session('userid');
        $total = $new = $updated = 0;
        $errors = '';

        DB::transaction(function () use ($sheets, $maps, $type, $name, $allowed, $uid, &$total, &$new, &$updated, &$errors) {
            $importId = $this->history($name);
            foreach ($sheets as $s => $rows) {
                $m = $maps[$s];
                foreach (array_slice($rows, 1) as $Row) {
                    $col = fn (string $f) => $Row[$m[$f]] ?? '';
                    $idField = $type === 1 ? 'Customer ID' : 'Customer Id';
                    $addrField = $type === 1 ? 'Installation Address' : 'Address';
                    $serialField = $type === 1 ? 'Serial No' : 'Serial Number';
                    $vcField = $type === 1 ? 'VC No' : 'VC Number';
                    $dateField = $type === 1 ? 'Installation Date' : 'Activation Date';
                    $customerId = $col($idField);
                    $total++;
                    $ok = false;
                    $needed = ['CAF', $idField, 'Customer Name', $addrField, 'Mobile', 'LCO Code', $serialField, $vcField];
                    if ($type === 1) {
                        array_push($needed, 'Pincode', 'STBs', 'City', 'Installation Date');
                    }
                    $complete = true;
                    foreach ($needed as $f) {
                        if ($col($f) === '') {
                            $complete = false;
                        }
                    }
                    if ($complete && $type === 3) {
                        $parts = explode('(', $customerId);
                        $customerId = count($parts) >= 2 ? substr($parts[1], 0, -1) : '';
                    }
                    $lco = $col('LCO Code');
                    if ($complete && $customerId !== '' && ($allowed === null || in_array($lco, $allowed, true))) {
                        $date = '';
                        if ($col($dateField) !== '') {
                            try {
                                $date = CarbonImmutable::parse($col($dateField))->format('Y-m-d H:i:s');
                            } catch (\Throwable) {
                                $date = null;
                            }
                        }
                        $common = [
                            'lco_customer_id' => $col('LCO Customer ID'), 'customer_name' => $col('Customer Name'), 'mobile' => $col('Mobile'),
                            'billing_address' => $col($addrField), 'serial_number' => str_replace("'", '', $col($serialField)),
                            'vc_number' => str_replace("'", '', $col($vcField)), 'import_id' => $importId,
                            'installation_date' => $date ?? '', 'reseller_business_name' => '',
                        ] + ($type === 1 ? ['pincode' => $col('Pincode'), 'stb_count' => $col('STBs'), 'city' => $col('City')] : []);
                        $exists = DB::table('ezybill_customers')->where('customer_id', $customerId)->exists();
                        if ($exists && $allowed !== null && ! DB::table('ezybill_customers')->where('customer_id', $customerId)->where('lcoCode', $lco)->exists()) {
                            $errors .= $customerId.',';   // staged under another LCO: an LCO may only update its own customers

                            continue;
                        }
                        if (! $exists) {
                            DB::table('ezybill_customers')->insert($common + [
                                'customer_id' => $customerId, 'caf_number' => $col('CAF'), 'lcoCode' => $lco,
                                'created_on' => now()->format('Y-m-d H:i:s'), 'created_by' => $uid,
                            ]);
                            $new++;
                        } else {
                            DB::table('ezybill_customers')->where('customer_id', $customerId)->update($common + ['updated_on' => now()->format('Y-m-d H:i:s'), 'updated_by' => $uid]);
                            $updated++;
                        }
                        $ok = true;
                    }
                    if (! $ok) {
                        $errors .= ($customerId === '' ? 0 : $customerId).',';
                    }
                }
            }
        });

        return ['total' => $total, 'new' => $new, 'updated' => $updated, 'errors' => $errors];
    }

    /** Merges the staged EzyBill customers into m_customer (admin only; the three statements of Import::update_database). */
    public function updateDatabase(): void
    {
        DB::transaction(function () {
            DB::statement("UPDATE ezybill_customers SET customer_name = REPLACE(customer_name, '?', ' ')");
            DB::statement('INSERT IGNORE INTO m_customer (customer_id,caf_number,lcoCode,customer_name,lco_customer_id,mobile,billing_address,serial_number,vc_number,created_on,created_by,status) SELECT customer_id, caf_number, lcoCode, customer_name,lco_customer_id,mobile,billing_address,serial_number,vc_number,created_on,created_by,1 FROM ezybill_customers WHERE lcoCode IN (SELECT lcoCode FROM m_network) ORDER BY id ASC');
            DB::statement('UPDATE m_customer B INNER JOIN ezybill_customers A ON A.customer_id = B.customer_id SET B.caf_number = A.caf_number,B.lcoCode = A.lcoCode,B.customer_name = A.customer_name,B.lco_customer_id = A.lco_customer_id,B.mobile = A.mobile,B.billing_address = A.billing_address,B.serial_number = A.serial_number,B.vc_number = A.vc_number');
        });
    }

    // ------------------------------------------------------------------ opening balances

    /** @return int customers updated */
    public function importOpeningBalances(string $path, string $name): int
    {
        $sheets = $this->sheets($path);
        $lco = (string) session('lcoCode');
        $admin = (string) session('usertype') === '1';
        $uid = (int) session('userid');
        $done = 0;

        DB::transaction(function () use ($sheets, $name, $lco, $admin, $uid, &$done) {
            $importId = $this->history($name);
            foreach ($sheets as $rows) {
                foreach (array_slice($rows, 1) as $Row) {
                    if (! self::filled($Row, 0) || ! self::filled($Row, 4) || ($opening = self::num($Row[4])) === null) {
                        continue;
                    }
                    $q = DB::table('m_customer')->where('customer_id', $Row[0]);
                    if (! $admin) {
                        $q->where('lcoCode', $lco);   // an LCO sets balances of its own customers only
                    }
                    $found = $q->get(['id']);
                    if ($found->count() !== 1) {
                        continue;
                    }
                    $id = $found[0]->id;
                    DB::table('m_customer')->where('id', $id)->lockForUpdate()->first(['id']);
                    DB::table('m_customer')->where('id', $id)->update([
                        'opening_balance' => $opening, 'import_id' => $importId, 'updated_on' => now()->format('Y-m-d H:i:s'), 'updated_by' => $uid,
                    ]);
                    $due = bcadd((string) (DB::table('subscriberbalance')->where('subscriberid', (string) $id)->value('amountdue') ?? '0'), '0', 2);
                    $newDue = bcadd($due, $opening, 2);
                    DB::table('subscriberpayments')->insert([
                        'transactiontype' => 2, 'subscriberid' => (string) $id, 'description' => 'Opening balance', 'dueamount' => $due, 'agentid' => 0,
                        'paidamount' => 0, 'discount' => 0, 'subscriptioncharge' => $opening, 'duebalance' => $newDue, 'dbafterdiscount' => $newDue,
                        'timestamp' => PaymentService::milliseconds(),
                    ]);
                    // (the old code looked the balance row up by the EzyBill customer id and so never updated an existing balance)
                    $this->setBalance($id, $newDue);
                    $done++;
                }
            }
        });

        return $done;
    }

    // ------------------------------------------------------------------ products

    /** @return int products inserted or updated */
    public function importProducts(string $path, string $name): int
    {
        $sheets = $this->sheets($path);
        $uid = (int) session('userid');
        $done = 0;

        DB::transaction(function () use ($sheets, $name, $uid, &$done) {
            $importId = $this->history($name);
            foreach ($sheets as $rows) {
                foreach (array_slice($rows, 2) as $Row) {   // two header rows
                    foreach ([0, 4, 8, 9, 10, 11, 12] as $need) {
                        if (! self::filled($Row, $need)) {
                            continue 2;
                        }
                    }
                    $base = self::num($Row[10]);
                    $tax = self::num($Row[11]);
                    $total = self::num($Row[12]);
                    if ($base === null || $tax === null || $total === null || ! ctype_digit($Row[8])) {
                        continue;
                    }
                    $data = [
                        'product_name' => $Row[0], 'Is_alacarte' => $Row[4] === 'YES' ? 1 : 0, 'channels' => (int) $Row[8],
                        'status' => $Row[9] === 'Active' ? 1 : 0, 'base_rate' => $base, 'tax_rate' => $tax, 'total_amount' => $total,
                        'import_id' => $importId, 'updated_on' => now()->format('Y-m-d H:i:s'), 'updated_by' => $uid,
                    ];
                    $existing = DB::table('m_products')->where('product_name', $Row[0])->get(['id']);
                    if ($existing->count() !== 1) {
                        DB::table('m_products')->insert($data);
                    } else {
                        DB::table('m_products')->where('id', $existing[0]->id)->update($data);
                    }
                    $done++;
                }
            }
        });

        return $done;
    }
}
