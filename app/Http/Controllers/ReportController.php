<?php

namespace App\Http\Controllers;

use App\Services\MenuService;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Reporting screens (port of the CI Report controller; usertype 1 and 2, enforced by the legacy.session middleware).
 * The *_data actions return HTML fragments that the pages load with AJAX, as before; every value is HTML-escaped now.
 */
class ReportController extends Controller
{
    public function __construct(private MenuService $menus, private ReportService $reports) {}

    private function page(string $view, array $data = [])
    {
        return view('admin.report.'.$view, $data + ['menulist' => $this->menus->menuList(), 'network_list' => $this->menus->networkList()]);
    }

    private function fragment(string $view, array $data = [])
    {
        return response(view('admin.report.data.'.$view, $data)->render());
    }

    private function lco(): string
    {
        return (string) session('lcoCode', '');
    }

    /** @return array{0:CarbonImmutable,1:CarbonImmutable}|null both days valid and from <= to */
    private function days(Request $request): ?array
    {
        $from = ReportService::day($request->input('sfromdate'));
        $to = ReportService::day($request->input('stodate'));

        return ($from && $to && $from <= $to) ? [$from, $to] : null;
    }

    private static function money(string|int|float|null $v): string
    {
        return number_format((float) $v, 2);
    }

    // ------------------------------------------------------------------ pages

    public function agentReport()
    {
        return $this->page('agentwisereport', [
            'agent' => DB::table('agent')->where('created_by', session('userid'))->get(['id', 'name'])->map(fn ($r) => (array) $r)->all(),
        ]);
    }

    public function dateWiseReport()
    {
        return $this->page('datewisereport');
    }

    public function monthWiseReport()
    {
        return $this->page('monthwisereport');
    }

    public function miscellaneousReport()
    {
        return $this->page('miscellaneousreport', [
            'miscellaneousheads' => DB::table('m_dropdown')->where('type', 3)->get(['id', 'name'])->map(fn ($r) => (array) $r)->all(),
        ]);
    }

    public function agentWiseCollectionReport()
    {
        return $this->page('agentwisecollectionreport');
    }

    public function expenseReport()
    {
        return $this->page('expensereport', [
            'expenseheader' => DB::table('expenseheader')->where('created_by', session('userid'))->get(['id', 'title'])->map(fn ($r) => (array) $r)->all(),
        ]);
    }

    public function discountReport()
    {
        return $this->page('discountreport');
    }

    // ------------------------------------------------------------------ payment lists (Agent report / Daily drill-down / Network)

    public function agentReportData(Request $request)
    {
        $d = $this->days($request);
        if (! $d) {
            return response('');
        }
        [$f, $t] = ReportService::window(...$d);
        $agent = (int) $request->input('agentid', -1);   // -1 = all, 0 = admin
        $rows = $this->reports->payments($this->lco(), $f, $t, $agent === -1 ? null : $agent);

        return $this->fragment('payments', $this->paymentsView($rows, 'agent'));
    }

    public function dailyReportData(Request $request)
    {
        $d = $this->days($request);
        if (! $d) {
            return response('');
        }
        [$f, $t] = ReportService::window(...$d);
        $net = DB::table('m_network')->where('id', session('network_id'))->first(['network_name', 'lcoCode', 'address', 'phone']);
        if (! $net) {
            return response('');
        }
        $rows = $this->reports->payments($this->lco(), $f, $t);

        return $this->fragment('payments', $this->paymentsView($rows, 'daily') + [
            'header' => ['network_name' => (string) $net->network_name, 'report_name' => 'Transaction History', 'lcoCode' => (string) $net->lcoCode,
                'address' => (string) $net->address, 'phone' => (string) $net->phone],
            'username' => (string) session('username'), 'now' => date('Y-m-d h:i:s A'),
        ]);
    }

    /** Transactions of one of the user's own networks (the old code accepted ANY LCO code from the browser). */
    public function networkDailyReportData(Request $request)
    {
        $d = $this->days($request);
        $network = (string) $request->input('network', '');
        // admin (the All network transactions page) may pick any active network; an LCO only its own
        $allowed = (string) session('usertype') === '1' ? DB::table('m_network')->where('status', 1)->pluck('lcoCode')->all() : array_column($this->menus->networkList(), 'lcoCode');
        if (! $d || $network === '' || ! in_array($network, $allowed, true)) {
            return response('');
        }
        [$f, $t] = ReportService::window(...$d);

        return $this->fragment('payments', $this->paymentsView($this->reports->payments($network, $f, $t), 'network'));
    }

    private function paymentsView($rows, string $variant): array
    {
        $totals = ['due' => '0', 'paid' => '0', 'discount' => '0', 'outstanding' => '0'];
        $list = [];
        foreach ($rows as $r) {
            $totals['due'] = bcadd($totals['due'], (string) $r->dueamount, 4);
            $totals['paid'] = bcadd($totals['paid'], (string) $r->paidamount, 4);
            $totals['discount'] = bcadd($totals['discount'], (string) $r->discount, 4);
            $totals['outstanding'] = bcadd($totals['outstanding'], (string) $r->dbafterdiscount, 4);
            $list[] = (object) [
                'id' => $r->id, 'date' => $variant === 'daily' ? ReportService::formatTime($r->timestamp) : ReportService::formatDate($r->timestamp),
                'lco_customer_id' => $r->lco_customer_id, 'customer_name' => $r->customer_name, 'billing_address' => $r->billing_address,
                'mobile' => $r->mobile, 'description' => $r->description, 'dueamount' => $r->dueamount, 'paidamount' => $r->paidamount,
                'discount' => $r->discount, 'dbafterdiscount' => $r->dbafterdiscount, 'agent' => ($r->agent ?? '') === '' ? 'Admin' : $r->agent,
            ];
        }

        return [
            'variant' => $variant, 'rows' => $list, 'count' => count($list),
            'totals' => array_map(fn ($v) => self::money($v), $totals),
        ];
    }

    // ------------------------------------------------------------------ date-wise report

    public function dateWiseReportData(Request $request)
    {
        $d = $this->days($request);
        if (! $d) {
            return response('');
        }
        [$from, $to] = $d;
        [$f, $t] = ReportService::window($from, $to);
        $byDay = $this->reports->dailyTotals($this->lco(), $f, $t);
        $days = [];
        $tot = ['cc' => 0, 'ca' => '0', 'dc' => 0, 'da' => '0'];
        for ($i = 0, $day = $from; $day <= $to; $i++, $day = $day->addDay()) {
            $r = $byDay[$i] ?? ['cc' => '0', 'ca' => '0', 'dc' => '0', 'da' => '0'];
            $tot['cc'] += (int) $r['cc'];
            $tot['ca'] = bcadd($tot['ca'], $r['ca'], 4);
            $tot['dc'] += (int) $r['dc'];
            $tot['da'] = bcadd($tot['da'], $r['da'], 4);
            $days[] = ['ymd' => $day->format('Y-m-d'), 'dmy' => $day->format('d-m-Y'), 'cc' => $r['cc'], 'ca' => self::money($r['ca']), 'dc' => $r['dc'], 'da' => self::money($r['da'])];
        }

        return $this->fragment('date_wise', [
            'first' => $from->format('Y-m-d'), 'last' => $to->format('Y-m-d'), 'days' => $days,
            'total' => ['cc' => $tot['cc'], 'ca' => self::money($tot['ca']), 'dc' => $tot['dc'], 'da' => self::money($tot['da'])],
        ]);
    }

    // ------------------------------------------------------------------ monthly report

    public function monthlyReportData(Request $request)
    {
        $d = $this->days($request);
        if (! $d) {
            return response('');
        }
        [$f, $t] = ReportService::window(...$d);
        $paidstat = (int) $request->input('paidstat', -1);   // -1 all, 1 paid in the period, 2 not paid
        $paid = $this->reports->paidPerCustomer($this->lco(), $f, $t);
        $rows = [];
        $totalPaid = '0';
        $totalDue = '0';
        foreach ($this->reports->customersWithBalance($this->lco()) as $c) {
            $has = array_key_exists((int) $c->id, $paid);
            if (! ($paidstat === -1 || ($paidstat === 1 && $has) || ($paidstat === 2 && ! $has))) {
                continue;
            }
            $p = $paid[(int) $c->id] ?? '0';
            $totalPaid = bcadd($totalPaid, $p, 4);
            $totalDue = bcadd($totalDue, (string) ($c->amountdue ?? '0'), 4);
            $rows[] = (object) [
                'lco_customer_id' => $c->lco_customer_id, 'customer_name' => $c->customer_name, 'billing_address' => $c->billing_address,
                'mobile' => $c->mobile, 'paid' => self::money($p), 'due' => self::money($c->amountdue ?? 0), 'last' => ReportService::formatDate($c->timestamp ?? 0),
            ];
        }

        return $this->fragment('monthly', ['rows' => $rows, 'totalPaid' => self::money($totalPaid), 'totalDue' => self::money($totalDue)]);
    }

    // ------------------------------------------------------------------ SMS popup

    /** Admin (no network) may look at any payment; an LCO only at the payments of its own customers. */
    public function paymentSms(Request $request)
    {
        if (! $request->has('txnid') || ! is_scalar($request->input('txnid'))) {
            return response('');
        }
        $lco = (string) session('usertype') === '1' ? null : $this->lco();
        $sms = $this->reports->paymentSms($request->input('txnid'), $lco);
        if (! $sms) {
            return response('Sms not found');
        }
        $status = match ((int) $sms->sms_status) {
            1 => 'In Queue',
            2 => 'Send',
            default => 'Pending',
        };

        return $this->fragment('sms', ['sms' => $sms, 'status' => $status]);
    }

    // ------------------------------------------------------------------ agent-wise collection

    public function agentWiseCollectionReportData(Request $request)
    {
        $d = $this->days($request);
        if (! $d) {
            return response('');
        }
        [$from, $to] = $d;
        [$f, $t] = ReportService::window($from, $to);
        $perDay = $this->reports->agentCollectionByDay($this->lco(), (int) session('userid'), $f, $t);
        $days = [];
        for ($i = 0, $day = $from; $day <= $to; $i++, $day = $day->addDay()) {
            $count = 0;
            $amount = '0';
            $agents = [];
            foreach ($perDay[$i] ?? [] as $a) {
                $count += (int) $a['c'];
                $amount = bcadd($amount, $a['s'], 4);
                $agents[] = ['name' => ($a['agentname'] ?? '') === '' ? 'ADMIN' : $a['agentname'], 'count' => $a['c'], 'amount' => self::money($a['s'])];
            }
            $days[] = ['ymd' => $day->format('Y-m-d'), 'count' => $count, 'amount' => self::money($amount), 'agents' => $agents];
        }
        $totals = [];
        $totalCount = 0;
        $totalAmount = '0';
        foreach ($this->reports->agentCollectionTotals($this->lco(), $f, $t) as $a) {
            $totalCount += (int) $a->c;
            $totalAmount = bcadd($totalAmount, (string) ($a->s ?? '0'), 4);
            $totals[] = ['name' => ($a->agentname ?? '') === '' ? 'ADMIN' : $a->agentname, 'count' => $a->c, 'amount' => self::money($a->s ?? 0)];
        }

        return $this->fragment('agent_collection', [
            'first' => $from->format('Y-m-d'), 'last' => $to->format('Y-m-d'), 'days' => $days,
            'totals' => $totals, 'totalCount' => $totalCount, 'totalAmount' => self::money($totalAmount),
        ]);
    }

    // ------------------------------------------------------------------ expenses

    /**
     * The old report compared the DATE column `expensedate` with millisecond timestamps (never true) and read a table that no
     * longer exists (malanadsubscription), so it could not show data. Ported to what it was clearly meant to do: expenses of
     * the logged-in user per day and per head, a consolidated table for the period, and collection - expenses = profit.
     */
    public function expenseReportData(Request $request)
    {
        $from = ReportService::day($request->input('sfromdate'));
        $to = ReportService::day($request->input('stodate'));
        $uid = (int) session('userid');
        $head = (int) $request->input('header', 0);
        $monthOnly = $from === null;   // old: no from date = current month
        if ($monthOnly) {
            $from = CarbonImmutable::now('Asia/Kolkata')->startOfMonth();
            $to = $from->endOfMonth()->startOfDay();
        } elseif ($to === null || $to < $from) {
            return response('');
        }
        $q = DB::table('expenses as E')->leftJoin('expenseheader as EH', 'EH.id', '=', 'E.expenseid')->where('E.created_by', $uid)
            ->whereBetween('E.expensedate', [$from->format('Y-m-d'), $to->format('Y-m-d')]);
        if ($head > 0) {
            $q->where('E.expenseid', $head);
        }
        $perDay = (clone $q)->groupBy('E.expensedate', 'E.expenseid', 'EH.title')->orderBy('E.expensedate')->orderBy('E.expenseid')
            ->get(['E.expensedate', 'EH.title', DB::raw('COUNT(E.id) as expcount'), DB::raw('SUM(E.amount) as expamount')])->groupBy('expensedate');
        $days = [];
        for ($day = $from; $day <= $to; $day = $day->addDay()) {
            $days[] = ['ymd' => $day->format('Y-m-d'), 'rows' => $perDay->get($day->format('Y-m-d'), collect())->map(fn ($r) => (array) $r)->all()];
        }
        $heads = DB::table('expenses as E')->leftJoin('expenseheader as EH', 'EH.id', '=', 'E.expenseid')->where('E.created_by', $uid)
            ->whereBetween('E.expensedate', [$from->format('Y-m-d'), $to->format('Y-m-d')])->groupBy('E.expenseid', 'EH.title')->orderBy('E.expenseid')
            ->get(['EH.title', DB::raw('COUNT(E.id) as expcount'), DB::raw('SUM(E.amount) as expamount')])->map(fn ($r) => (array) $r)->all();
        $totalExpense = array_reduce($heads, fn ($c, $r) => bcadd($c, (string) $r['expamount'], 2), '0');

        $lcos = $this->lco() !== '' ? [$this->lco()] : array_column($this->menus->networkList(), 'lcoCode');
        [$f, $t] = ReportService::window($from, $to);
        $collection = $lcos === [] ? '0' : (string) (DB::table('subscriberpayments as sp')->join('m_customer as c', 'c.id', '=', 'sp.subscriberid')
            ->whereIn('c.lcoCode', $lcos)->where('sp.timestamp', '>', $f)->where('sp.timestamp', '<', $t)->sum('sp.paidamount') ?? 0);

        return $this->fragment('expense', [
            'first' => $from->format('Y-m-d'), 'last' => $to->format('Y-m-d'), 'days' => $days, 'heads' => $heads,
            'consolidatedTitle' => $monthOnly ? $from->format('M Y') : $from->format('Y-m-d').' to '.$to->format('Y-m-d'),
            'totalExpense' => $totalExpense, 'collection' => $collection, 'profit' => bcsub($collection, $totalExpense, 2),
        ]);
    }

    // ------------------------------------------------------------------ discounts

    /** (The old report read the `subscriber` table, which does not exist any more: ported onto m_customer.) */
    public function discountReportData(Request $request)
    {
        $d = $this->days($request);
        if (! $d) {
            return response('');
        }
        [$from, $to] = $d;
        [$f, $t] = ReportService::window($from, $to);
        $byDay = [];
        $total = '0';
        foreach ($this->reports->discounts($this->lco(), $f, $t) as $r) {
            $byDay[CarbonImmutable::createFromTimestamp((int) round(((float) $r->timestamp) / 1000), 'Asia/Kolkata')->format('Y-m-d')][] = $r;
            $total = bcadd($total, number_format((float) $r->discount, 2, '.', ''), 2);
        }
        $days = [];
        for ($day = $from; $day <= $to; $day = $day->addDay()) {
            $days[] = ['ymd' => $day->format('Y-m-d'), 'rows' => $byDay[$day->format('Y-m-d')] ?? []];
        }

        return $this->fragment('discount', ['first' => $from->format('Y-m-d'), 'last' => $to->format('Y-m-d'), 'days' => $days, 'total' => $total]);
    }
}
