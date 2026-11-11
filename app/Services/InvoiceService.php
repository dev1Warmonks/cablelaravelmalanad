<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Imported Ezybill invoices (m_ezybill_invoices): the "system calculated days and amount" shown next to each invoice
 * (port of Transaction::_callback_bill_noofdays) and the approval that posts the monthly charge to the customer ledger
 * (port of Transaction::approve).
 */
class InvoiceService
{
    /** @var array<string,array>|null product name => [bundle_total,total_amount,base_rate] (only names that exist exactly once) */
    private ?array $products = null;

    private function products(): array
    {
        if ($this->products === null) {
            $this->products = [];
            $count = [];
            foreach (DB::table('m_products')->get(['product_name', 'bundle_total', 'total_amount', 'base_rate']) as $p) {
                $count[$p->product_name] = ($count[$p->product_name] ?? 0) + 1;
                $this->products[$p->product_name] = (array) $p;
            }
            foreach ($count as $name => $n) {
                if ($n !== 1) {
                    unset($this->products[$name]);   // the old query only used a product when exactly one row matched
                }
            }
        }

        return $this->products;
    }

    /**
     * Days and amount the system proposes for an invoice row.
     *
     * @return array{days:int|string, amount:float|int, colour:string}
     */
    public function proposal(object $row): array
    {
        $total = (float) $row->total_amount;
        if (isset($row->no_of_days) && ($row->no_of_days >= 28 || $row->package == 'Combo Plan')) {
            return ['days' => 30, 'amount' => round($total, 2), 'colour' => 'green'];
        }
        if (isset($row->no_of_days) && $row->no_of_days > 0) {
            return ['days' => (int) $row->no_of_days, 'amount' => round($total, 2), 'colour' => 'red'];
        }

        $current = 0.0;
        $packageTotal = 0.0;
        $bundles = 0;
        foreach (explode(',', (string) $row->package) as $name) {
            if (substr($name, 0, 1) === ' ') {
                $name = substr($name, 1);
            }
            $p = $this->products()[$name] ?? null;
            if ($p === null) {
                continue;
            }
            $packageTotal += (float) $p['total_amount'];
            if ((float) $p['bundle_total'] != 0) {
                $bundles++;
                $current += (float) $p['bundle_total'];
            } else {
                $current += (float) $p['total_amount'];
            }
        }
        if (round($packageTotal, 2) == round($total, 2)) {
            return ['days' => 30, 'amount' => round($current, 2), 'colour' => $bundles > 1 ? 'red' : 'green'];
        }
        $perDay = round($packageTotal / 30, 2);
        $days = $perDay > 0 ? $total / $perDay : 0;
        $amount = ($current / 30) * round($days);

        return ['days' => (int) round($days), 'amount' => round($amount, 2), 'colour' => $bundles > 1 ? 'red' : 'green'];
    }

    /**
     * Posts the invoice's charge to the customer: marks the invoice approved and, unless a "Monthly subscription" row already
     * exists for the customer this calendar month, adds the ledger row and moves the balance. Locked + all-or-nothing.
     *
     * @return string 'ok' | 'denied' | 'already'
     */
    public function approve(int|string $invoiceId, int|string $customerId, string $amount, int $days, ?string $lcoCode, bool $isAdmin): string
    {
        if (! preg_match('/^\d{1,7}(\.\d{1,2})?$/', $amount) || $days < 0 || $days > 31) {
            return 'denied';
        }

        return DB::transaction(function () use ($invoiceId, $customerId, $amount, $days, $lcoCode, $isAdmin) {
            $customer = DB::table('m_customer')->where('id', $customerId);
            if (! $isAdmin) {
                $customer->where('lcoCode', $lcoCode);
            }
            if (! $customer->lockForUpdate()->exists()) {
                return 'denied';
            }
            $invoice = DB::table('m_ezybill_invoices')->where('id', $invoiceId)->where('customer_id', (string) $customerId)->lockForUpdate()->first(['id', 'status']);
            if (! $invoice) {
                return 'denied';
            }
            if ((int) $invoice->status === 1) {
                return 'already';
            }
            DB::table('m_ezybill_invoices')->where('id', $invoiceId)->update(['status' => 1, 'current_bill_amount' => $amount, 'no_of_days' => $days]);

            $monthStart = (string) (strtotime(date('Y-m-').'01 00:00:00') * 1000);
            $hasMonthly = DB::table('subscriberpayments')->where('subscriberid', (string) $customerId)->where('transactiontype', '0')->where('timestamp', '>', $monthStart)->exists();
            if (! $hasMonthly) {
                $due = bcadd((string) (DB::table('subscriberbalance')->where('subscriberid', $customerId)->value('amountdue') ?? '0'), '0', 2);
                $after = bcadd($due, $amount, 2);
                $ts = PaymentService::milliseconds();
                if (DB::table('subscriberbalance')->where('subscriberid', $customerId)->exists()) {
                    DB::table('subscriberbalance')->where('subscriberid', $customerId)->update(['amountdue' => $after, 'timestamp' => $ts]);
                } else {
                    DB::table('subscriberbalance')->insert(['subscriberid' => $customerId, 'amountdue' => $after, 'timestamp' => $ts]);
                }
                DB::table('subscriberpayments')->insert([
                    'transactiontype' => '0', 'subscriberid' => (string) $customerId, 'description' => 'Monthly subscription', 'dueamount' => $due, 'agentid' => '0',
                    'paidamount' => 0, 'discount' => 0, 'subscriptioncharge' => $amount, 'duebalance' => $after, 'dbafterdiscount' => $after, 'timestamp' => $ts,
                ]);
            }
            Log::channel('audit')->info('invoice approved', ['invoice' => $invoiceId, 'customer' => $customerId, 'amount' => $amount, 'days' => $days, 'ledger_row_added' => ! $hasMonthly, 'by' => session('userid')]);

            return 'ok';
        });
    }

    /**
     * Counts shown on the import-history grid for one month (invoices pending/approved/total, SMS sent/pending/queued).
     * One query per month; $customerIds null = admin (all customers).
     */
    public function monthCounts(string $month, ?array $customerIds, ?array $smsCustomerIds, ?array $visibleCustomerIds): array
    {
        $q = DB::table('m_ezybill_invoices')->where('current_month', $month);
        if ($customerIds === null) {
            $r = $q->selectRaw('SUM(status = 0) p, SUM(status = 1) c, COUNT(*) t, SUM(sms_status = 2) ss, SUM(sms_status = 0 AND status = 1) sp, SUM(sms_status = 1) sq')->first();

            return ['pending' => (int) $r->p, 'completed' => (int) $r->c, 'total' => (int) $r->t, 'sms_send' => (int) $r->ss, 'sms_pending' => (int) $r->sp, 'sms_queue' => (int) $r->sq];
        }
        $out = ['pending' => 0, 'completed' => 0, 'total' => 0, 'sms_send' => 0, 'sms_pending' => 0, 'sms_queue' => 0];
        $mine = array_flip($customerIds);
        $sms = array_flip($smsCustomerIds ?? []);
        $visible = array_flip($visibleCustomerIds ?? []);
        foreach ($q->whereIn('customer_id', array_map('strval', $customerIds))->get(['customer_id', 'status', 'sms_status']) as $r) {
            if (! isset($mine[$r->customer_id])) {
                continue;
            }
            $out['total']++;
            $r->status == 0 && $out['pending']++;
            $r->status == 1 && $out['completed']++;
            $r->sms_status == 2 && $out['sms_send']++;
            ($r->sms_status == 0 && $r->status == 1 && isset($sms[$r->customer_id])) && $out['sms_pending']++;
            ($r->sms_status == 1 && isset($visible[$r->customer_id])) && $out['sms_queue']++;
        }

        return $out;
    }
}
