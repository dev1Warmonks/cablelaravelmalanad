<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/** IDOR: one LCO (or an agent) must never read or change another network's data by guessing ids. */
test('an LCO cannot read the details or the ledger of another LCO\'s customer', function () {
    loginAs(lcoUser());
    $foreign = customerNotOf(lcoUser()->lcoCode);
    $this->post('Transaction/subscriber_details', ['subscriberid' => $foreign->id])->assertJson(['success' => false]);
    expect($this->post('Transaction/subscriber_payments', ['subscriberid' => $foreign->id])->getContent())->toBe('');
    expect($this->post('Transaction/ledger_result', ['subscriberid' => $foreign->id])->getContent())->toBe('');
});

test('an LCO can read its own customer', function () {
    loginAs(lcoUser());
    $mine = customerOf(lcoUser()->lcoCode);
    $this->post('Transaction/subscriber_details', ['subscriberid' => $mine->id])->assertJson(['success' => true]);
});

test('an agent cannot read or pay a customer of another network', function () {
    loginAs(agentUser());
    $foreign = DB::table('m_customer')->whereNotIn('lcoCode', DB::table('m_network')->select('lcoCode')->where('created_by', agentUser()->created_by))->first();
    $this->post('Transaction/subscriber_details', ['subscriberid' => $foreign->id])->assertJson(['success' => false]);
    $before = DB::table('subscriberpayments')->where('subscriberid', (string) $foreign->id)->count();
    $this->post('Transaction/add_payment', ['subscriberid' => $foreign->id, 'paidamount' => 5, 'discount' => 0, 'comment' => '', 'trantype' => 1, 'paymentdate' => '2026-01-01']);
    expect(DB::table('subscriberpayments')->where('subscriberid', (string) $foreign->id)->count())->toBe($before);
});

test('an LCO cannot pay a customer of another LCO', function () {
    loginAs(lcoUser());
    $foreign = customerNotOf(lcoUser()->lcoCode);
    $before = DB::table('subscriberpayments')->where('subscriberid', (string) $foreign->id)->count();
    $this->post('Transaction/add_payment', ['subscriberid' => $foreign->id, 'paidamount' => 5, 'discount' => 0, 'comment' => '', 'trantype' => 1, 'paymentdate' => '2026-01-01']);
    expect(DB::table('subscriberpayments')->where('subscriberid', (string) $foreign->id)->count())->toBe($before);
});

test('the SMS text of another LCO\'s payment is not shown', function () {
    loginAs(lcoUser());
    $pay = DB::table('subscriberpayments as p')->join('m_customer as c', 'c.id', '=', 'p.subscriberid')->where('c.lcoCode', '<>', lcoUser()->lcoCode)->orderByDesc('p.id')->first(['p.id']);
    expect(trim($this->post('report/payment_sms', ['txnid' => $pay->id])->getContent()))->toBe('Sms not found');
});

test('a foreign LCO code is refused by the network report', function () {
    loginAs(lcoUser());
    $res = $this->post('report/network_daily_report_data', ['sfromdate' => '2026-10-01', 'stodate' => '2026-10-31', 'network' => otherLcoUser()->lcoCode]);
    expect(trim($res->getContent()))->toBe('');
});

test('complaints of another LCO cannot be viewed, edited or created for its customers', function () {
    loginAs(lcoUser());
    $other = DB::table('m_complaints')->whereNotIn('network_id', DB::table('m_network')->select('id')->where('created_by', lcoUser()->id))->orderByDesc('id')->first();
    expect($this->get('complaint/view_complaint/'.$other->id)->getContent())->not->toContain('Complaint Details');
    $remarks = $other->technician_remarks;
    expect($this->post('complaint/update_complaint', ['technician_remarks' => 'hijack', 'statuslist' => 2, 'complaint_id' => $other->id])->getContent())->toBe('Failed');
    expect(DB::table('m_complaints')->where('id', $other->id)->value('technician_remarks'))->toBe($remarks);
    $type = DB::table('m_dropdown')->where('type', 1)->first();
    $foreign = customerNotOf(lcoUser()->lcoCode);
    expect($this->post('complaint/add_complaint', ['subscriber' => $foreign->id, 'complaint_type' => $type->id, 'description' => 'x'])->getContent())->toBe('Failed');
});

test('opening balances can only be set for the LCO\'s own customers', function () {
    loginAs(lcoUser());
    if (! DB::table('m_user_roles')->where('user_id', lcoUser()->id)->where('menu_id', 12)->exists()) {
        $this->markTestSkipped('fixture LCO has no menu 12');
    }
    $foreign = customerNotOf(lcoUser()->lcoCode);
    $file = tempnam(sys_get_temp_dir(), 'ob').'.csv';
    file_put_contents($file, "Customer ID,n,l,a,Opening Balance\n{$foreign->customer_id},x,x,x,500\n");
    $due = DB::table('subscriberbalance')->where('subscriberid', (string) $foreign->id)->value('amountdue');
    $this->post('import/updatesubscribersopeningbalance', ['import' => 1, 'file' => new UploadedFile($file, 'ob.csv', 'text/csv', null, true)]);
    expect(DB::table('subscriberbalance')->where('subscriberid', (string) $foreign->id)->value('amountdue'))->toBe($due);
    unlink($file);
});

test('the Daily report of one LCO never contains another LCO\'s customers', function () {
    loginAs(lcoUser());
    $foreign = customerNotOf(lcoUser()->lcoCode);
    $ts = DB::table('subscriberpayments')->where('subscriberid', (string) $foreign->id)->orderByDesc('id')->value('timestamp');
    $day = date('Y-m-d', intdiv((int) $ts, 1000));
    $body = $this->post('report/agent_report_data', ['sfromdate' => $day, 'stodate' => $day, 'agentid' => -1])->getContent();
    expect($body)->not->toContain((string) $foreign->customer_name === '' ? 'impossible-string-'.uniqid() : e($foreign->customer_name));
});
