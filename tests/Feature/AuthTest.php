<?php

use Illuminate\Support\Facades\RateLimiter;

test('the login page renders for an anonymous visitor', function () {
    $this->get('/')->assertOk();
    $this->get('Login')->assertOk();
});

test('a wrong password does not sign anyone in', function () {
    expect($this->post('login/process_login', ['username' => lcoUser()->username, 'password' => 'definitely-wrong'])->status())->toBeIn([200, 302]);
    expect(session()->has('usertype'))->toBeFalse();
});

test('an unknown user and an empty body do not sign anyone in', function () {
    $this->post('login/process_login', ['username' => 'nobody-'.uniqid(), 'password' => 'x']);
    expect(session()->has('usertype'))->toBeFalse();
    $this->post('login/process_login', []);
    expect(session()->has('usertype'))->toBeFalse();
});

test('SQL injection in the login form does not bypass the password', function (string $payload) {
    expect($this->post('login/process_login', ['username' => $payload, 'password' => $payload])->status())->toBeIn([200, 302]);
    expect(session()->has('usertype'))->toBeFalse();
})->with(["' OR '1'='1", "admin'-- ", "x' OR 1=1 #", '" OR ""="', "'; DROP TABLE m_user; --"]);

test('repeated failures lock the username/IP combination out, even for the right password', function () {
    $max = (int) config('cablebill.login_max_attempts');
    for ($i = 0; $i < $max; $i++) {
        $this->post('login/process_login', ['username' => lcoUser()->username, 'password' => 'wrong'.$i]);
    }
    $this->post('login/process_login', ['username' => lcoUser()->username, 'password' => lcoUser()->password]);
    expect(session()->has('usertype'))->toBeFalse();
    RateLimiter::clear('login:127.0.0.1|'.mb_strtolower(lcoUser()->username));
});

test('each role lands on its own home page', function () {
    loginAs(adminUser());
    expect((string) session('usertype'))->toBe('1');
    $this->flushSession();
    loginAs(lcoUser());
    expect((string) session('usertype'))->toBe('2');
    $this->flushSession();
    loginAs(agentUser());
    expect((string) session('usertype'))->toBe('3');
});

test('signing in regenerates the session id (session fixation)', function () {
    $before = session()->getId();
    loginAs(lcoUser());
    expect(session()->getId())->not->toBe($before);
});

test('logout clears the identity', function () {
    loginAs(lcoUser());
    $this->get('login/logout')->assertRedirect();
    expect(session()->has('usertype'))->toBeFalse()->and(session()->has('userid'))->toBeFalse();
});
