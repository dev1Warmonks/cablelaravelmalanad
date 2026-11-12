<?php

use App\Services\GsoftBillingService;
use App\Services\PaymentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

function paymentInput(object $customer, array $over = []): array
{
    return $over + ['subscriberid' => $customer->id, 'paidamount' => '10.00', 'discount' => '0', 'comment' => '', 'trantype' => '1'];
}

test('ledger arithmetic is exact to the cent (no float drift)', function () {
    $c = customerOf(lcoUser()->lcoCode);
    DB::table('subscriberbalance')->updateOrInsert(['subscriberid' => (string) $c->id], ['amountdue' => '0.30']);
    $r = app(PaymentService::class)->record(paymentInput($c, ['paidamount' => '0.10', 'discount' => '0.20']), lcoUser()->id, lcoUser()->id);
    expect($r['balance_after'])->toBe('0.00')->and($r['due_before'])->toBe('0.30');
    expect(bccomp((string) DB::table('subscriberbalance')->where('subscriberid', (string) $c->id)->value('amountdue'), '0', 2))->toBe(0);
});

test('every payment is a ledger row and the balance mirrors the last row', function () {
    $c = customerOf(lcoUser()->lcoCode);
    $svc = app(PaymentService::class);
    $a = $svc->record(paymentInput($c, ['paidamount' => '12.34']), lcoUser()->id, lcoUser()->id);
    $b = $svc->record(paymentInput($c, ['paidamount' => '7.66', 'discount' => '1.00']), lcoUser()->id, lcoUser()->id);
    expect($b['due_before'])->toBe($a['balance_after']);
    expect(bcsub(bcsub($b['due_before'], '7.66', 2), '1.00', 2))->toBe($b['balance_after']);
    $rows = DB::table('subscriberpayments')->whereIn('id', [$a['id'], $b['id']])->count();
    expect($rows)->toBe(2);
});

test('invalid amounts are rejected before anything is written', function (string $amount) {
    $c = customerOf(lcoUser()->lcoCode);
    $before = DB::table('subscriberpayments')->count();
    expect(fn () => app(PaymentService::class)->record(paymentInput($c, ['paidamount' => $amount]), lcoUser()->id, lcoUser()->id))->toThrow(InvalidArgumentException::class);
    expect(DB::table('subscriberpayments')->count())->toBe($before);
})->with(['abc', '1,5', '1e3', "1' OR 1=1", '1.234', '']);

test('the web payment form refuses bad amounts without a server error', function (string $amount) {
    loginAs(lcoUser());
    $c = customerOf(lcoUser()->lcoCode);
    $before = DB::table('subscriberpayments')->count();
    $res = $this->post('Transaction/add_payment', ['subscriberid' => $c->id, 'paidamount' => $amount, 'discount' => 0, 'comment' => '', 'trantype' => 1, 'paymentdate' => '2026-01-01']);
    expect($res->status())->toBeLessThan(500);
    expect(DB::table('subscriberpayments')->count())->toBe($before);
})->with(['abc', '-5', '1e3', "1' OR 1=1", '5.123']);   // (a paid amount of 0 is allowed: discount-only entries)

// ---------------------------------------------------------------- UPIGateway callback idempotency (HTTP always faked)

afterEach(function () {
    DB::table('upigateway_history')->where('client_txn_id', 'like', 'PEST-%')->delete();   // MyISAM: not rolled back
});

function gsoftBill(string $amount = '100'): array
{
    $billId = DB::table('gsoft_billing')->insertGetId(['lco_code' => lcoUser()->lcoCode, 'current_month' => '2031-01-01', 'invoice_generated' => 1, 'free_sms_used' => 0,
        'extra_sms_used' => 0, 'invoice_total' => $amount, 'created_on' => now(), 'payment_type' => 0, 'bill_status' => 1]);
    $txn = 'PEST-'.uniqid();
    DB::table('upigateway_history')->insert(['bill_id' => $billId, 'client_txn_id' => $txn, 'status' => 0, 'created_on' => now(), 'created_by' => lcoUser()->id, 'date' => date('Y-m-d')]);

    return [$billId, $txn];
}

test('a gateway success credits the bill once; a replayed callback does not credit it again', function () {
    Http::fake(['*' => Http::response(['status' => true, 'data' => ['status' => 'success', 'amount' => '100']], 200)]);
    [$bill, $txn] = gsoftBill();
    $svc = app(GsoftBillingService::class);
    expect($svc->checkOrder($txn, date('Y-m-d'), $bill, lcoUser()->id))->toBeTrue();
    expect((int) DB::table('gsoft_billing')->where('id', $bill)->value('bill_status'))->toBe(2);
    expect($svc->checkOrder($txn, date('Y-m-d'), $bill, lcoUser()->id))->toBeFalse();   // replay
    expect((int) DB::table('gsoft_billing')->where('id', $bill)->value('bill_status'))->toBe(2);
});

test('a paid amount that differs from the bill does not mark it paid', function () {
    Http::fake(['*' => Http::response(['status' => true, 'data' => ['status' => 'success', 'amount' => '1']], 200)]);
    [$bill, $txn] = gsoftBill('100');
    expect(app(GsoftBillingService::class)->checkOrder($txn, date('Y-m-d'), $bill, lcoUser()->id))->toBeFalse();
    expect((int) DB::table('gsoft_billing')->where('id', $bill)->value('bill_status'))->toBe(1);
});

test('a transaction id of another bill cannot be used to pay this one', function () {
    Http::fake(['*' => Http::response(['status' => true, 'data' => ['status' => 'success', 'amount' => '100']], 200)]);
    [$billA, $txnA] = gsoftBill();
    [$billB] = gsoftBill();
    expect(app(GsoftBillingService::class)->checkOrder($txnA, date('Y-m-d'), $billB, lcoUser()->id))->toBeFalse();
    expect((int) DB::table('gsoft_billing')->where('id', $billB)->value('bill_status'))->toBe(1);
});

test('a failed or pending gateway answer never pays a bill', function (array $answer) {
    Http::fake(['*' => Http::response($answer, 200)]);
    [$bill, $txn] = gsoftBill();
    expect(app(GsoftBillingService::class)->checkOrder($txn, date('Y-m-d'), $bill, lcoUser()->id))->toBeFalse();
    expect((int) DB::table('gsoft_billing')->where('id', $bill)->value('bill_status'))->toBe(1);
})->with([[['status' => false, 'msg' => 'x']], [['status' => true, 'data' => ['status' => 'created']]], [['status' => true, 'data' => ['status' => 'failure']]], [[]]]);

test('with no gateway key configured nothing is sent and nothing is paid', function () {
    config(['cablebill.upigateway.key' => '']);
    Http::fake();
    [$bill, $txn] = gsoftBill();
    expect(app(GsoftBillingService::class)->checkOrder($txn, date('Y-m-d'), $bill, lcoUser()->id))->toBeFalse();
    Http::assertNothingSent();
});
