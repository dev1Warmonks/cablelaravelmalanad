<?php

/**
 * Report parity check: the same logged-in requests are sent to the OLD CodeIgniter app (copy, clone database, php -S :8099) and to
 * the NEW Laravel app (in-process); the visible text of the HTML (tags and <script> removed, whitespace collapsed) is compared.
 *   php tests/reports/compare.php [old_base_url]
 * Read-only: reports never write. Compares row sets, so ordering inside a day is compared as a sorted list where the old SQL had no ORDER BY.
 */

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

$oldBase = rtrim($argv[1] ?? 'http://127.0.0.1:8099', '/');
$small = ($argv[2] ?? '') === 'small';   // use the LCO with the fewest customers (the old monthly report needs one query per customer and times out on big LCOs)
$only = $argv[3] ?? null;

// ---- pick an LCO user with payments in the copy
$lcoRow = DB::selectOne('select c.lcoCode, max(cast(sp.timestamp as unsigned)) t from (select id, subscriberid, timestamp from subscriberpayments order by id desc limit 20000) sp
    join m_customer c on c.id = sp.subscriberid join m_network n on n.lcoCode = c.lcoCode join m_user u on u.id = n.created_by and u.type = 2 and u.status = 1
    group by c.lcoCode order by '.($small ? '(select count(*) from m_customer x where x.lcoCode = c.lcoCode) asc' : 'count(*) desc').' limit 1');
$user = DB::selectOne('select u.id, u.username, u.password, n.lcoCode from m_user u join m_network n on n.created_by = u.id where u.type = 2 and u.status = 1 and n.lcoCode = ? limit 1', [$lcoRow->lcoCode]);
$lastTs = (int) $lcoRow->t;
$last = (new DateTimeImmutable('@'.intdiv($lastTs, 1000)))->setTimezone(new DateTimeZone('Asia/Kolkata'));
$to = $last->format('Y-m-d');
$from = $last->modify('-9 days')->format('Y-m-d');
$oneDay = $last->format('d-m-Y');
$agent = DB::selectOne('select agentid a from subscriberpayments sp join m_customer c on c.id = sp.subscriberid where c.lcoCode = ? and sp.agentid <> 0 and cast(sp.timestamp as unsigned) > ? limit 1', [$user->lcoCode, $lastTs - 86400000 * 9]);
$pay = DB::selectOne('select sp.id from subscriberpayments sp join m_customer c on c.id = sp.subscriberid where c.lcoCode = ? and sp.sms_status > 0 order by sp.id desc limit 1', [$user->lcoCode]);
$payAny = DB::selectOne('select sp.id from subscriberpayments sp join m_customer c on c.id = sp.subscriberid where c.lcoCode = ? order by sp.id desc limit 1', [$user->lcoCode]);
echo "user {$user->username} lco {$user->lcoCode} range $from .. $to\n";

// ---- old client (curl + cookie jar)
$jar = tempnam(sys_get_temp_dir(), 'old');
$oldCall = function (string $method, string $path, array $post = []) use ($oldBase, $jar): string {
    $ch = curl_init($oldBase.'/'.$path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 280, CURLOPT_FOLLOWLOCATION => true]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $r = (string) curl_exec($ch);
    curl_close($ch);

    return $r;
};
$oldCall('POST', 'login/process_login', ['username' => $user->username, 'password' => $user->password]);

// ---- new client (in-process, cookies + CSRF carried by hand)
$cookies = [];
$newCall = function (string $method, string $path, array $post = []) use ($kernel, &$cookies): array {
    $req = Request::create('/'.$path, $method, $post, $cookies, [], ['REMOTE_ADDR' => '127.0.0.1']);
    $res = $kernel->handle($req);
    foreach ($res->headers->getCookies() as $c) {
        $cookies[$c->getName()] = $c->getValue();
    }
    $kernel->terminate($req, $res);

    return [$res->getStatusCode(), $res->getContent()];
};
[, $loginHtml] = $newCall('GET', 'login');
preg_match('/name="_token"\s+value="([^"]+)"|name="_token"[^>]*value="([^"]+)"|value="([^"]+)"\s+name="_token"/', $loginHtml, $m);
$token = $m[1] ?: ($m[2] ?: ($m[3] ?? ''));
[$st] = $newCall('POST', 'login/process_login', ['_token' => $token, 'username' => $user->username, 'password' => $user->password]);
echo "new login status $st\n";
$csrf = function () use ($newCall) {
    static $t;
    [, $html] = $newCall('GET', 'report/date_wise_report');
    preg_match('/csrf-token"\s+content="([^"]+)"/', $html, $m);

    return $m[1] ?? '';
};
$t = $csrf();

function text(string $html): string
{
    $html = preg_replace('#<script\b.*?</script>#is', '', $html);
    $html = preg_replace('/\s+/', ' ', $html);
    $html = preg_replace('#<(td|th)\b[^>]*>#i', '|', $html);
    $html = preg_replace('#</tr>#i', "\n", $html);
    $html = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5);
    $html = preg_replace('/[ \t]*\|[ \t]*/', '|', $html);
    $lines = array_map(fn ($l) => trim(preg_replace('/\s+/', ' ', $l)), explode("\n", $html));

    return implode("\n", array_values(array_filter($lines, fn ($l) => $l !== '')));
}

$cases = [
    ['date_wise_report_data', ['sfromdate' => $from, 'stodate' => $to], false],
    ['date_wise_report_data', ['sfromdate' => $to, 'stodate' => $to], false],
    ['daily_report_data', ['sfromdate' => $oneDay, 'stodate' => $oneDay], false],
    ['daily_report_data', ['sfromdate' => $from, 'stodate' => $to], false],
    ['agent_report_data', ['sfromdate' => $from, 'stodate' => $to, 'agentid' => -1], false],
    ['agent_report_data', ['sfromdate' => $from, 'stodate' => $to, 'agentid' => 0], false],
    ['agent_report_data', ['sfromdate' => $from, 'stodate' => $to, 'agentid' => $agent->a ?? 1], false],
    ['monthly_report_data', ['sfromdate' => $from, 'stodate' => $to, 'paidstat' => -1], true],
    ['monthly_report_data', ['sfromdate' => $from, 'stodate' => $to, 'paidstat' => 1], true],
    ['monthly_report_data', ['sfromdate' => $from, 'stodate' => $to, 'paidstat' => 2], true],
    ['agent_wise_collection_report_data', ['sfromdate' => $from, 'stodate' => $to], false],
    ['payment_sms', ['txnid' => $pay->id ?? ($payAny->id ?? 0)], false],
    ['payment_sms', ['txnid' => 999999999], false],
];
$pass = $fail = 0;
foreach ($cases as [$ep, $post, $sortRows]) {
    if ($only !== null && ! str_contains($ep, $only)) {
        continue;
    }
    $t0 = microtime(true);
    $old = text($oldCall('POST', 'report/'.$ep, $post));
    $t1 = microtime(true);
    [$st, $body] = $newCall('POST', 'report/'.$ep, $post + ['_token' => $t]);
    $t2 = microtime(true);
    $new = text($body);
    if ($sortRows) {
        $o = explode("\n", $old);
        $n = explode("\n", $new);
        sort($o);
        sort($n);
        $old = implode("\n", $o);
        $new = implode("\n", $n);
    }
    $ok = $old === $new && $st === 200;
    $ok ? $pass++ : $fail++;
    printf("%s %-36s %s old %.1fs new %.1fs  (%d lines)\n", $ok ? 'PASS' : 'FAIL', $ep, json_encode($post), $t1 - $t0, $t2 - $t1, substr_count($new, "\n") + 1);
    if (! $ok) {
        $ol = explode("\n", $old);
        $nl = explode("\n", $new);
        $shown = 0;
        foreach (array_unique(array_merge(array_keys($ol), array_keys($nl))) as $i) {
            if (($ol[$i] ?? null) !== ($nl[$i] ?? null) && $shown++ < 4) {
                echo "   line $i\n     old: ".substr($ol[$i] ?? '<none>', 0, 200)."\n     new: ".substr($nl[$i] ?? '<none>', 0, 200)."\n";
            }
        }
        echo '   (new status '.$st.', old '.count($ol).' lines, new '.count($nl)." lines)\n";
    }
}
echo "\n$pass passed, $fail failed\n";
