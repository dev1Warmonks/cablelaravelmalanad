<?php

/**
 * Runs the AgentApi parity cases against the NEW Laravel app (in-process, through the HTTP kernel) and compares each
 * response with the recording of the old app.   php tests/agentapi/run_new.php
 *
 * Reads run first. Write cases run inside one DB transaction that is ALWAYS rolled back (subscriberpayments, subscriberbalance and
 * m_complaints are InnoDB), so the working copy is never changed. SMS is forced off and the gateway is never contacted.
 * JSON must match byte for byte; PDFs are compared by the text they contain (FPDF stamps a creation time).
 */
putenv('SMS_SEND_ENABLED=false');
$_ENV['SMS_SEND_ENABLED'] = 'false';
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['cablebill.sms.enabled' => false]);

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

Http::preventStrayRequests();
RateLimiter::clear('agentapi-login:127.0.0.1');

/** Rows written by the test itself carry the time of the write and a new auto-increment id: mask exactly those (today's date) before comparing. */
function mask(string $caseName, string $body): string
{
    if (! str_ends_with($caseName, '_after_writes')) {
        return $body;
    }
    $today = preg_quote(date('M-d-Y'), '/');
    $body = preg_replace('/"timestamp":"'.$today.' [^"]*"(.*?)"tran_id":"\d+"/', '"timestamp":"<now>"$1"tran_id":"<new>"', $body);

    return preg_replace('/"lastpaid":"'.$today.' [^"]*"/', '"lastpaid":"<now>"', $body);
}

function pdfText(string $pdf): string
{
    // uncompressed FPDF streams: collect the text operators (Tj) in order
    preg_match_all('/\((.*?)\) Tj/s', $pdf, $m);

    return implode('|', $m[1]);
}

$recDir = storage_path('app/agentapi_recordings/old');
$cases = (require __DIR__.'/cases.php')();
usort($cases, fn ($a, $b) => (int) $a['write'] <=> (int) $b['write']);   // stable enough: reads first, writes last (order inside each group kept below)
$ordered = array_merge(array_filter($cases, fn ($c) => ! $c['write']), array_filter($cases, fn ($c) => $c['write']));

$pass = $fail = 0;
DB::beginTransaction();
try {
    foreach ($ordered as $c) {
        $rec = json_decode(file_get_contents("$recDir/{$c['name']}.json"), true);
        $raw = is_string($c['body']) ? $c['body'] : json_encode($c['body']);
        $req = Request::create('/AgentApi/'.$c['path'], 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json', 'REMOTE_ADDR' => '127.0.0.1'], $raw);
        $res = $kernel->handle($req);
        $body = $res->getContent();
        $ok = false;
        if ($rec['is_pdf']) {
            $ok = str_starts_with($body, '%PDF') && pdfText($body) === pdfText(base64_decode($rec['body']));
        } else {
            $ok = mask($c['name'], $body) === mask($c['name'], $rec['body']) && $res->getStatusCode() === $rec['status'];
        }
        $ctOk = str_starts_with(strtolower((string) $res->headers->get('Content-Type')), strtolower(explode(';', $rec['content_type'])[0] ?: 'text/html'));
        $ok = $ok && $ctOk;
        $ok ? $pass++ : $fail++;
        printf("%s %-42s\n", $ok ? 'PASS' : 'FAIL', $c['name']);
        if (! $ok) {
            echo '   old ['.$rec['status'].' '.$rec['content_type'].'] '.substr($rec['is_pdf'] ? pdfText(base64_decode($rec['body'])) : $rec['body'], 0, 300)."\n";
            echo '   new ['.$res->getStatusCode().' '.$res->headers->get('Content-Type').'] '.substr(str_starts_with($body, '%PDF') ? pdfText($body) : $body, 0, 300)."\n";
        }
    }
} finally {
    DB::rollBack();
}
echo "\n$pass passed, $fail failed (writes rolled back)\n";
exit($fail ? 1 : 0);
