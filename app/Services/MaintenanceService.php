<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/** Ports of Common_model::deactivate_customers() and update_last_paid_date_date() (admin maintenance scripts). */
class MaintenanceService
{
    /**
     * Customers without serial/VC number become inactive; customers with an invoice last month become active
     * (status 2 is never touched). Returns the same text the old script echoed.
     */
    public function deactivateCustomers(string $lcoCode): string
    {
        DB::transaction(function () use ($lcoCode) {
            $q = DB::table('m_customer')->where(fn ($w) => $w->where('serial_number', '')->orWhere('vc_number', ''));
            if ($lcoCode !== '') {
                $q->where('lcoCode', $lcoCode);
            }
            $q->update(['status' => 0]);
        });

        $month = date('Y-m', strtotime(date('Y-m').' -1 month'));
        $invoices = DB::table('m_ezybill_invoices')->where('current_month', 'like', $month.'%');
        if (! (clone $invoices)->exists()) {
            return '';
        }

        // Materialise the ids first (as the old code did): "NOT IN (subquery)" over 600k invoice rows takes minutes in MySQL.
        $ids = DB::table('m_ezybill_invoices')->distinct()
            ->where('current_month', 'like', $month.'%')->whereNotNull('customer_id')->where('customer_id', '!=', '')
            ->pluck('customer_id')->all() ?: [0];

        $out = '';
        DB::transaction(function () use ($lcoCode, $ids, &$out) {
            $inactive = DB::table('m_customer')->whereNotIn('id', $ids)->where('status', '!=', 2);
            $active = DB::table('m_customer')->whereIn('id', $ids)->where('status', '!=', 2);
            if ($lcoCode !== '') {
                $inactive->where('lcoCode', $lcoCode);
                $active->where('lcoCode', $lcoCode);
            }
            $out .= 'Effected Rows (Inactive) : '.$inactive->update(['status' => 0]).'<br>';
            $out .= 'Effected Rows (Active) : '.$active->update(['status' => 1]).'<br>';
        });

        return $out;
    }

    /** subscriberbalance.timestamp := time of the latest payment (transactiontype 1). One statement instead of 25k queries. */
    public function updateLastPaidDate(): int
    {
        // subscriberbalance has no index on subscriberid, so a JOIN UPDATE is quadratic. Compute the latest payment per
        // customer once (uses idx_subscriberid_id), then update by primary key in batches. MAX() on the varchar column
        // equals the old "ORDER BY timestamp DESC LIMIT 1" (same-length millisecond strings).
        $latest = DB::table('subscriberpayments')->where('transactiontype', 1)
            ->groupBy('subscriberid')->selectRaw('subscriberid, MAX(`timestamp`) as ts')->pluck('ts', 'subscriberid');

        $updated = 0;
        DB::transaction(function () use ($latest, &$updated) {
            $batch = [];
            $flush = function () use (&$batch, &$updated) {
                if (! $batch) {
                    return;
                }
                $case = '';
                $bind = [];
                foreach ($batch as $id => $ts) {
                    $case .= ' WHEN ? THEN ?';
                    array_push($bind, $id, $ts);
                }
                $in = implode(',', array_fill(0, count($batch), '?'));
                $updated += DB::update("UPDATE subscriberbalance SET `timestamp` = CASE id{$case} END WHERE id IN ({$in})", array_merge($bind, array_keys($batch)));
                $batch = [];
            };
            foreach (DB::table('subscriberbalance')->select('id', 'subscriberid')->orderBy('id')->cursor() as $row) {
                if (isset($latest[$row->subscriberid])) {
                    $batch[$row->id] = (string) $latest[$row->subscriberid];
                    if (count($batch) >= 500) {
                        $flush();
                    }
                }
            }
            $flush();
        });

        return $updated;
    }
}
