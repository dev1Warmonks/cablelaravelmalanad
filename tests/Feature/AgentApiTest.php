<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;

function api(string $method, array|string $body): TestResponse
{
    return test()->call('POST', 'AgentApi/'.$method, [], [], [], ['CONTENT_TYPE' => 'application/json'], is_string($body) ? $body : json_encode($body));
}

function apiCustomer(): object
{
    $lcos = DB::table('m_network')->where('created_by', agentUser()->created_by)->pluck('lcoCode')->all();
    $q = DB::table('m_customer')->whereIn('lcoCode', $lcos)->where('status', '!=', 2);
    if (DB::table('agent_areas')->where('agent_id', agentUser()->id)->exists()) {
        $q->whereIn('subarea_id', DB::table('agent_areas')->select('agent_area_id')->where('agent_id', agentUser()->id));
    }

    return $q->orderByDesc('id')->first();
}

test('login: right credentials give the token, wrong ones a generic failure', function () {
    $ok = api('login', ['username' => agentUser()->username, 'password' => agentUser()->password])->json();
    expect($ok['status'])->toBe('success')->and($ok['token'])->toBe(agentUser()->token);
    $bad = api('login', ['username' => agentUser()->username, 'password' => 'wrong'])->json();
    expect($bad)->toBe(['status' => 'failed', 'message' => 'Invalid Username or Password']);
});

test('login is rate limited per IP', function () {
    RateLimiter::clear('agentapi-login:127.0.0.1');
    for ($i = 0; $i < 20; $i++) {
        api('login', ['username' => 'x', 'password' => 'y'.$i]);
    }
    $res = api('login', ['username' => agentUser()->username, 'password' => agentUser()->password]);
    expect($res->getStatusCode())->toBe(429);
    RateLimiter::clear('agentapi-login:127.0.0.1');
});

test('SQL injection in the credentials or the token authenticates nobody', function (string $payload) {
    expect(api('login', ['username' => $payload, 'password' => $payload])->json('status'))->toBe('failed');
    expect(api('verify', ['token' => $payload])->json('status'))->toBe('failed');
    expect(api('subscriber_list', ['token' => $payload])->json('status'))->toBe('failed');
})->with(["' OR '1'='1", "x' OR 1=1 -- ", '" OR ""="', '1; DROP TABLE agent']);

test('an empty, array or non-JSON body is handled without errors', function (string $raw) {
    foreach (['login', 'verify', 'subscriber_list', 'add_payment', 'payment_print', 'complaint_add', 'dalily_report'] as $ep) {
        expect(api($ep, $raw)->getStatusCode())->toBeLessThan(500);
    }
})->with(['', 'not json', '[]', '{"token":["a","b"]}', '{"token":{"a":1}}', '{"username":[],"password":[]}', 'null']);

test('payment_print only serves receipts of the agent\'s own networks', function () {
    $own = DB::table('subscriberpayments as p')->join('m_customer as c', 'c.id', '=', 'p.subscriberid')
        ->whereIn('c.lcoCode', DB::table('m_network')->select('lcoCode')->where('created_by', agentUser()->created_by))->orderByDesc('p.id')->first(['p.id']);
    $foreign = DB::table('subscriberpayments as p')->join('m_customer as c', 'c.id', '=', 'p.subscriberid')
        ->whereNotIn('c.lcoCode', DB::table('m_network')->select('lcoCode')->where('created_by', agentUser()->created_by))->orderByDesc('p.id')->first(['p.id']);
    $mine = api('payment_print', ['token' => agentUser()->token, 'payment_id' => $own->id]);
    expect($mine->headers->get('Content-Type'))->toContain('application/pdf')->and(substr($mine->getContent(), 0, 4))->toBe('%PDF');
    $other = api('payment_print', ['token' => agentUser()->token, 'payment_id' => $foreign->id]);
    expect($other->json())->toBe(['status' => 'failed', 'message' => 'Invoice data not found']);
});

test('add_payment: a customer of another network is refused', function () {
    $foreign = DB::table('m_customer')->whereNotIn('lcoCode', DB::table('m_network')->select('lcoCode')->where('created_by', agentUser()->created_by))->first();
    expect(api('add_payment', ['token' => agentUser()->token, 'subscriberid' => $foreign->id, 'paidamount' => 7])->json('message'))->toBe('Invalid Customer');
});

test('add_payment: amounts are validated', function (mixed $paid, mixed $discount) {
    $c = apiCustomer();
    $before = DB::table('subscriberpayments')->count();
    $res = api('add_payment', ['token' => agentUser()->token, 'subscriberid' => $c->id, 'paidamount' => $paid, 'discount' => $discount])->json();
    expect($res)->toBe(['status' => 'failed', 'message' => 'Invalid Amount']);
    expect(DB::table('subscriberpayments')->count())->toBe($before);
})->with([[0, 0], [-5, 0], ['abc', 0], ["1' OR '1'='1", 0], [5, -1], [5, 'x'], ['1e3', 0], ['5.123', 0], [[1], 0], [5, [1]]]);

test('add_payment: records the payment exactly, blocks a repeat within 30 minutes, flags it as an app payment', function () {
    $c = apiCustomer();
    $due = (string) (DB::table('subscriberbalance')->where('subscriberid', (string) $c->id)->value('amountdue') ?? '0');
    $res = api('add_payment', ['token' => agentUser()->token, 'subscriberid' => $c->id, 'paidamount' => '10.10', 'discount' => '0.20', 'comment' => 'pest']);
    expect($res->json())->toBe(['message' => 'Payment Success', 'status' => 'success', 'print' => '']);
    $row = DB::table('subscriberpayments')->where('subscriberid', (string) $c->id)->orderByDesc('id')->first();
    expect((string) $row->agentid)->toBe((string) agentUser()->id)->and((int) $row->is_app)->toBe(1)
        ->and(bccomp((string) $row->dueamount, $due, 2))->toBe(0)
        ->and(bccomp((string) $row->dbafterdiscount, bcsub(bcsub($due, '10.10', 2), '0.20', 2), 2))->toBe(0);
    expect(bccomp((string) DB::table('subscriberbalance')->where('subscriberid', (string) $c->id)->value('amountdue'), (string) $row->dbafterdiscount, 2))->toBe(0);
    $again = api('add_payment', ['token' => agentUser()->token, 'subscriberid' => $c->id, 'paidamount' => '10.10', 'discount' => 0]);
    expect($again->json('message'))->toBe('Please try after 30 minutes');
});

test('responses are plain json_encode output (byte-compatible with the old app)', function () {
    $raw = api('transaction_type_list', ['token' => agentUser()->token])->getContent();
    expect($raw)->toBe(json_encode(json_decode($raw, true)));
});
