<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * GSoft monthly subscription bills (what an LCO pays the platform): gsoft_billing rows, UPIGateway orders
 * (upigateway_history) and the "Monthly bills" screen data. Port of Transaction::monthly_bills_data,
 * payment_type_view, upigateway_check_order_status and initiate_payment.
 *
 * gsoft_billing.bill_status: 0 Pending, 1 Initiated, 2 Paid, 3 Failed, 4 Free.
 *
 * Fixes vs the old code:
 *  - the order amount is the bill's own invoice_total (the old code sent whatever amount the browser posted, so a bill could
 *    be paid with a 1-rupee order and still be marked paid);
 *  - the bill must belong to the caller's LCO;
 *  - crediting is idempotent: the bill row is locked, an already-paid bill is never touched again, and the gateway's amount
 *    (when returned) must equal the invoice total;
 *  - "payment completed" cannot move a paid/free bill back to Initiated.
 * upigateway_history is MyISAM (no transactions), so correctness relies on the InnoDB gsoft_billing row lock + status check.
 */
class GsoftBillingService
{
    public const STATUS = [0 => 'Pending', 1 => 'Initiated', 2 => 'Successfully paid', 3 => 'Failed', 4 => 'Free'];

    public function __construct(private UpiGatewayService $gateway) {}

    // ------------------------------------------------------------------ monthly bills screen

    /** @return array{rows: array<int,array>, totals: array} */
    public function screen(?string $from, ?string $to, string $lcoCode, int|string $userId): array
    {
        $this->pollPending();

        $perCustomer = (float) DB::table('m_user')->where('id', $userId)->value('per_customer_price');
        $months = DB::table('invoice_history')->orderByDesc('current_month');
        if ($from !== null && $to !== null && $to !== '') {
            $months->where('current_month', '>=', $from)->where('current_month', '<=', $to);
        }
        // literal id list (strings: the invoice/payment columns are varchar). "IN (subquery)" took 15 s here, the list 0.25 s.
        $customerIds = DB::table('m_customer')->where('lcoCode', $lcoCode)->pluck('id')->map(fn ($v) => (string) $v)->all();

        $rows = [];
        $totals = ['invoice' => 0.0, 'generated' => 0, 'free_sms' => 0, 'extra_sms' => 0];
        $monthRows = $months->get();
        foreach ($monthRows as $m) {
            // Only months that have a bill for this LCO are shown, so look the bill up first (the old code computed every
            // month's counts, ~78 months x several full-table scans, before discarding most of them).
            $bills = DB::table('gsoft_billing')->where('lco_code', $lcoCode)->where('current_month', $m->current_month)->get(['bill_status', 'id']);
            if ($bills->count() !== 1) {
                continue;
            }
            $invoices = fn () => DB::table('m_ezybill_invoices')->where('current_month', $m->current_month)->whereIn('customer_id', $customerIds);
            $totalInvoices = (int) $invoices()->count();
            $freeSms = (int) $invoices()->where('sms_status', 2)->count();

            $d = date_create($m->current_month);
            $from_ts = (string) (strtotime($d->format('Y-m-d')) * 1000);
            $to_ts = (string) (strtotime($d->format('Y-m').'-31') * 1000 + 24 * 60 * 60 * 1000);
            // one scan instead of two: payments SMS vs charge/misc SMS in the month
            $sms = DB::table('subscriberpayments')->whereIn('subscriberid', $customerIds)->where('timestamp', '>', $from_ts)->where('timestamp', '<', $to_ts)->where('sms_status', 2)
                ->selectRaw('COALESCE(SUM(transactiontype = 1 AND is_misc = 0), 0) as pay_sms, COALESCE(SUM(is_misc = 1 OR transactiontype = 3), 0) as paid_sms')->first();
            $freeSms += (int) $sms->pay_sms;
            $paidSms = (int) $sms->paid_sms;
            $invoiceTotal = $perCustomer * $totalInvoices;

            $status = (int) $bills[0]->bill_status;
            $totals['invoice'] += $invoiceTotal;
            $totals['generated'] += $totalInvoices;
            $totals['free_sms'] += $freeSms;
            $totals['extra_sms'] += $paidSms;
            $rows[] = [
                'yearmonth' => $m->yearmonth, 'current_month' => $m->current_month, 'invoices' => $totalInvoices, 'free_sms' => $freeSms,
                'paid_sms' => $paidSms, 'invoice_total' => $invoiceTotal, 'status' => self::STATUS[$status] ?? '', 'bill_id' => $bills[0]->id,
                'payable' => in_array($status, [0, 1, 3], true),
            ];
        }

        return ['empty' => $monthRows->isEmpty(), 'rows' => $rows, 'totals' => $totals];
    }

    /** One bill of the caller's LCO for the pay dialog (monthly_bills_view). */
    public function billFor(string $lcoCode, string $currentMonth): ?object
    {
        return DB::table('gsoft_billing')->where('lco_code', $lcoCode)->where('current_month', $currentMonth)->first();
    }

    // ------------------------------------------------------------------ paying

    /** Marks the LCO's bill for a month as "Initiated" with a manual payment type (the "Payment completed" button). */
    public function initiate(string $lcoCode, string $currentMonth, int|string $paymentType): int
    {
        return DB::table('gsoft_billing')->where('lco_code', $lcoCode)->where('current_month', $currentMonth)
            ->whereIn('bill_status', [0, 1, 3])->update(['bill_status' => 1, 'payment_type' => $paymentType]);
    }

    /**
     * Creates a UPIGateway order for the bill. The amount is the bill's invoice_total, never client input.
     *
     * @return array{status:bool, msg:string, payment_url?:string, client_txn_id?:string, date?:string}
     */
    public function startUpiPayment(int|string $billId, string $lcoCode, int|string $userId, int|string $networkId): array
    {
        $fail = ['status' => false, 'msg' => 'Payment link generation failed'];
        $bill = DB::table('gsoft_billing')->where('id', $billId)->where('lco_code', $lcoCode)->first();
        if (! $bill || ! in_array((int) $bill->bill_status, [0, 1, 3], true) || (float) $bill->invoice_total <= 0) {
            return $fail;
        }
        $network = DB::table('m_network')->where('id', $networkId)->first(['network_name', 'phone']);
        if (! $network) {
            return $fail;
        }
        $clientTxnId = date('Ymdhis').'-'.$bill->id;
        $json = $this->gateway->createOrder($clientTxnId, number_format((float) $bill->invoice_total, 2, '.', ''), (string) $network->network_name,
            $network->phone !== '' && $network->phone !== null ? (string) $network->phone : (string) config('cablebill.upigateway.default_phone'));
        if (($json['status'] ?? false) !== true || empty($json['data']['payment_url'])) {
            return $fail;
        }

        $date = date('Y-m-d');
        DB::table('upigateway_history')->insert([
            'bill_id' => $bill->id, 'client_txn_id' => $clientTxnId, 'url_response' => json_encode($json), 'status' => '1',
            'created_on' => date('Y-m-d H:i:s'), 'date' => $date, 'created_by' => $userId,
        ]);
        DB::table('gsoft_billing')->where('id', $bill->id)->whereIn('bill_status', [0, 1, 3])->update(['bill_status' => 1]);

        return ['status' => true, 'msg' => (string) ($json['msg'] ?? ''), 'payment_url' => $json['data']['payment_url'], 'client_txn_id' => $clientTxnId, 'date' => $date];
    }

    // ------------------------------------------------------------------ gateway status

    /** Re-checks the open orders of the last 5 days (the old screen did this on every load). Skips bills that are already paid. */
    public function pollPending(): void
    {
        if (! $this->gateway->configured()) {
            return;
        }
        $open = DB::table('upigateway_history as h')->join('gsoft_billing as b', 'b.id', '=', 'h.bill_id')
            ->where('h.status', 1)->where('h.date', '>=', date('Y-m-d', strtotime('-5 days')))->whereNotIn('b.bill_status', [2, 4])
            ->get(['h.client_txn_id', 'h.date', 'h.bill_id']);
        foreach ($open as $h) {
            $this->checkOrder($h->client_txn_id, $h->date, $h->bill_id);
        }
    }

    /** Applies the gateway's answer for one order. Idempotent: a bill is credited at most once. Returns true when it was credited now. */
    public function checkOrder(string $clientTxnId, string $date, int|string $billId, int|string|null $userId = null): bool
    {
        $json = $this->gateway->checkOrderStatus($clientTxnId, date('d-m-Y', strtotime($date)));
        $now = date('Y-m-d H:i:s');
        $by = $userId ?? session('userid');

        if (($json['status'] ?? false) !== true) {
            DB::table('upigateway_history')->where('client_txn_id', $clientTxnId)->update(['failed_response' => json_encode($json), 'modified_on' => $now, 'modified_by' => $by]);

            return false;
        }
        DB::table('upigateway_history')->where('client_txn_id', $clientTxnId)->update(['success_response' => json_encode($json), 'modified_on' => $now, 'modified_by' => $by]);
        if (($json['data']['status'] ?? '') !== 'success') {
            return false;
        }

        return DB::transaction(function () use ($clientTxnId, $billId, $json) {
            $bill = DB::table('gsoft_billing')->where('id', $billId)->lockForUpdate()->first();
            $history = DB::table('upigateway_history')->where('client_txn_id', $clientTxnId)->where('bill_id', $billId)->first(['id']);
            if (! $bill || ! $history) {
                Log::warning('upigateway: order does not belong to that bill', ['txn' => $clientTxnId, 'bill' => $billId]);

                return false;
            }
            if (in_array((int) $bill->bill_status, [2, 4], true)) {
                return false;   // already paid/free: never credit twice
            }
            if (isset($json['data']['amount']) && abs((float) $json['data']['amount'] - (float) $bill->invoice_total) > 0.009) {
                Log::warning('upigateway: amount mismatch, bill NOT marked paid', ['txn' => $clientTxnId, 'bill' => $billId, 'paid' => $json['data']['amount'], 'due' => $bill->invoice_total]);

                return false;
            }
            DB::table('gsoft_billing')->where('id', $billId)->update(['payment_type' => 4, 'bill_status' => 2, 'tran_id' => $clientTxnId]);
            Log::channel('audit')->info('gsoft bill paid via upigateway', ['bill' => $billId, 'txn' => $clientTxnId, 'amount' => $bill->invoice_total]);

            return true;
        });
    }

    // ------------------------------------------------------------------ administrator screens

    /**
     * Creates the gsoft_billing row (status Pending) for every network of every active user and every invoice month that has
     * invoices but no bill yet (AdminSettings::generate_monthly_bills). Returns the number of bills created.
     * Fix: the old code stored number_format() text ("1,234.00" -> truncated to 1 by MySQL); a plain rounded number is stored.
     */
    public function generateAll(): int
    {
        @set_time_limit(0);
        $months = DB::table('invoice_history')->orderByDesc('current_month')->pluck('current_month')->all();
        // One grouped query for "invoices per network and month" (the old loop ran 2 queries per network x month, ~15 s each).
        $stats = [];
        foreach (DB::select('SELECT c.lcoCode, e.current_month, COUNT(*) AS total, SUM(e.sms_status = 2) AS free FROM m_ezybill_invoices e JOIN m_customer c ON e.customer_id = c.id GROUP BY c.lcoCode, e.current_month') as $r) {
            $stats[$r->lcoCode][$r->current_month] = $r;
        }
        $existing = [];
        foreach (DB::table('gsoft_billing')->get(['lco_code', 'current_month']) as $b) {
            $existing[$b->lco_code][$b->current_month] = true;
        }

        $created = 0;
        foreach (DB::table('m_user')->where('status', 1)->get(['id', 'per_customer_price']) as $user) {
            foreach (DB::table('m_network')->where('created_by', $user->id)->pluck('lcoCode') as $lco) {
                foreach ($months as $month) {
                    $row = $stats[$lco][$month] ?? null;
                    if (isset($existing[$lco][$month]) || $row === null || (int) $row->total === 0) {
                        continue;
                    }
                    $ids = DB::table('m_customer')->where('lcoCode', $lco)->pluck('id')->map(fn ($v) => (string) $v)->all();
                    $d = date_create($month);
                    $from = (string) (strtotime($d->format('Y-m-d')) * 1000);
                    $to = (string) (strtotime($d->format('Y-m').'-31') * 1000 + 24 * 60 * 60 * 1000);
                    $sms = DB::table('subscriberpayments')->whereIn('subscriberid', $ids)->where('timestamp', '>', $from)->where('timestamp', '<', $to)->where('sms_status', 2)
                        ->selectRaw('COALESCE(SUM(transactiontype = 1 AND is_misc = 0), 0) as pay_sms, COALESCE(SUM(is_misc = 1 OR transactiontype = 3), 0) as paid_sms')->first();
                    DB::table('gsoft_billing')->insert([
                        'lco_code' => $lco, 'current_month' => $month, 'invoice_generated' => (int) $row->total,
                        'free_sms_used' => (int) $row->free + (int) $sms->pay_sms, 'extra_sms_used' => (int) $sms->paid_sms,
                        'invoice_total' => (string) round((float) $user->per_customer_price * (int) $row->total), 'created_on' => date('Y-m-d H:i:s'), 'bill_status' => 0,
                    ]);
                    $existing[$lco][$month] = true;
                    $created++;
                }
            }
        }
        Log::channel('audit')->info('gsoft bills generated', ['created' => $created, 'by' => session('userid')]);

        return $created;
    }

    /** Admin bill list (all networks, filters: status, user, network; -1 = all). Same SQL as the old screen, bound parameters. */
    public function adminScreen(int $status, int $userId, string $network): array
    {
        $where = '';
        $bind = [];
        if ($network !== '-1') {
            $where .= ' AND n.lcoCode = ?';
            $bind[] = $network;
        }
        if ($userId !== -1) {
            $where .= ' AND n.created_by = ?';
            $bind[] = $userId;
        }
        if ($status !== -1) {
            $where .= ' AND gb.bill_status = ?';
            $bind[] = $status;
        }
        $sql = "
        SELECT n.lcoCode, n.network_name, ih.yearmonth, ih.current_month,
               COALESCE(inv.total_invoice_count, 0) AS total_invoice_count,
               COALESCE(inv.free_sms_count, 0) AS free_sms_count_from_invoices,
               COALESCE(subs.added_free_sms, 0) AS added_free_sms_from_payments,
               COALESCE(subs.paid_sms_count, 0) AS paid_sms_count,
               COALESCE(u.per_customer_price, 0) AS per_customer_price,
               gb.bill_status, gb.payment_type, COALESCE(pt.name, '') AS payment_type_name
          FROM m_network n
         CROSS JOIN invoice_history ih
          LEFT JOIN (SELECT c.lcoCode, e.current_month, COUNT(*) AS total_invoice_count,
                            SUM(CASE WHEN e.sms_status = 2 THEN 1 ELSE 0 END) AS free_sms_count
                       FROM m_ezybill_invoices e JOIN m_customer c ON e.customer_id = c.id
                      GROUP BY c.lcoCode, e.current_month) inv ON inv.lcoCode = n.lcoCode AND inv.current_month = ih.current_month
          LEFT JOIN (SELECT c.lcoCode, DATE_FORMAT(FROM_UNIXTIME(sp.timestamp/1000), '%Y-%m') AS ym,
                            SUM(CASE WHEN sp.transactiontype = 1 AND sp.is_misc = 0 AND sp.sms_status = 2 THEN 1 ELSE 0 END) AS added_free_sms,
                            SUM(CASE WHEN (sp.is_misc = 1 OR sp.transactiontype = 3) AND sp.sms_status = 2 THEN 1 ELSE 0 END) AS paid_sms_count
                       FROM subscriberpayments sp JOIN m_customer c ON sp.subscriberid = c.id
                      GROUP BY c.lcoCode, ym) subs ON subs.lcoCode = n.lcoCode AND subs.ym = DATE_FORMAT(STR_TO_DATE(ih.current_month, '%Y-%m-%d'), '%Y-%m')
          LEFT JOIN gsoft_billing gb ON gb.lco_code = n.lcoCode AND gb.current_month = ih.current_month
          LEFT JOIN gsoft_payment_types pt ON pt.id = gb.payment_type
          LEFT JOIN m_user u ON u.id = n.created_by
         WHERE 1=1 {$where}
           AND (inv.total_invoice_count IS NOT NULL AND inv.total_invoice_count > 0)
           AND gb.lco_code IS NOT NULL
         ORDER BY ih.current_month DESC, n.network_name ASC";

        $rows = [];
        $totals = ['generated' => 0, 'free' => 0, 'extra' => 0, 'amount' => 0.0];
        foreach (DB::select($sql, $bind) as $r) {
            $free = (int) $r->free_sms_count_from_invoices + (int) $r->added_free_sms_from_payments;
            $count = (int) $r->total_invoice_count;
            $total = (float) $r->per_customer_price * $count;
            $st = (int) $r->bill_status;
            $pt = (int) $r->payment_type === 0 ? 'Direct cash' : ($r->payment_type_name ?: '');
            if ($st === 4) {
                $pt = '';
            }
            $totals['generated'] += $count;
            $totals['free'] += $free;
            $totals['extra'] += (int) $r->paid_sms_count;
            $totals['amount'] += $total;
            $rows[] = [
                'network' => $r->network_name, 'yearmonth' => $r->yearmonth, 'current_month' => $r->current_month, 'lco' => $r->lcoCode, 'invoices' => $count,
                'free_sms' => $free, 'paid_sms' => (int) $r->paid_sms_count, 'total' => $total, 'status' => self::STATUS[$st] ?? 'Unknown',
                'payable' => in_array($st, [0, 1, 3], true), 'payment_type' => $pt,
            ];
        }

        return ['rows' => $rows, 'totals' => $totals];
    }

    /** Administrator confirms a bill (cash etc.): status Paid, or Free when payment type is -2. Returns affected rows. */
    public function complete(string $lcoCode, string $currentMonth, int $paymentType): int
    {
        $status = $paymentType === -2 ? 4 : 2;
        $n = DB::table('gsoft_billing')->where('lco_code', $lcoCode)->where('current_month', $currentMonth)->update(['bill_status' => $status, 'payment_type' => $paymentType]);
        Log::channel('audit')->warning('gsoft bill completed by admin', ['lco' => $lcoCode, 'month' => $currentMonth, 'status' => $status, 'payment_type' => $paymentType, 'rows' => $n, 'by' => session('userid')]);

        return $n;
    }
}
