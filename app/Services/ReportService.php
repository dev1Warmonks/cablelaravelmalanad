<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Queries behind the Reporting screens (port of the Report controller).
 *
 * Same numbers as the CodeIgniter reports, but:
 *  - every value is a bound parameter (the old code pasted POST values, e.g. the LCO code and the expense head, into SQL);
 *  - day-by-day reports use ONE grouped query for the whole range instead of two queries per day (subscriberpayments has
 *    1.1 million rows and no index on `timestamp`, so a 31-day range cost 62 full scans);
 *  - the monthly report reads the per-customer paid totals in one query instead of one query per customer.
 * Day windows are exactly the old ones: timestamp > midnight(Asia/Kolkata) and < midnight + 24h (both ends exclusive).
 */
class ReportService
{
    public const DAY_MS = 86400000;

    /** Y-m-d (date inputs) or d-m-Y (the Daily report drill-down) -> midnight in Asia/Kolkata, else null. */
    public static function day(mixed $s): ?CarbonImmutable
    {
        if (! is_string($s)) {
            return null;
        }
        $s = trim($s);
        foreach (['Y-m-d', 'd-m-Y'] as $format) {
            try {
                $d = CarbonImmutable::createFromFormat('!'.$format, $s, 'Asia/Kolkata');
            } catch (\Throwable) {
                continue;   // Carbon throws on a format mismatch
            }
            if ($d->format($format) === $s) {
                return $d;
            }
        }

        return null;
    }

    /** [from, to) window in ms covering the days $from..$to inclusive. @return array{0:int,1:int} */
    public static function window(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return [$from->getTimestamp() * 1000, $to->getTimestamp() * 1000 + self::DAY_MS];
    }

    public static function formatDate(mixed $ms): string
    {
        return CarbonImmutable::createFromTimestamp((int) round(((float) $ms) / 1000), 'Asia/Kolkata')->format('d-m-Y');
    }

    public static function formatTime(mixed $ms): string
    {
        return CarbonImmutable::createFromTimestamp((int) round(((float) $ms) / 1000), 'Asia/Kolkata')->format('d/m/Y h:i:s a');
    }

    /** Payment rows of the LCO's customers (cash payments, "Opening balance" excluded), newest first. */
    public function payments(string $lco, int $fdate, int $tdate, ?int $agentId = null): Collection
    {
        $q = DB::table('subscriberpayments as sp')
            ->join('m_customer as c', 'c.id', '=', 'sp.subscriberid')
            ->leftJoin('agent as a', 'a.id', '=', 'sp.agentid')
            ->where('c.lcoCode', $lco)
            ->whereRaw('sp.transactiontype = 1')
            ->where('sp.timestamp', '>', $fdate)->where('sp.timestamp', '<', $tdate)
            ->where('sp.description', '!=', 'Opening balance');
        if ($agentId !== null) {
            $q->whereRaw('sp.agentid = ?', [$agentId]);
        }

        return $q->orderByDesc('sp.id')->get([
            'sp.id', 'sp.timestamp', 'sp.description', 'sp.dueamount', 'sp.paidamount', 'sp.discount', 'sp.dbafterdiscount',
            'c.customer_name', 'c.billing_address', 'c.mobile', 'c.lco_customer_id', 'a.name as agent',
        ]);
    }

    /**
     * Per-day collection / discount totals. @return array<int, array{cc:string,ca:string,dc:string,da:string}> keyed by day index (0 = $from)
     */
    public function dailyTotals(string $lco, int $fdate, int $tdate): array
    {
        $rows = DB::select(
            'SELECT FLOOR((CAST(sp.timestamp AS UNSIGNED) - ?) / ?) AS d, COUNT(sp.id) AS cc, SUM(sp.paidamount) AS ca,
                    SUM(sp.discount <> 0) AS dc, SUM(CASE WHEN sp.discount <> 0 THEN sp.discount ELSE 0 END) AS da
             FROM subscriberpayments sp JOIN m_customer c ON c.id = sp.subscriberid
             WHERE sp.timestamp > ? AND sp.timestamp < ? AND MOD(CAST(sp.timestamp AS UNSIGNED) - ?, ?) <> 0
               AND sp.transactiontype = 1 AND c.lcoCode = ?
             GROUP BY d',
            [$fdate, self::DAY_MS, $fdate, $tdate, $fdate, self::DAY_MS, $lco]
        );
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->d] = ['cc' => (string) $r->cc, 'ca' => (string) ($r->ca ?? '0'), 'dc' => (string) ($r->dc ?? '0'), 'da' => (string) ($r->da ?? '0')];
        }

        return $out;
    }

    /**
     * Agent collections per day, then per agent (Opening balance excluded).
     *
     * @return array<int, list<array{agentname:?string,c:string,s:string}>> day index => agent rows
     */
    public function agentCollectionByDay(string $lco, int $userId, int $fdate, int $tdate): array
    {
        $rows = DB::select(
            'SELECT FLOOR((CAST(sp.timestamp AS UNSIGNED) - ?) / ?) AS d, sp.agentid, MAX(a.name) AS agentname, COUNT(sp.id) AS c, SUM(sp.paidamount) AS s
             FROM subscriberpayments sp JOIN m_customer c ON c.id = sp.subscriberid
             LEFT JOIN agent a ON a.id = sp.agentid AND a.created_by = ?
             WHERE sp.timestamp > ? AND sp.timestamp < ? AND MOD(CAST(sp.timestamp AS UNSIGNED) - ?, ?) <> 0
               AND sp.description != \'Opening balance\' AND sp.transactiontype = 1 AND c.lcoCode = ?
             GROUP BY d, sp.agentid ORDER BY d, sp.agentid',
            [$fdate, self::DAY_MS, $userId, $fdate, $tdate, $fdate, self::DAY_MS, $lco]
        );
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->d][] = ['agentname' => $r->agentname, 'c' => (string) $r->c, 's' => (string) ($r->s ?? '0')];
        }

        return $out;
    }

    /** Agent totals for the whole range (the second table of the agent-wise collection report). */
    public function agentCollectionTotals(string $lco, int $fdate, int $tdate): array
    {
        return DB::select(
            'SELECT MAX(a.name) AS agentname, COUNT(sp.id) AS c, SUM(sp.paidamount) AS s
             FROM subscriberpayments sp JOIN m_customer c ON c.id = sp.subscriberid LEFT JOIN agent a ON a.id = sp.agentid
             WHERE sp.timestamp > ? AND sp.timestamp < ? AND sp.description != \'Opening balance\' AND sp.transactiontype = 1 AND c.lcoCode = ?
             GROUP BY sp.agentid ORDER BY sp.agentid',
            [$fdate, $tdate, $lco]
        );
    }

    /** Every customer of the LCO with balance and last-paid time (monthly report). */
    public function customersWithBalance(string $lco): Collection
    {
        return DB::table('m_customer as a')->leftJoin('subscriberbalance as b', 'b.subscriberid', '=', 'a.id')
            ->where('a.lcoCode', $lco)
            ->get(['a.id', 'a.customer_name', 'a.billing_address', 'a.mobile', 'a.lco_customer_id', 'b.amountdue', 'b.timestamp']);
    }

    /** Paid total per customer in the window. @return array<int,string> customer id => sum */
    public function paidPerCustomer(string $lco, int $fdate, int $tdate): array
    {
        return DB::table('subscriberpayments as sp')->join('m_customer as c', 'c.id', '=', 'sp.subscriberid')
            ->where('c.lcoCode', $lco)->whereRaw('sp.transactiontype = 1')
            ->where('sp.timestamp', '>', $fdate)->where('sp.timestamp', '<', $tdate)
            ->where('sp.description', '!=', 'Opening balance')
            ->groupBy('sp.subscriberid')->selectRaw('sp.subscriberid as sid, SUM(sp.paidamount) as total')
            ->pluck('total', 'sid')->mapWithKeys(fn ($v, $k) => [(int) $k => (string) $v])->all();
    }

    /** Payments with a discount, oldest first (discount report). */
    public function discounts(string $lco, int $fdate, int $tdate): Collection
    {
        return DB::table('subscriberpayments as sp')->join('m_customer as c', 'c.id', '=', 'sp.subscriberid')
            ->where('c.lcoCode', $lco)->whereRaw('sp.discount <> 0')
            ->where('sp.timestamp', '>', $fdate)->where('sp.timestamp', '<', $tdate)
            ->orderBy('sp.id')
            ->get(['sp.timestamp', 'sp.discount', 'c.lco_customer_id', 'c.customer_name', 'c.billing_address', 'c.mobile']);
    }

    /** SMS details of one payment, only for customers the user may see ($lco null = no restriction, admin). */
    public function paymentSms(int|string $paymentId, ?string $lco): ?object
    {
        $q = DB::table('subscriberpayments as sp')->where('sp.id', (string) $paymentId);
        if ($lco !== null) {
            $q->join('m_customer as c', 'c.id', '=', 'sp.subscriberid')->where('c.lcoCode', $lco);
        }

        return $q->first(['sp.sms', 'sp.sms_status', 'sp.sms_id', 'sp.mobile']);
    }
    // ------------------------------------------------------------------ agent panel (an agent's own collections)

    /** Per-day totals of one agent's cash payments (same shape as dailyTotals). */
    public function agentDailyTotals(int $agentId, int $fdate, int $tdate): array
    {
        $rows = DB::select(
            'SELECT FLOOR((CAST(timestamp AS UNSIGNED) - ?) / ?) AS d, COUNT(id) AS cc, SUM(paidamount) AS ca,
                    SUM(discount <> 0) AS dc, SUM(CASE WHEN discount <> 0 THEN discount ELSE 0 END) AS da
             FROM subscriberpayments
             WHERE timestamp > ? AND timestamp < ? AND MOD(CAST(timestamp AS UNSIGNED) - ?, ?) <> 0 AND transactiontype = 1 AND agentid = ?
             GROUP BY d',
            [$fdate, self::DAY_MS, $fdate, $tdate, $fdate, self::DAY_MS, $agentId]
        );
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->d] = ['cc' => (string) $r->cc, 'ca' => (string) ($r->ca ?? '0'), 'dc' => (string) ($r->dc ?? '0'), 'da' => (string) ($r->da ?? '0')];
        }

        return $out;
    }

    /** One agent's cash payments in the window, newest first. */
    public function agentPayments(int $agentId, int $fdate, int $tdate): Collection
    {
        return DB::table('subscriberpayments as sp')
            ->leftJoin('m_customer as c', 'c.id', '=', 'sp.subscriberid')
            ->whereRaw('sp.agentid = ?', [$agentId])->whereRaw('sp.transactiontype = 1')
            ->where('sp.timestamp', '>', $fdate)->where('sp.timestamp', '<', $tdate)
            ->where('sp.description', '!=', 'Opening balance')
            ->orderByDesc('sp.id')
            ->get(['sp.id', 'sp.timestamp', 'sp.description', 'sp.dueamount', 'sp.paidamount', 'sp.discount', 'sp.dbafterdiscount', 'c.customer_name', 'c.billing_address', 'c.mobile', 'c.lco_customer_id']);
    }
}
