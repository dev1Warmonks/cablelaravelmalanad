<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Port of Dashboardmodel (admin/LCO dashboard). Same numbers and same output formatting as the
 * CodeIgniter version, but with bound parameters instead of string-built SQL.
 *
 * Quirks kept on purpose (so values match the old site): time windows use strtotime() on
 * "Y-m-d" strings in Asia/Kolkata with strict > and <; "previous month" uses "<Y-m>-31"
 * (strtotime overflows into the next month for short months); some tiles are formatted with
 * number_format() and some are raw numbers.
 */
class DashboardService
{
    private string $lco;

    public function __construct()
    {
        $this->lco = (string) session('lcoCode', '');
    }

    public function all(): array
    {
        return [
            'todays_total_collection' => $this->todaysTotalCollection(),
            'same_day_last_month_total_collection' => $this->sameDayLastMonthTotalCollection(),
            'last_month_outstanding' => $this->lastMonthOutstanding(),
            'this_month_total_collection' => $this->thisMonthTotalCollection(),
            'total_collection' => $this->totalCollection(),
            'previous_month_total_collection' => $this->previousMonthTotalCollection(),
            'total_outstanding' => $this->totalOutstanding(),
            'this_month_miscellaneous_collection' => $this->thisMonthMiscCollection(),
            'active_customer_count' => $this->activeCustomerCount(),
            'sms_balance' => $this->smsBalance(),
            'agent_wise_total_collection' => $this->agentWiseTotalCollection(),
        ];
    }

    /** Empty structures shown to usertype 1 (admin has no network), as the old controller did. */
    public function emptyAll(): array
    {
        return array_fill_keys([
            'todays_total_collection', 'same_day_last_month_total_collection', 'last_month_outstanding',
            'this_month_total_collection', 'total_collection', 'previous_month_total_collection',
            'total_outstanding', 'this_month_miscellaneous_collection', 'active_customer_count',
            'sms_balance', 'agent_wise_total_collection',
        ], []);
    }

    private function customerIds()
    {
        return DB::table('m_customer')->select('id')->where('lcoCode', $this->lco);
    }

    private function payments()
    {
        return DB::table('subscriberpayments')->where('transactiontype', 1)
            ->whereIn('subscriberid', $this->customerIds());
    }

    private function window(string $from, string $to): array
    {
        return [strtotime($from) * 1000, (strtotime($to) * 1000) + 24 * 60 * 60 * 1000];
    }

    private function num($v)
    {
        return 0 + ($v ?? 0);   // PHP numeric-string coercion like "$total += $row['x']"
    }

    private function fmt($v, int $dec = 2): string
    {
        return number_format((float) ($v ?? 0), $dec);
    }

    private function discountRow(?array $w = null)
    {
        $q = $this->payments()->where('discount', '!=', '0.0000');
        if ($w) {
            $q->where('timestamp', '>', $w[0])->where('timestamp', '<', $w[1]);
        }

        return $q->selectRaw('sum(discount) as discountamount, count(id) as discountcount')->first();
    }

    public function todaysTotalCollection(): array
    {
        $w = $this->window(date('Y-m-d'), date('Y-m-d'));
        $c = $this->payments()->where('timestamp', '>', $w[0])->where('timestamp', '<', $w[1])
            ->selectRaw('sum(paidamount) as collectionamount, count(id) as collectioncount')->first();
        $d = $this->discountRow($w);

        return [
            'totalcollectioncount' => $c->collectioncount ?? 0,
            'totalcollectionamount' => $this->fmt($c->collectionamount ?? 0),
            'totaldiscountcount' => $this->num($d->discountcount ?? 0),
            'totaldiscountamount' => $this->fmt($this->num($d->discountamount ?? 0)),
        ];
    }

    public function sameDayLastMonthTotalCollection(): array
    {
        $dt = date_create(date('Y-m-d').' -1 month')->format('Y-m-d');
        $w = $this->window($dt, $dt);
        $c = $this->payments()->where('timestamp', '>', $w[0])->where('timestamp', '<', $w[1])
            ->selectRaw('sum(paidamount) as collectionamount')->first();

        return ['totalcollectionamount' => $this->fmt($this->num($c->collectionamount ?? 0))];
    }

    public function thisMonthTotalCollection(): array
    {
        $w = $this->window(date('Y-m').'-1', date('Y-m-d'));
        $c = $this->payments()->where('timestamp', '>', $w[0])->where('timestamp', '<', $w[1])
            ->selectRaw('sum(paidamount) as collectionamount, count(id) as collectioncount')->first();
        $d = $this->discountRow($w);

        return [   // raw (unformatted) like the old tile
            'totalcollectioncount' => $this->num($c->collectioncount ?? 0),
            'totalcollectionamount' => $this->num($c->collectionamount ?? 0),
            'totaldiscountcount' => $this->num($d->discountcount ?? 0),
            'totaldiscountamount' => $this->num($d->discountamount ?? 0),
        ];
    }

    public function thisMonthMiscCollection(): array
    {
        $w = $this->window(date('Y-m').'-1', date('Y-m-d'));
        $c = $this->payments()->where('is_misc', 1)->where('timestamp', '>', $w[0])->where('timestamp', '<', $w[1])
            ->selectRaw('sum(paidamount) as collectionamount')->first();

        return ['totalcollectionamount' => $this->fmt($this->num($c->collectionamount ?? 0))];
    }

    public function totalCollection(): array
    {
        $c = $this->payments()->selectRaw('sum(paidamount) as collectionamount')->first();
        $d = $this->discountRow();

        return [
            'totalcollectionamount' => $this->fmt($this->num($c->collectionamount ?? 0)),
            'totaldiscountamount' => $this->fmt($this->num($d->discountamount ?? 0)),
        ];
    }

    public function previousMonthTotalCollection(): array
    {
        $dt = date_create(date('Y-m-d').' first day of last month');
        $w = $this->window($dt->format('Y-m-d'), $dt->format('Y-m').'-31');
        $c = $this->payments()->where('timestamp', '>', $w[0])->where('timestamp', '<', $w[1])
            ->selectRaw('sum(paidamount) as collectionamount')->first();

        return ['totalcollectionamount' => $this->fmt($this->num($c->collectionamount ?? 0))];
    }

    public function totalOutstanding(): array
    {
        $v = DB::table('subscriberbalance')->whereIn('subscriberid', $this->customerIds())
            ->selectRaw('sum(amountdue) as total_outstanding')->value('total_outstanding');

        return ['total_outstanding' => number_format((float) ($v ?? 0), 2, '.', '')];
    }

    public function lastMonthOutstanding(): array
    {
        $month = $this->thisMonthTotalCollection()['totalcollectionamount'];
        $outstanding = $this->totalOutstanding()['total_outstanding'];

        return ['total_outstanding' => $month + $outstanding];
    }

    public function activeCustomerCount(): array
    {
        $base = DB::table('m_customer')->where('lcoCode', $this->lco);

        return [
            'active_customer' => (clone $base)->where('status', 1)->count('id'),
            'total_customer' => (clone $base)->count('id'),
        ];
    }

    public function smsBalance(): array
    {
        return ['smscount' => DB::table('m_user')->where('id', session('userid'))->value('smscount') ?? 0];
    }

    public function agentWiseTotalCollection(): array
    {
        $w = $this->window(date('Y-m-d'), date('Y-m-d'));

        return DB::table('subscriberpayments as a')
            ->leftJoin('agent as b', 'b.id', '=', 'a.agentid')
            ->where('a.timestamp', '>', $w[0])->where('a.timestamp', '<', $w[1])
            ->where('a.transactiontype', 1)
            ->whereIn('a.subscriberid', $this->customerIds())
            ->groupBy('a.agentid', 'b.name')
            ->orderByDesc('collectionamount')
            ->selectRaw('sum(a.paidamount) as collectionamount, a.agentid, b.name')
            ->get()->map(fn ($r) => (array) $r)->all();
    }
}
