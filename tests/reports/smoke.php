<?php

/**
 * Smoke + security checks of the Reporting module on the NEW app (in-process, read-only):
 * every page renders, the expense/discount/network fragments work (the old versions could not run on this schema),
 * and cross-network access is refused.   php tests/reports/smoke.php
 */

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

$user = DB::selectOne("select u.username, u.password, n.lcoCode from m_user u join m_network n on n.created_by = u.id where u.type = 2 and u.status = 1 and n.lcoCode = 'MLNDKBDY1' limit 1");
$other = DB::selectOne('select sp.id from subscriberpayments sp join m_customer c on c.id = sp.subscriberid where c.lcoCode <> ? order by sp.id desc limit 1', [$user->lcoCode]);
$cookies = [];
app('session.store')->flush();   // the in-process kernel reuses one session store; start the logged-out request clean
$call = function (string $method, string $path, array $post = []) use ($kernel, &$cookies): array {
    $req = Request::create('/'.$path, $method, $post, $cookies, [], ['REMOTE_ADDR' => '127.0.0.1']);
    $res = $kernel->handle($req);
    foreach ($res->headers->getCookies() as $c) {
        $cookies[$c->getName()] = $c->getValue();
    }
    $kernel->terminate($req, $res);

    return [$res->getStatusCode(), (string) $res->getContent(), $res];
};
[, $html] = $call('GET', 'login');
preg_match('/name="_token"[^>]*value="([^"]+)"|value="([^"]+)"[^>]*name="_token"/', $html, $m);
$call('POST', 'login/process_login', ['_token' => $m[1] ?: $m[2], 'username' => $user->username, 'password' => $user->password]);
[, $page] = $call('GET', 'report/date_wise_report');
preg_match('/csrf-token"\s+content="([^"]+)"/', $page, $m);
$t = $m[1];

$fail = 0;
$check = function (string $name, bool $ok, string $extra = '') use (&$fail) {
    echo ($ok ? 'PASS ' : 'FAIL ').$name.($extra ? "  $extra" : '')."\n";
    $ok || $fail++;
};

foreach (['agent_report', 'date_wise_report', 'month_wise_report', 'miscellaneous_report', 'agent_wise_collection_report', 'expense_report', 'discount_report'] as $p) {
    [$st, $body] = $call('GET', 'report/'.$p);
    $check("page report/$p", $st === 200 && str_contains($body, 'container') || str_contains($body, 'mischead'), "status $st");
    $check("page report/$p capitalised URL", $call('GET', 'Report/'.$p)[0] === 200);
}
$r = ['sfromdate' => '2026-10-01', 'stodate' => '2026-11-10', '_token' => $t];
[$st, $b] = $call('POST', 'report/expense_report_data', $r + ['header' => 0]);
$check('expense_report_data renders', $st === 200 && str_contains($b, 'Consolidated Report') && str_contains($b, 'Profit'), "status $st");
[$st, $b] = $call('POST', 'report/expense_report_data', ['sfromdate' => '2023-10-01', 'stodate' => '2023-10-31', 'header' => 0, '_token' => $t]);
$check('expense_report_data (period with data, user may have none)', $st === 200 && str_contains($b, 'Displaying results'), 'status '.$st);
[$st, $b] = $call('POST', 'report/expense_report_data', ['sfromdate' => '', 'stodate' => '', 'header' => 0, '_token' => $t]);
$check('expense_report_data empty dates = this month', $st === 200 && str_contains($b, 'Consolidated Report ('.date('M Y').')'), "status $st");
[$st, $b] = $call('POST', 'report/discount_report_data', $r);
$check('discount_report_data renders', $st === 200 && str_contains($b, 'Displaying Discounts applied'), "status $st");
[$st, $b] = $call('POST', 'report/network_daily_report_data', $r + ['network' => $user->lcoCode]);
$check('network_daily_report_data own network', $st === 200 && str_contains($b, 'example2'), "status $st");
[$st, $b] = $call('POST', 'report/network_daily_report_data', $r + ['network' => 'SOMEONE_ELSES_LCO']);
$check('network_daily_report_data foreign network refused', $st === 200 && trim($b) === '');
[$st, $b] = $call('POST', 'report/payment_sms', ['txnid' => $other->id, '_token' => $t]);
$check("payment_sms of another LCO's payment refused", trim($b) === 'Sms not found', trim(substr($b, 0, 40)));
foreach (['not-a-date', '2026-13-45', '', '31-31-2026'] as $bad) {
    [$st, $b] = $call('POST', 'report/date_wise_report_data', ['sfromdate' => $bad, 'stodate' => $bad, '_token' => $t]);
    $check('bad date "'.$bad.'" gives empty body, no error', $st === 200 && trim($b) === '');
}
[$st] = $call('POST', 'report/date_wise_report_data', ['sfromdate' => '2026-11-01', 'stodate' => '2026-11-02']);
$check('POST without CSRF token is rejected (419)', $st === 419, "status $st");
$cookies = [];
app('session.store')->flush();   // the in-process kernel reuses one session store; start the logged-out request clean
[$st, , $res] = $call('GET', 'report/date_wise_report');
$check('logged-out user is redirected to logout', $st === 302 && str_contains((string) $res->headers->get('Location'), 'login/logout'), "status $st ".$res->headers->get('Location'));

echo $fail ? "\n$fail FAILED\n" : "\nall passed\n";
exit($fail ? 1 : 0);
