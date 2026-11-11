<?php

/**
 * Records the OLD CodeIgniter AgentApi's real responses.
 *   php tests/agentapi/record_old.php [base_url]      default http://127.0.0.1:8099
 *
 * The old app must be a COPY pointing at a CLONE database (never production, never the working copy), with every SMS gateway
 * switched off (sms_apis.status = 0): add_payment would otherwise queue and send real SMS to real customer numbers.
 * Output: storage/app/agentapi_recordings/old/<case>.json  {request, status, content_type, body}
 */

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$base = rtrim($argv[1] ?? 'http://127.0.0.1:8099', '/');
$dir = storage_path('app/agentapi_recordings/old');
@mkdir($dir, 0775, true);
$only = $argv[2] ?? null;   // optional case-name prefix: re-record just those cases
foreach (glob($dir.'/'.($only ?? '').'*.json') as $f) {
    unlink($f);
}

$cases = (require __DIR__.'/cases.php')();
$n = 0;
foreach ($cases as $c) {
    if ($only !== null && ! str_starts_with($c['name'], $only)) {
        continue;
    }
    $raw = is_string($c['body']) ? $c['body'] : json_encode($c['body']);
    $ch = curl_init("$base/AgentApi/{$c['path']}");
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $raw, CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60, CURLOPT_HEADER => true]);
    $resp = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    $body = substr((string) $resp, $info['header_size']);
    $headers = substr((string) $resp, 0, $info['header_size']);
    preg_match('/^Content-Type:\s*([^\r\n]+)/mi', $headers, $m);
    file_put_contents("$dir/{$c['name']}.json", json_encode([
        'request' => ['path' => $c['path'], 'body' => $raw], 'status' => $info['http_code'], 'content_type' => $m[1] ?? '', 'body' => str_starts_with($body, '%PDF') ? base64_encode($body) : $body,
        'is_pdf' => str_starts_with($body, '%PDF'),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    $n++;
    printf("%-42s HTTP %d  %s  %s\n", $c['name'], $info['http_code'], $m[1] ?? '-', str_starts_with($body, '%PDF') ? '[PDF '.strlen($body).' bytes]' : substr(preg_replace('/\s+/', ' ', $body), 0, 90));
}
echo "recorded $n responses into $dir\n";
