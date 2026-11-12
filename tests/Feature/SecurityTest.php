<?php

use App\Services\ImportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

// ---------------------------------------------------------------- CSRF

test('a POST without a CSRF token is rejected (419) and with the token it goes through', function () {
    loginAs(lcoUser());   // (signed in first: the login form itself needs a token once CSRF is enforced)
    $this->app->detectEnvironment(fn () => 'local');   // the framework skips CSRF checks while the environment is "testing"
    $token = session()->token();
    $this->post('complaint/add_complaint', ['subscriber' => 1, 'complaint_type' => 1, 'description' => 'x'])->assertStatus(419);
    $this->post('complaint/add_complaint', ['_token' => $token, 'subscriber' => 1, 'complaint_type' => 1, 'description' => 'x'])->assertOk();
    $this->post('Transaction/add_payment', ['subscriberid' => 1, 'paidamount' => 1, 'discount' => 0, 'comment' => '', 'trantype' => 1])->assertStatus(419);
    $this->post('login/process_login', ['username' => 'a', 'password' => 'b'])->assertStatus(419);
});

test('the mobile app API needs no CSRF token and sets no cookie', function () {
    $this->app->detectEnvironment(fn () => 'local');
    $res = $this->call('POST', 'AgentApi/verify', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['token' => 'nope']));
    expect($res->getStatusCode())->toBe(200)->and($res->headers->getCookies())->toBe([]);
});

// ---------------------------------------------------------------- headers and cookies

test('security headers are on every response', function () {
    foreach (['/', 'Login'] as $url) {
        $res = $this->get($url);
        expect($res->headers->get('X-Content-Type-Options'))->toBe('nosniff')
            ->and($res->headers->get('X-Frame-Options'))->toBe('SAMEORIGIN')
            ->and($res->headers->get('Referrer-Policy'))->not->toBeNull()
            ->and($res->headers->has('X-Powered-By'))->toBeFalse();
    }
});

test('the content security policy is present', function () {
    $csp = (string) ($this->get('Login')->headers->get('Content-Security-Policy') ?? $this->get('Login')->headers->get('Content-Security-Policy-Report-Only'));
    expect($csp)->toContain("frame-ancestors 'self'")->toContain("object-src 'none'")->toContain("base-uri 'self'");
});

test('the session cookie is HttpOnly with SameSite', function () {
    $res = $this->get('Login');
    $cookie = collect($res->headers->getCookies())->first(fn ($c) => str_contains($c->getName(), 'session'));
    expect($cookie)->not->toBeNull();
    expect($cookie->isHttpOnly())->toBeTrue()->and($cookie->getSameSite())->toBeIn(['lax', 'strict']);
});

test('debug pages never reach a visitor when APP_DEBUG is off', function () {
    config(['app.debug' => false]);
    $res = $this->get('this-page-does-not-exist-'.uniqid());
    expect($res->status())->toBe(404)->and($res->getContent())->not->toContain('vendor/laravel');
});

// ---------------------------------------------------------------- upload abuse

function importUpload(string $name, string $content, string $mime = 'text/csv', string $url = 'import/importsubscribers', string $field = 'file'): TestResponse
{
    $path = tempnam(sys_get_temp_dir(), 'up');
    file_put_contents($path, $content);
    $file = new UploadedFile($path, $name, $mime, null, true);

    return test()->post($url, ['import' => 1, $field => $url === 'import/importsubscribers' ? [$file] : $file]);
}

test('executable or disguised uploads are refused and nothing is stored', function (string $name, string $content, string $mime) {
    loginAs(lcoUser());
    $res = importUpload($name, $content, $mime);
    expect($res->getContent())->toContain('invalid file type');
    expect(Storage::disk('local')->files('imports'))->toBe([]);
})->with([
    'php as csv' => ['shell.csv', "<?php system(\$_GET['c']); ?>", 'text/csv'],
    'double extension' => ['shell.php.csv', '<?php echo 1; ?>', 'text/csv'],
    'exe' => ['a.exe', 'MZ....', 'application/octet-stream'],
    'php' => ['a.php', '<?php echo 1;', 'text/x-php'],
    'html as xlsx' => ['a.xlsx', '<html><script>alert(1)</script></html>', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
    'svg' => ['a.svg', '<svg onload=alert(1)>', 'image/svg+xml'],
]);

test('an oversized upload is refused', function () {
    loginAs(lcoUser());
    $big = UploadedFile::fake()->create('big.csv', 25 * 1024, 'text/csv');   // 25 MB > the 20 MB limit
    $res = $this->post('import/importsubscribers', ['import' => 1, 'file' => [$big]]);
    expect($res->getContent())->toContain('invalid file');
});

test('a broken workbook is reported, not a 500, and is not kept', function () {
    loginAs(lcoUser());
    $res = importUpload('broken.xlsx', 'PK-not-really-a-zip', 'application/zip');
    expect($res->status())->toBeLessThan(500);
    expect(Storage::disk('local')->files('imports'))->toBe([]);
});

test('uploads are only stored with a random name, never the client\'s', function () {
    $svc = app(ImportService::class);
    $path = tempnam(sys_get_temp_dir(), 'up');
    file_put_contents($path, "a,b\n1,2\n");
    $r = $svc->accept(new UploadedFile($path, '../../../public/evil.csv', 'text/csv', null, true));
    expect($r)->toBeArray();
    expect(basename($r[0]))->toMatch('/^[0-9a-f]{24}\.csv$/')->and($r[0])->not->toContain('evil');
    $svc->discard($r[0]);
});
