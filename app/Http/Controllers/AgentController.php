<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Agent panel (usertype 3, enforced by the legacy.session:3 middleware): dashboard, payment screen (the AJAX endpoints are the
 * shared Transaction ones), and the agent's own daily report. Port of the CI Agent controller.
 */
class AgentController extends Controller
{
    /** The agent row of the logged-in agent (must still be active). */
    private function agent(): ?object
    {
        return DB::table('agent')->where('id', session('userid'))->where('status', 1)->where('emptype', 3)->first(['id', 'created_by']);
    }

    public function index()
    {
        return view('agent.dashboard');
    }

    public function payment()
    {
        $agent = $this->agent();
        $subscriber = [];
        $network = null;
        if ($agent) {
            $q = DB::table('m_customer')->whereIn('lcoCode', DB::table('m_network')->select('lcoCode')->where('created_by', $agent->created_by))
                ->where('status', '!=', 2);
            if (DB::table('agent_areas')->where('agent_id', $agent->id)->exists()) {
                $q->whereIn('subarea_id', DB::table('agent_areas')->select('agent_area_id')->where('agent_id', $agent->id));
            }
            $subscriber = $q->orderBy('customer_name')->get(['id', 'customer_name', 'mobile', 'lco_customer_id', 'newid'])->map(fn ($r) => (array) $r)->all();
            $network = DB::table('m_network')->where('created_by', $agent->created_by)->orderBy('id')->first(['network_name', 'lcoCode', 'address', 'phone']);
        }

        return view('admin.payment.make_payment', [
            'subscriber' => $subscriber,
            'miscellaneousheads_list' => DB::table('m_dropdown')->where('type', 3)->get(['id', 'name'])->map(fn ($r) => (array) $r)->all(),
            'header' => [
                'network_name' => $network->network_name ?? '', 'lcoCode' => $network->lcoCode ?? '', 'address' => $network->address ?? '',
                'phone' => $network->phone ?? '', 'report_name' => 'Payment history',
            ],
        ]);
    }

    /** Old "change area" switcher (its menu was commented out in the layout): sets the session area when the agent really has it. */
    public function changeArea(string $area)
    {
        $agent = $this->agent();
        session()->forget(['areaname', 'areaid']);
        if ($agent && ctype_digit($area)) {
            $row = DB::table('agent_areas')->join('m_area', 'm_area.id', '=', 'agent_areas.agent_area_id')
                ->where('agent_areas.agent_id', $agent->id)->where('m_area.id', $area)->first(['m_area.areaname', 'm_area.id']);
            if ($row) {
                session(['areaname' => $row->areaname, 'areaid' => $row->id]);
            }
        }

        return redirect('agent');
    }

    public function dateWiseReport()
    {
        return view('agent.report.datewisereport');
    }

    public function dateWiseReportData(Request $request)
    {
        $from = ReportService::day($request->input('sfromdate'));
        $to = ReportService::day($request->input('stodate'));
        if (! $from || ! $to || $from > $to) {
            return response('');
        }
        [$f, $t] = ReportService::window($from, $to);
        $byDay = app(ReportService::class)->agentDailyTotals((int) session('userid'), $f, $t);
        $days = [];
        $tot = ['cc' => 0, 'ca' => '0', 'dc' => 0, 'da' => '0'];
        for ($i = 0, $day = $from; $day <= $to; $i++, $day = $day->addDay()) {
            $r = $byDay[$i] ?? ['cc' => '0', 'ca' => '0', 'dc' => '0', 'da' => '0'];
            $tot['cc'] += (int) $r['cc'];
            $tot['ca'] = bcadd($tot['ca'], $r['ca'], 4);
            $tot['dc'] += (int) $r['dc'];
            $tot['da'] = bcadd($tot['da'], $r['da'], 4);
            $days[] = ['ymd' => $day->format('Y-m-d'), 'dmy' => $day->format('d-m-Y'), 'cc' => $r['cc'], 'ca' => number_format((float) $r['ca'], 2), 'dc' => $r['dc'], 'da' => number_format((float) $r['da'], 2)];
        }

        return response(view('admin.report.data.date_wise', [
            'first' => $from->format('Y-m-d'), 'last' => $to->format('Y-m-d'), 'days' => $days,
            'total' => ['cc' => $tot['cc'], 'ca' => number_format((float) $tot['ca'], 2), 'dc' => $tot['dc'], 'da' => number_format((float) $tot['da'], 2)],
        ])->render());
    }

    public function dailyReportData(Request $request)
    {
        $from = ReportService::day($request->input('sfromdate'));
        $to = ReportService::day($request->input('stodate'));
        if (! $from || ! $to || $from > $to) {
            return response('');
        }
        [$f, $t] = ReportService::window($from, $to);
        $totals = ['due' => '0', 'paid' => '0', 'discount' => '0', 'outstanding' => '0'];
        $rows = [];
        foreach (app(ReportService::class)->agentPayments((int) session('userid'), $f, $t) as $r) {
            $totals['due'] = bcadd($totals['due'], (string) $r->dueamount, 4);
            $totals['paid'] = bcadd($totals['paid'], (string) $r->paidamount, 4);
            $totals['discount'] = bcadd($totals['discount'], (string) $r->discount, 4);
            $totals['outstanding'] = bcadd($totals['outstanding'], (string) $r->dbafterdiscount, 4);
            $rows[] = (object) (['date' => ReportService::formatDate($r->timestamp)] + (array) $r);
        }

        return response(view('agent.data.payments', ['rows' => $rows, 'count' => count($rows),
            'totals' => array_map(fn ($v) => number_format((float) $v, 2), $totals)])->render());
    }
}
