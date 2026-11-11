<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Customer bill generation (ports of Model_subscriber::generate_network_bills, generate_bills_for_selected and
 * delete_network_bills). A "bill" is an approved row in m_ezybill_invoices for the previous month plus a
 * "Monthly subscription" row in the customer's ledger (subscriberpayments) and the matching balance movement.
 *
 * Fixes vs the old code: every customer is processed in its own locked transaction and re-checked inside the lock, so a
 * double click or two admins at once cannot bill a customer twice; amounts are exact (bcmath); the global
 * "UPDATE m_ezybill_invoices ... current_month" normalisation that ran on 600k rows at every call is not repeated;
 * callers are scoped to their own network by the controller; every run is written to the audit log.
 */
class BillGenerationService
{
    private function month(?string $m): string
    {
        return $m !== null && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $m) ? $m : date('Y-m');
    }

    private function adminUserId(): int
    {
        return (int) (DB::table('m_user')->where('network_name', 'like', '%Admin%')->orderBy('id')->value('id') ?: 1);
    }

    /** [from, to) date range of the month that is being billed (the month BEFORE the selected invoice month). */
    private function billedMonthRange(string $billMonth): array
    {
        $first = $billMonth.'-01';
        $prev = date('Y-m-01', strtotime($first.' -1 month'));

        return [$prev, $first];
    }

    private function invoiceExists(string $customerId, string $from, string $to): bool
    {
        return DB::table('m_ezybill_invoices')->where('customer_id', $customerId)->where('current_month', '>=', $from)->where('current_month', '<', $to)->exists();
    }

    /** Posts invoice + ledger + balance for one customer (caller holds the customer lock). */
    private function post(object $cust, string $package, string $amount, int $days, string $invoiceDate, string $billedMonth, int $adminId, bool $ledgerGuard): void
    {
        DB::table('m_ezybill_invoices')->insert([
            'customer_id' => $cust->id, 'package' => $package, 'serial_number' => (string) ($cust->serial_number ?? ''), 'vc_number' => (string) ($cust->vc_number ?? ''),
            'invoice_date' => $invoiceDate, 'bill_amount' => $amount, 'discount_amount' => 0, 'tax_amount' => 0, 'cgst' => 0, 'sgst' => 0, 'cess' => 0,
            'mso_share' => 0, 'lco_share' => 0, 'total_amount' => $amount, 'current_bill_amount' => $amount, 'current_month' => $billedMonth,
            'status' => 1, 'no_of_days' => $days, 'created_on' => date('Y-m-d H:i:s'), 'created_by' => $adminId,
        ]);

        $existing = DB::table('subscriberbalance')->where('subscriberid', $cust->id)->value('amountdue');
        $prev = bcadd((string) ($existing ?? '0'), '0', 2);
        $new = bcadd($prev, $amount, 2);
        if ($existing !== null) {
            DB::table('subscriberbalance')->where('subscriberid', $cust->id)->update(['amountdue' => $new]);
        } else {
            DB::table('subscriberbalance')->insert(['subscriberid' => $cust->id, 'amountdue' => $new]);
        }

        if ($ledgerGuard) {
            // network run: no ledger row when the newest ledger row already ends at the new balance (old guard, kept)
            $latest = DB::table('subscriberpayments')->where('subscriberid', (string) $cust->id)->orderByDesc('id')->value('dbafterdiscount');
            if ($latest !== null && abs((float) $latest - (float) $new) <= 0.001) {
                return;
            }
        }
        DB::table('subscriberpayments')->insert([
            'transactiontype' => '0', 'subscriberid' => (string) $cust->id, 'agentid' => '0', 'description' => 'Monthly subscription',
            'dueamount' => $prev, 'paidamount' => 0, 'discount' => 0, 'subscriptioncharge' => $amount, 'duebalance' => $new, 'dbafterdiscount' => $new,
            'timestamp' => (string) round(microtime(true) * 1000),
        ]);
    }

    /** All active customers of an LCO that have no invoice for the billed month yet. */
    public function generateForNetwork(string $lcoCode, ?string $billMonth): array
    {
        @set_time_limit(0);
        $billMonth = $this->month($billMonth);
        [$billed, $to] = $this->billedMonthRange($billMonth);
        $invoiceDate = $billMonth.'-01';
        $adminId = $this->adminUserId();
        $extra = (string) (DB::table('m_network')->where('lcoCode', $lcoCode)->value('bill_extra_amount') ?: 0);

        $customers = DB::table('m_customer')->where('status', 1)->where('lcoCode', $lcoCode)->get(['id', 'customer_id', 'combo_plan', 'serial_number', 'vc_number']);
        if ($customers->isEmpty()) {
            return ['status' => true, 'active_count' => 0, 'pending_count' => 0, 'total_count' => 0];
        }
        $ids = $customers->pluck('id')->map(fn ($v) => (string) $v)->all();

        $combo = DB::table('m_combo_plan')->get(['id', 'name', 'rate'])->keyBy('id');
        $broadband = [];
        foreach (array_chunk($ids, 1000) as $chunk) {
            foreach (DB::table('m_broadband_customer as bc')->join('m_broadband_plan as bp', 'bp.id', '=', 'bc.broadband_plan')->whereIn('bc.customer_id', $chunk)->get(['bc.customer_id', 'bp.name', 'bp.package_amount as rate']) as $r) {
                $broadband[$r->customer_id] = $r;
            }
        }
        $latestIds = DB::table('m_ezybill_invoices')->selectRaw('customer_id, MAX(id) as max_id')->whereIn('customer_id', $ids)->groupBy('customer_id')->pluck('max_id')->all();
        $prevInv = [];
        foreach (array_chunk($latestIds, 1000) as $chunk) {
            foreach (DB::table('m_ezybill_invoices')->whereIn('id', $chunk)->get(['customer_id', 'package', 'current_bill_amount', 'no_of_days']) as $r) {
                $prevInv[$r->customer_id] = $r;
            }
        }

        $active = 0;
        foreach ($customers as $cust) {
            if ($this->invoiceExists((string) $cust->id, $billed, $to)) {
                continue;
            }
            $package = '';
            $amount = '0';
            $days = 30;
            $planId = (int) $cust->combo_plan;
            if ($planId > 0 && isset($combo[$planId])) {
                $package = ! empty($combo[$planId]->name) ? $combo[$planId]->name : 'Combo Plan';
                $amount = (string) (float) $combo[$planId]->rate;
            }
            if ($package === '' && isset($broadband[$cust->id])) {
                $package = ! empty($broadband[$cust->id]->name) ? $broadband[$cust->id]->name : 'Broadband Plan';
                $amount = (string) (float) $broadband[$cust->id]->rate;
            }
            if ($package === '' && isset($prevInv[$cust->id])) {
                $p = $prevInv[$cust->id];
                $package = (string) $p->package;
                $amount = (string) (float) $p->current_bill_amount;
                $d = (int) $p->no_of_days;
                $days = ($d >= 28 && $d <= 31) ? 30 : ($d > 0 ? $d : 30);
            }
            if ($package === '') {
                $package = 'Standard Plan';
                $amount = '0';
            }
            if ((float) $extra > 0) {
                $amount = bcadd($amount, $extra, 2);
            }
            $amount = bcadd($amount, '0', 2);

            DB::transaction(function () use ($cust, $package, $amount, $days, $invoiceDate, $billed, $to, $adminId, &$active) {
                DB::table('m_customer')->where('id', $cust->id)->lockForUpdate()->first(['id']);
                if ($this->invoiceExists((string) $cust->id, $billed, $to)) {
                    return;   // another request billed this customer meanwhile
                }
                $this->post($cust, $package, $amount, $days, $invoiceDate, $billed, $adminId, true);
                $active++;
            });
        }
        Log::channel('audit')->info('bills generated for network', ['lco' => $lcoCode, 'bill_month' => $billMonth, 'billed' => $active, 'customers' => $customers->count(), 'by' => session('userid')]);

        return ['status' => true, 'active_count' => $active, 'pending_count' => 0, 'total_count' => $customers->count()];
    }

    /** Selected customers with one flat amount ("Selected Subscription"); the customers become active. */
    public function generateForSelected(array $customerIds, string $amount, ?string $billMonth): array
    {
        @set_time_limit(0);
        $billMonth = $this->month($billMonth);
        [$billed, $to] = $this->billedMonthRange($billMonth);
        $invoiceDate = $billMonth.'-01';
        $adminId = $this->adminUserId();
        $amount = bcadd($amount, '0', 2);

        $customers = DB::table('m_customer')->whereIn('id', $customerIds)->get(['id', 'customer_id', 'combo_plan', 'serial_number', 'vc_number']);
        if ($customers->isEmpty()) {
            return ['status' => false, 'message' => 'No matching subscribers found'];
        }
        $active = 0;
        $skipped = 0;
        foreach ($customers as $cust) {
            DB::transaction(function () use ($cust, $amount, $invoiceDate, $billed, $to, $adminId, &$active, &$skipped) {
                DB::table('m_customer')->where('id', $cust->id)->lockForUpdate()->first(['id']);
                if ($this->invoiceExists((string) $cust->id, $billed, $to)) {
                    $skipped++;

                    return;
                }
                $this->post($cust, 'Selected Subscription', $amount, 30, $invoiceDate, $billed, $adminId, false);
                DB::table('m_customer')->where('id', $cust->id)->update(['status' => 1]);
                $active++;
            });
        }
        Log::channel('audit')->info('bills generated for selected customers', ['bill_month' => $billMonth, 'amount' => $amount, 'billed' => $active, 'skipped' => $skipped, 'by' => session('userid')]);

        return ['status' => true, 'active_count' => $active, 'skipped_count' => $skipped, 'pending_count' => 0, 'total_count' => $customers->count()];
    }

    /**
     * Admin only: removes the invoices of the billed month for an LCO, the "Monthly subscription" ledger rows of the selected month
     * and re-bases later ledger rows (old arithmetic kept: values are clamped at 0, balance = newest ledger row or 0).
     */
    public function deleteNetworkBills(string $lcoCode, ?string $billMonth): array
    {
        @set_time_limit(0);
        $billMonth = $this->month($billMonth);
        [$billed, $to] = $this->billedMonthRange($billMonth);
        $monthStart = (string) (strtotime($billMonth.'-01 00:00:00') * 1000);
        $monthEnd = (string) (strtotime(date('Y-m-t 23:59:59', strtotime($billMonth.'-01'))) * 1000);

        // literal id list: "IN (subquery)" on the 600k-row invoice table is ~60x slower
        $ids = DB::table('m_customer')->where('lcoCode', $lcoCode)->pluck('id')->map(fn ($v) => (string) $v)->all();
        $invoices = DB::table('m_ezybill_invoices')->where('current_month', '>=', $billed)->where('current_month', '<', $to)
            ->whereIn('customer_id', $ids)
            ->get(['id', 'customer_id', 'current_bill_amount', 'status']);

        $deletedActive = 0;
        foreach ($invoices as $inv) {
            if ((int) $inv->status !== 1) {
                continue;
            }
            DB::transaction(function () use ($inv, $monthStart, $monthEnd, &$deletedActive) {
                $cid = (string) $inv->customer_id;
                DB::table('m_customer')->where('id', $cid)->lockForUpdate()->first(['id']);
                DB::table('subscriberpayments')->where('subscriberid', $cid)->where('transactiontype', '0')->where('timestamp', '>=', $monthStart)->where('timestamp', '<=', $monthEnd)->delete();
                $amt = (string) (float) $inv->current_bill_amount;
                if ((float) $amt > 0) {
                    DB::update('UPDATE subscriberpayments SET dueamount = GREATEST(0, dueamount - ?), duebalance = GREATEST(0, duebalance - ?), dbafterdiscount = GREATEST(0, dbafterdiscount - ?) WHERE subscriberid = ? AND timestamp > ?', [$amt, $amt, $amt, $cid, $monthEnd]);
                }
                $final = DB::table('subscriberpayments')->where('subscriberid', $cid)->orderByDesc('id')->value('dbafterdiscount') ?? 0;
                DB::table('subscriberbalance')->where('subscriberid', $cid)->update(['amountdue' => $final]);
                $deletedActive++;
            });
        }
        DB::table('m_ezybill_invoices')->where('current_month', '>=', $billed)->where('current_month', '<', $to)
            ->whereIn('customer_id', $ids)->delete();
        Log::channel('audit')->warning('network bills deleted', ['lco' => $lcoCode, 'bill_month' => $billMonth, 'invoices' => $invoices->count(), 'active' => $deletedActive, 'by' => session('userid')]);

        return ['status' => true, 'deleted_count' => $invoices->count(), 'deleted_active' => $deletedActive];
    }
}
