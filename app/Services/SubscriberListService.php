<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Port of Model_subscriber::subcriber_list / subcriber_Mlist / all_subcriber_list.
 * Same rows, same JSON keys and formatting; inputs are validated and bound (the old code concatenated
 * `filteramount` straight into SQL).
 */
class SubscriberListService
{
    private function lastPaid(?string $ts): string
    {
        if ($ts === null || $ts === '') {
            return '';
        }

        return date('M-d-Y h:i a', (int) ($ts / 1000));   // Model_subscriber::formatTimeDMY (Asia/Kolkata)
    }

    private function intOrDefault($v, int $default = -1): int
    {
        return is_numeric($v) ? (int) $v : $default;
    }

    /** Shared filters of the first two lists. */
    private function baseQuery($filterAmount, $area, $subarea, $status, $paidStat, string $extraSelect = '')
    {
        $q = DB::table('m_customer')
            ->leftJoin('m_area', 'm_area.id', '=', 'm_customer.area_id')
            ->leftJoin('m_sub_area', 'm_sub_area.id', '=', 'm_customer.subarea_id')
            ->leftJoin('subscriberbalance', 'subscriberbalance.subscriberid', '=', 'm_customer.id')
            ->selectRaw('m_customer.id,amc_status,customer_name,mobile,lco_customer_id,billing_address,m_area.areaname as area,m_sub_area.areaname as subarea,m_customer.status,subscriberbalance.amountdue as due,subscriberbalance.timestamp as lastpaid,m_customer.sms'.$extraSelect);

        if ((string) session('usertype') !== '1') {
            $q->where('m_customer.lcoCode', session('lcoCode'));
        }
        if (is_numeric($filterAmount) && (float) $filterAmount != 0) {
            $q->where('subscriberbalance.amountdue', '>=', (float) $filterAmount);
        }
        if (($a = $this->intOrDefault($area)) !== -1) {
            $q->where('m_customer.area_id', $a);
        }
        if (($s = $this->intOrDefault($subarea)) !== -1) {
            $q->where('m_customer.subarea_id', $s);
        }
        if (($st = $this->intOrDefault($status)) !== -1) {
            $q->where('m_customer.status', $st);
        } else {
            $q->where('m_customer.status', '!=', 2);
        }
        $paid = $this->intOrDefault($paidStat);
        if ($paid === 1 || $paid === 2) {
            $from = strtotime(date('Y-m-01')) * 1000;
            $to = strtotime(date('Y-m-t')) * 1000 + 24 * 60 * 60 * 1000;
            // subscriberid is varchar, m_customer.id is int: convert so the index on subscriberpayments can be used
            $q->whereRaw(($paid === 2 ? 'NOT ' : '').'EXISTS (SELECT 1 FROM subscriberpayments WHERE subscriberpayments.subscriberid = CONVERT(m_customer.id, CHAR) AND transactiontype = 1 AND timestamp BETWEEN ? AND ?)', [$from, $to]);
        }

        return $q->orderBy('lco_customer_id', 'ASC');
    }

    /** index/list and inactive_subscriber/list */
    public function subscriberList($filterAmount, $area, $subarea, $status, $paidStat): array
    {
        $rows = $this->baseQuery($filterAmount, $area, $subarea, $status, $paidStat, ',m_combo_plan.name as combo_plan_name,m_customer.combo_plan,m_customer.newid')
            ->leftJoin('m_combo_plan', 'm_combo_plan.id', '=', 'm_customer.combo_plan')->get();

        $packageMap = [];
        if ($rows->isNotEmpty()) {
            // Latest invoice per customer. Needs idx_customer_id on m_ezybill_invoices (database/sql/001_*.sql, approved):
            // with it this is milliseconds; without it the IN() scan of 600k rows takes ~26 s.
            $max = DB::table('m_ezybill_invoices')->selectRaw('customer_id, MAX(id) as max_id')
                ->whereIn('customer_id', array_map('strval', $rows->pluck('id')->all()))->groupBy('customer_id')->pluck('max_id', 'customer_id')->all();
            $wanted = $max;
            if ($wanted) {
                foreach (DB::table('m_ezybill_invoices')->whereIn('id', array_values($wanted))->get(['customer_id', 'package']) as $i) {
                    if (! empty($i->package)) {
                        $packageMap[$i->customer_id] = $i->package;
                    }
                }
            }
        }

        $productPrice = [];
        foreach (DB::table('m_products')->get(['product_name', 'bundle_total', 'total_amount', 'base_rate']) as $p) {
            $price = 0;
            if (! empty($p->bundle_total) && (float) $p->bundle_total != 0) {
                $price = (float) $p->bundle_total;
            } elseif (! empty($p->total_amount) && (float) $p->total_amount != 0) {
                $price = (float) $p->total_amount;
            } elseif (! empty($p->base_rate) && (float) $p->base_rate != 0) {
                $price = (float) $p->base_rate;
            }
            $productPrice[strtolower(trim((string) $p->product_name))] = $price;
        }

        $comboPrice = [];
        foreach (DB::table('m_combo_plan')->get(['id', 'name', 'rate']) as $c) {
            $comboPrice[$c->id] = (float) $c->rate;
            if (! empty($c->name)) {
                $comboPrice[strtolower(trim($c->name))] = (float) $c->rate;
            }
        }

        $out = [];
        foreach ($rows as $r) {
            $pkg = isset($packageMap[$r->id]) && $packageMap[$r->id] !== '' ? $packageMap[$r->id] : (! empty($r->combo_plan_name) ? $r->combo_plan_name : '-');
            $amount = 0;
            if (! empty($r->combo_plan) && isset($comboPrice[$r->combo_plan])) {
                $amount = $comboPrice[$r->combo_plan];
            } elseif ($pkg !== '-' && ! empty($pkg)) {
                foreach (explode(',', $pkg) as $part) {
                    $key = strtolower(trim($part));
                    if (isset($productPrice[$key])) {
                        $amount += $productPrice[$key];
                    } elseif (isset($comboPrice[$key])) {
                        $amount += $comboPrice[$key];
                    }
                }
            }
            $out[] = [
                'sms_stat' => $r->sms, 'customer_name' => $r->customer_name, 'customer_id' => $r->id,
                'billing_address' => $r->billing_address, 'lco_customer_id' => $r->lco_customer_id, 'amc_status' => $r->amc_status,
                'mobile' => $r->mobile, 'area' => $r->area, 'subarea' => $r->subarea, 'package_name' => $pkg,
                'combo_plan' => $r->combo_plan, 'package_amount' => number_format($amount, 2, '.', ''), 'newid' => $r->newid,
                'due' => $r->due, 'lastpaid' => $this->lastPaid($r->lastpaid), 'status' => $r->status,
            ];
        }

        return $out;
    }

    /** index/Mlist */
    public function mList($filterAmount, $area, $subarea, $status, $paidStat): array
    {
        $out = [];
        foreach ($this->baseQuery($filterAmount, $area, $subarea, $status, $paidStat)->get() as $r) {
            $out[] = [
                'sms_stat' => $r->sms, 'customer_name' => $r->customer_name, 'customer_id' => $r->id,
                'amc_status' => $r->amc_status, 'billing_address' => $r->billing_address, 'lco_customer_id' => $r->lco_customer_id,
                'mobile' => $r->mobile, 'area' => $r->area, 'subarea' => $r->subarea, 'due' => $r->due,
                'lastpaid' => $this->lastPaid($r->lastpaid), 'status' => $r->status,
            ];
        }

        return $out;
    }

    /** all_subscriber/list (admin) */
    public function allList($lcoCode, $area, $subarea, $status): array
    {
        $q = DB::table('m_customer')
            ->leftJoin('m_area', 'm_area.id', '=', 'm_customer.area_id')
            ->leftJoin('m_sub_area', 'm_sub_area.id', '=', 'm_customer.subarea_id')
            ->leftJoin('subscriberbalance', 'subscriberbalance.subscriberid', '=', 'm_customer.id')
            ->selectRaw('m_customer.id,amc_status,customer_name,mobile,lco_customer_id,billing_address,m_area.areaname as area,m_sub_area.areaname as subarea,m_customer.status,subscriberbalance.amountdue as due,subscriberbalance.timestamp as lastpaid');
        if ($lcoCode !== null && $lcoCode !== '') {
            $q->where('m_customer.lcoCode', $lcoCode);
        }
        if (($a = $this->intOrDefault($area)) !== -1) {
            $q->where('m_customer.area_id', $a);
        }
        if (($s = $this->intOrDefault($subarea)) !== -1) {
            $q->where('m_customer.subarea_id', $s);
        }
        if (($st = $this->intOrDefault($status)) !== -1) {
            $q->where('m_customer.status', $st);
        }

        $out = [];
        foreach ($q->orderBy('lco_customer_id', 'ASC')->get() as $r) {
            $out[] = [
                'customer_name' => $r->customer_name, 'customer_id' => $r->id, 'amc_status' => $r->amc_status,
                'billing_address' => $r->billing_address, 'lco_customer_id' => $r->lco_customer_id, 'mobile' => $r->mobile,
                'area' => $r->area, 'subarea' => $r->subarea, 'due' => $r->due, 'lastpaid' => $this->lastPaid($r->lastpaid), 'status' => $r->status,
            ];
        }

        return $out;
    }
}
