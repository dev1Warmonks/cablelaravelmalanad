<?php

namespace App\Http\Controllers;

use App\Services\ImportService;
use App\Services\MenuService;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Imports (usertype 1 and 2, enforced by the legacy.session middleware; some also need a menu id, as before).
 * Output texts are the old ones. Uploads are validated and stored by ImportService.
 */
class ImportController extends Controller
{
    public function __construct(private MenuService $menus, private ImportService $imports) {}

    private function page(string $view, array $data = [])
    {
        return view('admin.import.'.$view, $data + ['menulist' => $this->menus->menuList(), 'network_list' => $this->menus->networkList()]);
    }

    private function needMenu(int $id): void
    {
        abort_unless(in_array($id, $this->menus->menuList()), 403);
    }

    /** Runs $work($path, $name) for one uploaded file, always deleting the stored copy. Returns an error text instead on a bad upload or a failure. */
    private function withUpload(Request $request, string $field, callable $work): string
    {
        $file = $request->file($field);
        $file = is_array($file) ? ($file[0] ?? null) : $file;

        return $this->withFile($file, $work);
    }

    private function withFile($file, callable $work): string
    {
        $stored = $this->imports->accept($file);
        if (is_string($stored)) {
            return e($stored);
        }
        [$path, $name] = $stored;
        try {
            return (string) $work($path, $name);
        } catch (\Throwable $e) {
            Log::error('import failed: '.$e->getMessage(), ['file' => $name, 'by' => session('userid')]);

            return e($name).' could not be imported (unreadable or invalid file)';
        } finally {
            $this->imports->discard($path);
        }
    }

    // ------------------------------------------------------------------ pages

    public function subscribers()
    {
        return $this->page('subscribers');
    }

    public function importsuscriberfromezybill()
    {
        return $this->page('ezybill');
    }

    public function importsuscriberfromezybillF2()
    {
        return $this->page('ezybill_f2');
    }

    public function importsubscriberopeningbalance()
    {
        $this->needMenu(12);

        return $this->page('openingbalance');
    }

    public function importproducts()
    {
        $this->needMenu(1);

        return $this->page('products');
    }

    // ------------------------------------------------------------------ imports

    /** Invoice files (several at once). */
    public function importsubscribers(Request $request)
    {
        if (! $request->has('import')) {
            return response('');
        }
        $out = '';
        foreach ((array) $request->file('file', []) as $file) {
            if ($file === null || $file->getSize() === 0) {
                continue;   // an empty slot, as before
            }
            $name = $file->getClientOriginalName();
            $msg = $this->withFile($file, function ($path, $n) {
                $this->imports->importInvoices($path, $n);

                return null;
            });
            $out .= $msg === '' ? e($name).' import completed\n' : $msg;
        }

        return response($out);
    }

    public function postEzybill(Request $request)
    {
        return $this->ezybill($request, 1);
    }

    public function postEzybillF2(Request $request)
    {
        return $this->ezybill($request, 3);
    }

    private function ezybill(Request $request, int $type)
    {
        if (! $request->has('import')) {
            return response('');
        }
        $html = $this->withUpload($request, 'file', function ($path, $name) use ($type) {
            $r = $this->imports->importEzybillCustomers($path, $name, $type);
            if ($r === null) {
                return 'invalid excel format';
            }
            $error = $r['total'] - $r['new'] - $r['updated'];

            return '<style>table {
					font-family: arial, sans-serif;
					border-collapse: collapse;
				  }

				  td, th {
					border: 1px solid #dddddd;
					text-align: left;
					padding: 8px;
				  }

				  </style>
				  <table>
				  	<tr><td>Status</td><td>import completed</td></tr>
					<tr><td>Total</td><td>'.$r['total'].'</td></tr>
					<tr><td>New Customer</td><td>'.$r['new'].'</td></tr>
					<tr><td>Customer updated</td><td>'.$r['updated'].'</td></tr>
					<tr><td>Error</td><td>'.$error.'('.e($r['errors']).')</td></tr>
				</table>';
        });

        return response($html);
    }

    /** Merges staged EzyBill customers into m_customer (admin only). */
    public function updateDatabase()
    {
        if ((string) session('usertype') !== '1') {
            return response('');
        }
        $this->imports->updateDatabase();
        Log::channel('audit')->info('import: ezybill customers merged into m_customer', ['by' => session('userid')]);

        return response('Success');
    }

    public function updatesubscribersopeningbalance(Request $request)
    {
        $this->needMenu(12);
        if (! $request->has('import')) {
            return response('');
        }
        $msg = $this->withUpload($request, 'file', function ($path, $name) {
            $n = $this->imports->importOpeningBalances($path, $name);
            Log::channel('audit')->info('import: opening balances', ['by' => session('userid'), 'file' => $name, 'customers' => $n]);

            return '';
        });

        return response($msg === '' ? 'import completed' : $msg);
    }

    public function postProducts(Request $request)
    {
        $this->needMenu(1);
        if (! $request->has('import')) {
            return response('');
        }
        $msg = $this->withUpload($request, 'file', function ($path, $name) {
            $this->imports->importProducts($path, $name);

            return '';
        });

        return response($msg === '' ? 'import completed' : $msg);
    }

    /** CSV of the LCO's customers with their opening balances (the format of the opening balance import). */
    public function downloadopeningbalancexcel()
    {
        $lco = (string) session('lcoCode');
        $rows = DB::table('m_customer')->where('lcoCode', $lco)->get(['customer_id', 'customer_name', 'lco_customer_id', 'billing_address', 'opening_balance']);
        // text cells that start with = + - @ would run as formulas when opened in Excel: prefix them (columns 2-4 are never read back)
        $safe = fn ($v) => preg_match('/^[=+\-@\t\r]/', (string) $v) ? "'".$v : $v;
        $name = 'Customer list ('.preg_replace('/[^A-Za-z0-9_-]/', '', $lco).').csv';

        return response()->streamDownload(function () use ($rows, $safe) {
            $f = fopen('php://output', 'w');
            fputcsv($f, ['Customer ID', 'Customer Name', 'LCO Customer ID', 'Billing Address', 'Opening Balance']);
            foreach ($rows as $r) {
                fputcsv($f, [$r->customer_id, $safe($r->customer_name), $safe($r->lco_customer_id), $safe($r->billing_address), $r->opening_balance]);
            }
            fclose($f);
        }, $name, ['Content-Type' => 'text/csv', 'Cache-Control' => 'no-store, no-cache', 'Pragma' => 'no-cache', 'Expires' => '0']);
    }

    // ------------------------------------------------------------------ SMS for pending monthly bills

    /** Pending (not yet queued) bill SMS of one month for the LCO's SMS-enabled customers. */
    private function pendingInvoices(string $month)
    {
        $ids = DB::table('m_customer')->where('lcoCode', session('lcoCode'))->where('sms', 1)->pluck('id')->map(fn ($i) => (string) $i)->all();
        if ($ids === []) {
            return collect();
        }

        return DB::table('m_ezybill_invoices')->where('current_month', $month)->whereIn('customer_id', $ids)->where('sms_status', 0)->where('status', 1)
            ->get(['id', 'customer_id', 'current_bill_amount', 'current_month']);
    }

    private static function validMonth(string $month): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $month);
    }

    public function sendSmsToPendingCustomers(Request $request, string $month, ?string $userid = null)
    {
        $user = DB::table('m_user')->where('id', session('userid'))->first(['sms', 'smscount']);
        if (! self::validMonth($month) || ! $user || (int) $user->sms !== 1 || (float) $user->smscount <= 0) {
            return response('');   // as before: nothing is shown unless the LCO has SMS enabled and a balance
        }
        $referer = (string) $request->headers->get('referer', url('transaction/import_history/'));

        return $this->page('send_sms', [
            'current_month' => $month, 'referer' => $referer, 'pending_sms_count' => $this->pendingInvoices($month)->count(), 'sms_balance' => $user->smscount,
        ]);
    }

    /** Queues the bill SMS (delivered right away when SMS sending is enabled, otherwise by the SMS cron). Stops when the SMS balance is used up. */
    public function postSendSms(string $month)
    {
        if (! self::validMonth($month)) {
            return response('');
        }
        $uid = session('userid');
        $lco = (string) session('lcoCode');
        $network = DB::table('m_network')->where('lcoCode', $lco)->first(['sms_signature', 'bill_type']);
        $queued = 0;
        foreach ($this->pendingInvoices($month) as $inv) {
            DB::transaction(function () use ($inv, $uid, $network, &$queued) {
                $balance = (float) DB::table('m_user')->where('id', $uid)->lockForUpdate()->value('smscount');
                if ($balance <= 0) {
                    return;   // the old code queued the SMS even without balance (and then did not charge it)
                }
                $c = DB::table('m_customer')->leftJoin('subscriberbalance', 'subscriberbalance.subscriberid', '=', 'm_customer.id')
                    ->where('m_customer.id', $inv->customer_id)->first(['m_customer.customer_name', 'subscriberbalance.amountdue as due']);
                if (! $c) {
                    return;
                }
                $monthText = $network && (int) $network->bill_type === 0 ? 'current month' : date('M Y', strtotime($inv->current_month));
                $sms = 'CTOPUP Dear '.$c->customer_name.', Cablebill 4 '.$monthText.' generated. Due amt is Rs '.$c->due.'. Pls make payment on time. Thank you '.($network->sms_signature ?? '').'.';
                DB::table('m_ezybill_invoices')->where('id', $inv->id)->update(['sms' => $sms, 'sms_status' => 1]);
                DB::table('m_user')->where('id', $uid)->update(['smscount' => $balance - 1]);
                $queued++;
            });
        }
        $sent = app(SmsService::class)->processInvoiceQueue($uid);   // no-op unless SMS_SEND_ENABLED=true
        Log::channel('audit')->info('import: bill SMS queued', ['by' => $uid, 'month' => $month, 'queued' => $queued, 'sent' => $sent['sent']]);

        return response('');
    }
}
