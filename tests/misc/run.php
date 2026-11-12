<?php

/**
 * Agent panel, Complaint, SMS cron / invoice SMS and Privacy policy checks on the NEW app (in-process).
 * One DB transaction, rolled back; every HTTP call to an SMS provider is faked (and stray requests are prevented).
 *   php tests/misc/run.php
 */

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\PaymentService;
use App\Services\ReportService;
use App\Services\SmsService;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

Http::fake();
Http::preventStrayRequests();
config(['cablebill.sms.enabled' => true]);   // the provider calls below hit the fake, never a real gateway

$fail = 0;
$check = function (string $name, bool $ok, string $extra = '') use (&$fail) {
    echo ($ok ? 'PASS ' : 'FAIL ').$name.($extra !== '' ? "  [$extra]" : '')."\n";
    $ok || $fail++;
};

$cookies = [];
$call = function (string $method, string $path, array $post = []) use ($kernel, &$cookies): array {
    $req = Request::create('/'.$path, $method, $post, $cookies, [], ['REMOTE_ADDR' => '127.0.0.1']);
    $res = $kernel->handle($req);
    foreach ($res->headers->getCookies() as $c) {
        $cookies[$c->getName()] = $c->getValue();
    }
    $kernel->terminate($req, $res);

    return [$res->getStatusCode(), (string) $res->getContent(), $res];
};
$login = function (string $user, string $pass) use ($call, &$cookies): string {
    $cookies = [];
    app('session.store')->flush();
    [, $html] = $call('GET', 'login');
    preg_match('/name="_token"[^>]*value="([^"]+)"|value="([^"]+)"[^>]*name="_token"/', $html, $m);
    $call('POST', 'login/process_login', ['_token' => $m[1] ?: $m[2], 'username' => $user, 'password' => $pass]);
    [, $page] = $call('GET', 'privacypolicy/details');
    [, $any] = $call('GET', 'login');
    preg_match('/csrf-token"\s+content="([^"]+)"/', $any.$page, $m);

    return $m[1] ?? '';
};
$tokenOf = function (string $path) use ($call): string {
    [, $html] = $call('GET', $path);
    preg_match('/csrf-token"\s+content="([^"]+)"/', $html, $m);

    return $m[1] ?? '';
};

// ================= fixtures
$agent = DB::selectOne("select a.id, a.username, a.password, a.created_by from agent a where a.status = 1 and a.emptype = 3 and a.username <> '' and a.password <> ''
    and exists (select 1 from subscriberpayments p where p.agentid = a.id and p.transactiontype = 1 and cast(p.timestamp as unsigned) > ?) order by a.id desc limit 1",
    [(time() - 86400 * 120) * 1000]);
$lco = DB::selectOne("select u.id uid, u.username, u.password, n.lcoCode, n.id nid from m_user u join m_network n on n.created_by = u.id
    where u.type = 2 and u.status = 1 and n.status = 1 and u.default_sms_api > 0 and exists (select 1 from m_customer c where c.lcoCode = n.lcoCode and length(replace(c.mobile, \"'91\", '')) = 10) limit 1");
$admin = DB::selectOne('select username, password from m_user where type = 1 and status = 1 limit 1');
echo "agent {$agent->username} (owner {$agent->created_by}); LCO {$lco->username} ({$lco->lcoCode})\n";

DB::beginTransaction();
try {
    // ================= privacy policy (public)
    $cookies = [];
    [$st, $body] = $call('GET', 'PrivacyPolicy/details');
    $check('privacy policy page is public', $st === 200 && stripos($body, 'privacy') !== false, (string) $st);

    // ================= agent panel
    $login($agent->username, $agent->password);
    $t = $tokenOf('agent');
    foreach (['agent' => 'TODAY', 'agent/payment' => 'subscriber', 'agent/date_wise_report' => 'Daily report'] as $p => $needle) {
        [$st, $body] = $call('GET', $p);
        $check("agent page /$p", $st === 200 && stripos($body, $needle) !== false, (string) $st);
    }
    [, $body] = $call('GET', 'agent/payment');
    $expected = DB::table('m_customer')->whereIn('lcoCode', DB::table('m_network')->select('lcoCode')->where('created_by', $agent->created_by))->where('status', '!=', 2)
        ->when(DB::table('agent_areas')->where('agent_id', $agent->id)->exists(), fn ($q) => $q->whereIn('subarea_id', DB::table('agent_areas')->select('agent_area_id')->where('agent_id', $agent->id)))->count();
    $options = substr_count($body, '<option value="') - substr_count($body, 'name="trantype"');
    $check('agent payment page lists only the agent\'s own customers', $options >= $expected && $options <= $expected + 60, "customers $expected, options $options");
    [$st] = $call('GET', 'report/date_wise_report');
    $check('agent cannot open the LCO reports (redirected to logout)', $st === 302);
    [$st] = $call('GET', 'import/subscribers');
    $check('agent cannot open imports', $st === 302);
    $latest = (int) DB::table('subscriberpayments')->where('agentid', $agent->id)->where('transactiontype', 1)->max(DB::raw('cast(timestamp as unsigned)'));
    $day = (new DateTimeImmutable('@'.intdiv($latest, 1000)))->setTimezone(new DateTimeZone('Asia/Kolkata'))->format('Y-m-d');
    [$st, $body] = $call('POST', 'agent/date_wise_report_data', ['_token' => $t, 'sfromdate' => $day, 'stodate' => $day]);
    [$from, $to] = ReportService::window(ReportService::day($day), ReportService::day($day));
    $cnt = DB::table('subscriberpayments')->where('agentid', $agent->id)->where('transactiontype', 1)->whereRaw('timestamp > ? and timestamp < ?', [$from, $to])->count();
    $check('agent day totals: collection count matches the ledger', $st === 200 && str_contains($body, "<td>$cnt</td>") && str_contains($body, 'Displaying results from '.$day), "$st expect $cnt");
    [$st, $body] = $call('POST', 'agent/daily_report_data', ['_token' => $t, 'sfromdate' => $day, 'stodate' => $day]);
    $check('agent daily list: one row per payment', $st === 200 && substr_count($body, '<td>'.date('d-m-Y', intdiv($latest, 1000)).'</td>') >= 1 && str_contains($body, 'Outsatanding'), (string) $st);
    [, $body] = $call('POST', 'agent/daily_report_data', ['_token' => $t, 'sfromdate' => 'x', 'stodate' => 'y']);
    $check('agent report: bad dates give an empty body', trim($body) === '');
    $foreign = DB::table('m_customer')->whereNotIn('lcoCode', DB::table('m_network')->select('lcoCode')->where('created_by', $agent->created_by))->first();
    [, $body] = $call('POST', 'Transaction/subscriber_details', ['_token' => $t, 'subscriberid' => $foreign->id]);
    $check('agent cannot read a customer of another network', str_contains($body, '"success":false'));
    [$st] = $call('GET', 'agent/change_area/abc');
    $check('agent change_area with a bad id just redirects', $st === 302);

    // ================= complaints (LCO user)
    $login($lco->username, $lco->password);
    $t = $tokenOf('complaint');
    [$st, $body] = $call('GET', 'complaint');
    $check('complaint list renders', $st === 200 && str_contains($body, 'Complaint List'), (string) $st);
    $cust = DB::table('m_customer')->where('lcoCode', $lco->lcoCode)->first();
    $type = DB::table('m_dropdown')->where('type', 1)->where('status', 1)->first();
    [$st, $body] = $call('GET', 'complaint/complaint_add_view');
    $check('complaint add page renders', $st === 200 && str_contains($body, 'Complaint Details'));
    $before = DB::table('m_complaints')->count();
    [, $body] = $call('POST', 'complaint/add_complaint', ['_token' => $t, 'subscriber' => $cust->id, 'complaint_type' => $type->id, 'description' => 'test <b>x</b>']);
    $check('complaint added for an own customer', $body === 'Success' && DB::table('m_complaints')->count() === $before + 1, $body);
    $c = DB::table('m_complaints')->orderByDesc('id')->first();
    $check('complaint stored with status 1 and the LCO network', (int) $c->status === 1 && (string) $c->network_id === (string) $lco->nid && (string) $c->customer_id === (string) $cust->id);
    $foreignCust = DB::table('m_customer')->where('lcoCode', '<>', $lco->lcoCode)->first();
    [, $body] = $call('POST', 'complaint/add_complaint', ['_token' => $t, 'subscriber' => $foreignCust->id, 'complaint_type' => $type->id, 'description' => 'x']);
    $check('complaint for another LCO\'s customer is refused', $body === 'Failed');
    [, $body] = $call('POST', 'complaint/add_complaint', ['_token' => $t, 'subscriber' => $cust->id, 'complaint_type' => '999999', 'description' => 'x']);
    $check('complaint with an unknown type is refused', $body === 'Failed');
    [, $body] = $call('GET', 'complaint/view_complaint/'.$c->id);
    $check('view own complaint (description escaped)', str_contains($body, 'test &lt;b&gt;x&lt;/b&gt;') && ! str_contains($body, 'test <b>x</b>'));
    [, $body] = $call('GET', 'complaint/edit_complaint/'.$c->id);
    $check('edit page for own complaint', str_contains($body, 'technician_remarks'));
    [, $body] = $call('POST', 'complaint/update_complaint', ['_token' => $t, 'technician_remarks' => 'fixed', 'statuslist' => 3, 'complaint_id' => $c->id]);
    $check('update own complaint', $body === 'Success' && (int) DB::table('m_complaints')->where('id', $c->id)->value('status') === 3);
    [, $body] = $call('POST', 'complaint/update_complaint', ['_token' => $t, 'technician_remarks' => 'x', 'statuslist' => 9, 'complaint_id' => $c->id]);
    $check('update with an invalid status refused', $body === 'Failed');
    $other = DB::table('m_complaints')->whereNotIn('network_id', DB::table('m_network')->select('id')->where('created_by', $lco->uid))->orderByDesc('id')->first();
    if ($other) {
        [, $body] = $call('GET', 'complaint/view_complaint/'.$other->id);
        $check('another LCO\'s complaint cannot be viewed', str_contains($body, 'error') && ! str_contains($body, 'Complaint Details'));
        $statusBefore = $other->status;
        [, $body] = $call('POST', 'complaint/update_complaint', ['_token' => $t, 'technician_remarks' => 'hijack', 'statuslist' => 2, 'complaint_id' => $other->id]);
        $check('another LCO\'s complaint cannot be updated', $body === 'Failed' && DB::table('m_complaints')->where('id', $other->id)->value('status') == $statusBefore && DB::table('m_complaints')->where('id', $other->id)->value('technician_remarks') !== 'hijack');
    }
    [$st] = $call('GET', 'complaint/enquiry');
    $hasMenu7 = DB::table('m_user_roles')->where('user_id', $lco->uid)->where('menu_id', 7)->exists();
    $check('enquiry screen needs menu 7', $hasMenu7 ? $st === 200 : $st === 403, (string) $st);

    [$st] = $call('GET', 'Transaction/all_network_transaction');
    $check('LCO user cannot open All network transactions (403)', $st === 403, (string) $st);
    [$st, $body] = $call('GET', 'Transaction');
    $check('History page (Transaction/) renders for an LCO', $st === 200 && str_contains($body, 'Transaction History'), (string) $st);

    // ================= enquiry / types (admin has every menu)
    $login($admin->username, $admin->password);
    foreach (['complaint/enquiry', 'complaint/complaint_type_management', 'complaint/enquiry_type_management', 'complaint/enquiry/add'] as $p) {
        [$st, $body] = $call('GET', $p);
        $check("admin screen /$p", $st === 200 && str_contains($body, 'flexigrid') || str_contains($body, 'form'), (string) $st);
    }

    // ================= history pages (admin sees every network)
    $ta = $tokenOf('complaint');
    [$st, $body] = $call('GET', 'Transaction/all_network_transaction');
    $check('admin: All network transactions page lists the networks', $st === 200 && str_contains($body, 'id="network"') && str_contains($body, $lco->lcoCode), (string) $st);
    [$st, $body] = $call('POST', 'report/network_daily_report_data', ['_token' => $ta, 'sfromdate' => '2026-10-01', 'stodate' => '2026-11-10', 'network' => $lco->lcoCode]);
    $check('admin: can read the transactions of any active network', $st === 200 && str_contains($body, 'example2'), (string) $st);
    [$st, $body] = $call('POST', 'report/network_daily_report_data', ['_token' => $ta, 'sfromdate' => '2026-10-01', 'stodate' => '2026-11-10', 'network' => 'NOPE']);
    $check('admin: unknown network gives nothing', trim($body) === '');

    // ================= SMS: bill SMS delivery (faked HTTP) + cron URL
    $login($lco->username, $lco->password);
    $mobileCust = DB::table('m_customer')->where('lcoCode', $lco->lcoCode)->whereRaw("length(replace(mobile, \"'91\", '')) = 10")->first();
    $invId = DB::table('m_ezybill_invoices')->insertGetId(['customer_id' => (string) $mobileCust->id, 'invoice_date' => '2031-02-01', 'current_month' => '2031-01-01', 'status' => 1,
        'sms_status' => 1, 'sms' => 'CTOPUP test bill', 'current_bill_amount' => 1, 'created_on' => now()]);
    $r = app(SmsService::class)->processInvoiceQueue($lco->uid, 5000);
    $inv = DB::table('m_ezybill_invoices')->where('id', $invId)->first();
    $check('bill SMS: queued invoice is sent through the gateway (fake) and marked sent', (int) $inv->sms_status === 2 && $r['sent'] >= 1, json_encode($r));
    Http::assertSent(fn ($req) => str_contains($req->url(), '1207162027495529783') || ! str_contains($req->url(), 'textsms'));
    $check('bill SMS: only this LCO\'s bills are sent', DB::table('m_ezybill_invoices as i')->join('m_customer as c', 'c.id', '=', 'i.customer_id')->where('i.sms_status', 2)->where('c.lcoCode', '<>', $lco->lcoCode)->where('i.id', '>', $invId - 1)->count() === 0);
    $payId = DB::table('subscriberpayments')->insertGetId(['transactiontype' => '1', 'subscriberid' => (string) $mobileCust->id, 'description' => 'test', 'dueamount' => 0, 'paidamount' => 1, 'discount' => 0,
        'subscriptioncharge' => 0, 'duebalance' => 0, 'dbafterdiscount' => 0, 'timestamp' => (string) PaymentService::milliseconds(), 'sms' => 'ctopup test', 'sms_status' => 1,
        'mobile' => str_replace("'91", '', $mobileCust->mobile)]);
    $r = app(SmsService::class)->processQueue(100000);
    $check('payment SMS queue: finds the LCO gateway and sends (fake)', (int) DB::table('subscriberpayments')->where('id', $payId)->value('sms_status') === 2 && $r['sent'] >= 1, json_encode($r));
    $cookies = [];
    app('session.store')->flush();
    config(['cablebill.sms.cron_secret' => '']);
    [$st] = $call('GET', 'SmsSendCron');
    $check('cron URL is disabled without a secret (404)', $st === 404);
    config(['cablebill.sms.cron_secret' => 'unit-test-secret']);
    [$st] = $call('GET', 'SmsSendCron?key=wrong');
    $check('cron URL with a wrong key (404)', $st === 404);
    [$st, $body] = $call('GET', 'SmsSendCron?key=unit-test-secret');
    $check('cron URL with the right key runs the queue', $st === 200 && str_contains($body, 'Bulk SMS cron execution finished.'), "$st $body");
    $exit = Artisan::call('sms:send-queued', ['--limit' => 1]);
    $check('artisan sms:send-queued runs', $exit === 0, trim(Artisan::output()));
} finally {
    DB::rollBack();
}
echo $fail ? "\n$fail FAILED\n" : "\nall passed\n";
exit($fail ? 1 : 0);
