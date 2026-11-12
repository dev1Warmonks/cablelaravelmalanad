<?php

use App\Services\PaymentService;
use Illuminate\Support\Facades\DB;

/** Hostile input to every kind of parameter must never cause a SQL error page, nor change data, nor come back as live markup. */
function payloads(): array
{
    return ["1' OR '1'='1", '1; DROP TABLE m_customer; --', "' UNION SELECT password,2,3 FROM m_user -- ", '1 OR 1=1', '\\', "1'\"", '%27%20OR%201%3D1', '0x27', '<script>alert(1)</script>', str_repeat('A', 5000)];
}

test('report endpoints survive hostile parameters', function (string $payload) {
    loginAs(lcoUser());
    foreach (['date_wise_report_data', 'daily_report_data', 'agent_report_data', 'monthly_report_data', 'agent_wise_collection_report_data', 'expense_report_data', 'discount_report_data'] as $ep) {
        $res = $this->post('report/'.$ep, ['sfromdate' => $payload, 'stodate' => $payload, 'agentid' => $payload, 'paidstat' => $payload, 'header' => $payload, 'network' => $payload]);
        expect($res->status())->toBeLessThan(500);
        expect($res->getContent())->not->toContain('SQLSTATE')->not->toContain('Illuminate\\');
    }
    $res = $this->post('report/payment_sms', ['txnid' => $payload]);
    expect($res->status())->toBeLessThan(500);
    expect($res->getContent())->not->toContain('SQLSTATE');
})->with(payloads());

test('billing endpoints survive hostile ids and amounts', function (string $payload) {
    loginAs(lcoUser());
    $before = DB::table('subscriberpayments')->count();
    foreach (['Transaction/subscriber_details', 'Transaction/subscriber_payments', 'Transaction/ledger_result'] as $ep) {
        $res = $this->post($ep, ['subscriberid' => $payload]);
        expect($res->status())->toBeLessThan(500);
        expect($res->getContent())->not->toContain('SQLSTATE');
    }
    $res = $this->post('Transaction/add_payment', ['subscriberid' => customerOf(lcoUser()->lcoCode)->id, 'paidamount' => $payload, 'discount' => $payload, 'comment' => $payload, 'trantype' => $payload, 'paymentdate' => $payload]);
    expect($res->status())->toBeLessThan(500);
    expect($res->getContent())->not->toContain('SQLSTATE');
    expect(DB::table('subscriberpayments')->count())->toBe($before);   // nothing was recorded
})->with(payloads());

test('complaint and import endpoints survive hostile input', function (string $payload) {
    loginAs(lcoUser());
    foreach ([['complaint/add_complaint', ['subscriber' => $payload, 'complaint_type' => $payload, 'description' => $payload]],
        ['complaint/update_complaint', ['technician_remarks' => $payload, 'statuslist' => $payload, 'complaint_id' => $payload]]] as [$url, $data]) {
        $res = $this->post($url, $data);
        expect($res->status())->toBeLessThan(500)->and($res->getContent())->toBe('Failed');
    }
    foreach (['complaint/view_complaint/', 'complaint/edit_complaint/'] as $url) {
        expect($this->get($url.rawurlencode($payload))->status())->toBeLessThan(500);
    }
    expect($this->get('import/send_sms_to_pending_customers/'.rawurlencode($payload))->status())->toBeLessThan(500);
})->with(payloads());

test('CRUD list parameters survive hostile search and sort input', function (string $payload) {
    loginAs(adminUser());
    $res = $this->post('master/area_management/ajax_list', ['search_text' => $payload, 'search_field' => $payload, 'order_by' => [$payload, 'asc'], 'per_page' => $payload, 'page' => $payload]);
    expect($res->status())->toBeLessThan(500);
    expect($res->getContent())->not->toContain('SQLSTATE');
})->with(payloads());

test('names and addresses are escaped wherever they are listed', function () {
    loginAs(lcoUser());
    $c = customerOf(lcoUser()->lcoCode);
    DB::table('m_customer')->where('id', $c->id)->update(['customer_name' => '<img src=x onerror=alert(1)>', 'billing_address' => '"><script>alert(2)</script>']);
    DB::table('subscriberpayments')->insert(['transactiontype' => '1', 'subscriberid' => (string) $c->id, 'description' => '<b>desc</b>', 'dueamount' => 0, 'paidamount' => 1, 'discount' => 0,
        'subscriptioncharge' => 0, 'duebalance' => 0, 'dbafterdiscount' => 0, 'timestamp' => (string) PaymentService::milliseconds(), 'agentid' => 0]);
    $today = date('Y-m-d');
    foreach (['report/agent_report_data', 'report/daily_report_data'] as $ep) {
        $body = $this->post($ep, ['sfromdate' => $today, 'stodate' => $today, 'agentid' => -1])->getContent();
        expect($body)->not->toContain('<img src=x onerror')->not->toContain('<script>alert(2)')->not->toContain('<b>desc</b>');
    }
    $body = $this->post('report/monthly_report_data', ['sfromdate' => $today, 'stodate' => $today, 'paidstat' => 1])->getContent();
    expect($body)->not->toContain('<img src=x onerror')->not->toContain('<script>alert(2)');
});
