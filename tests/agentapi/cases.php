<?php

/**
 * AgentApi parity cases: the same requests are sent to the OLD CodeIgniter app (recorded) and to the new Laravel app,
 * and the responses are compared byte for byte.
 *
 * Built from the local database copy (never production). Tokens and customers are picked from the data, so the case list
 * adapts to the copy; the recordings (which contain real names/tokens from the copy) are stored in storage/app/agentapi_recordings
 * and are never committed.
 *
 * Write cases (add_payment, complaint_add) are marked 'write' and run LAST: the recorded old app commits them to its own
 * clone database; the new app runs them inside a rolled-back transaction.
 */

use Illuminate\Support\Facades\DB;

return function (): array {
    $agentWithAreas = DB::table('agent as a')->where('a.status', 1)->where('a.emptype', 3)->whereNotNull('a.token')->where('a.token', '!=', '')
        ->whereRaw('(select count(*) from agent_areas where agent_id = a.id) > 0')
        ->whereIn('a.created_by', DB::table('m_user')->select('id')->where('status', 1))
        ->orderBy('a.id')->first();
    $agentNoAreas = DB::table('agent as a')->where('a.status', 1)->where('a.emptype', 3)->whereNotNull('a.token')->where('a.token', '!=', '')
        ->whereRaw('(select count(*) from agent_areas where agent_id = a.id) = 0')
        ->whereIn('a.created_by', DB::table('m_user')->select('id')->where('status', 1))
        ->whereIn('a.created_by', DB::table('m_network')->select('created_by'))
        ->orderBy('a.id')->first();
    $inactive = DB::table('agent')->where('status', 0)->where('emptype', 3)->whereNotNull('token')->where('token', '!=', '')->first();

    $networks = fn ($agent) => DB::table('m_network')->where('created_by', $agent->created_by)->pluck('lcoCode')->all();
    $customerOf = function ($agent) use ($networks) {
        return DB::table('m_customer')->whereIn('lcoCode', $networks($agent))->where('status', '!=', 2)
            ->whereIn('id', DB::table('subscriberpayments')->select(DB::raw('CAST(subscriberid AS UNSIGNED)'))->where('transactiontype', '1'))
            ->orderByDesc('id')->first();
    };
    $custA = $customerOf($agentWithAreas);
    $custB = $agentNoAreas ? $customerOf($agentNoAreas) : null;
    $foreign = DB::table('m_customer')->whereNotIn('lcoCode', $networks($agentWithAreas))->first();
    $payment = DB::table('subscriberpayments')->where('subscriberid', (string) $custA->id)->where('transactiontype', '1')->orderByDesc('id')->first();
    $foreignPayment = DB::table('subscriberpayments')->where('subscriberid', (string) $foreign->id)->orderByDesc('id')->first();
    $miscHead = DB::table('m_dropdown')->where('type', 3)->first();
    $complaintType = DB::table('m_dropdown')->where('type', 1)->where('status', 1)->first();
    $tokA = $agentWithAreas->token;
    $tokB = $agentNoAreas->token ?? $tokA;

    $c = [];
    $add = function (string $name, string $path, $body, bool $write = false) use (&$c) {
        $c[] = ['name' => $name, 'path' => $path, 'body' => $body, 'write' => $write];
    };

    // ---- login / verify
    $add('login_ok', 'login', ['username' => $agentWithAreas->username, 'password' => $agentWithAreas->password]);
    $add('login_ok_no_areas', 'login', ['username' => ($agentNoAreas ?? $agentWithAreas)->username, 'password' => ($agentNoAreas ?? $agentWithAreas)->password]);
    $add('login_bad_password', 'login', ['username' => $agentWithAreas->username, 'password' => 'definitely-wrong']);
    $add('login_unknown_user', 'login', ['username' => 'nobody-xyz', 'password' => 'x']);
    $add('login_missing_password', 'login', ['username' => $agentWithAreas->username]);
    $add('login_empty_body', 'login', []);
    $add('login_not_json', 'login', 'this is not json');
    if ($inactive) {
        $add('login_inactive_agent', 'login', ['username' => $inactive->username, 'password' => $inactive->password]);
    }
    $add('verify_ok', 'verify', ['token' => $tokA]);
    $add('verify_bad_token', 'verify', ['token' => 'bad-token']);
    $add('verify_missing', 'verify', []);
    if ($inactive) {
        $add('verify_inactive_agent', 'verify', ['token' => $inactive->token]);
    }

    // ---- read endpoints
    foreach (['subscriber_list', 'transaction_type_list', 'area_list', 'print_header', 'complaint_type_list', 'dalily_report'] as $ep) {
        $add($ep.'_agent_with_areas', $ep, ['token' => $tokA]);
        if ($agentNoAreas) {
            $add($ep.'_agent_no_areas', $ep, ['token' => $tokB]);
        }
        $add($ep.'_bad_token', $ep, ['token' => 'bad-token']);
        $add($ep.'_missing_token', $ep, []);
    }
    $add('history_ok', 'subscriber_transaction_history', ['token' => $tokA, 'subscriberid' => $custA->id]);
    $add('history_string_id', 'subscriber_transaction_history', ['token' => $tokA, 'subscriberid' => (string) $custA->id]);
    $add('history_foreign_customer', 'subscriber_transaction_history', ['token' => $tokA, 'subscriberid' => $foreign->id]);
    $add('history_no_customer', 'subscriber_transaction_history', ['token' => $tokA]);
    $add('history_bad_token', 'subscriber_transaction_history', ['token' => 'bad', 'subscriberid' => $custA->id]);
    $add('history_missing_token', 'subscriber_transaction_history', ['subscriberid' => $custA->id]);
    if ($custB) {
        $add('history_ok_agent_b', 'subscriber_transaction_history', ['token' => $tokB, 'subscriberid' => $custB->id]);
    }
    // payment_print returns a PDF for a valid payment: compared by text content, not bytes (FPDF stamps the creation time)
    $add('print_ok', 'payment_print', ['token' => $tokA, 'payment_id' => $payment->id]);
    $add('print_alt_key_paymentid', 'payment_print', ['token' => $tokA, 'paymentid' => $payment->id]);
    $add('print_unknown_payment', 'payment_print', ['token' => $tokA, 'payment_id' => 999999999]);
    $add('print_bad_token', 'payment_print', ['token' => 'bad', 'payment_id' => $payment->id]);
    $add('print_missing_token', 'payment_print', ['payment_id' => $payment->id]);
    $add('print_foreign_payment', 'payment_print', ['token' => $tokA, 'payment_id' => $foreignPayment->id ?? 0]);

    // ---- writes (last)
    $pay = fn (array $extra = []) => array_merge(['token' => $tokA, 'subscriberid' => $custA->id, 'paidamount' => 7, 'discount' => 0, 'comment' => 'api test'], $extra);
    $add('add_payment_ok', 'add_payment', $pay(), true);
    $add('add_payment_duplicate_within_30min', 'add_payment', $pay(), true);
    $add('add_payment_with_discount', 'add_payment', $pay(['paidamount' => 8, 'discount' => 2, 'comment' => '']), true);
    if ($miscHead) {
        $add('add_payment_misc_head', 'add_payment', $pay(['paidamount' => 9, 'trantype' => $miscHead->id, 'comment' => 'head test']), true);
    }
    $add('add_payment_other_mobile', 'add_payment', $pay(['paidamount' => 10, 'othermobile' => '9000000001']), true);
    $add('add_payment_foreign_customer', 'add_payment', $pay(['subscriberid' => $foreign->id, 'paidamount' => 11]), true);
    $add('add_payment_no_customer', 'add_payment', ['token' => $tokA, 'paidamount' => 12], true);
    $add('add_payment_bad_token', 'add_payment', $pay(['token' => 'bad', 'paidamount' => 13]), true);
    $add('add_payment_missing_token', 'add_payment', ['subscriberid' => $custA->id, 'paidamount' => 14], true);
    $add('complaint_add_ok', 'complaint_add', ['token' => $tokA, 'customer_id' => $custA->id, 'complaint_id' => $complaintType->id ?? 0, 'description' => 'api test complaint'], true);
    $add('complaint_add_foreign_customer', 'complaint_add', ['token' => $tokA, 'customer_id' => $foreign->id, 'complaint_id' => $complaintType->id ?? 0, 'description' => 'x'], true);
    $add('complaint_add_bad_token', 'complaint_add', ['token' => 'bad', 'customer_id' => $custA->id], true);
    $add('complaint_add_missing_token', 'complaint_add', ['customer_id' => $custA->id], true);

    // after the writes: the daily report and history must show them (same in old and new)
    $add('dalily_report_after_writes', 'dalily_report', ['token' => $tokA], true);
    $add('history_after_writes', 'subscriber_transaction_history', ['token' => $tokA, 'subscriberid' => $custA->id], true);
    $add('subscriber_list_after_writes', 'subscriber_list', ['token' => $tokA], true);

    return $c;
};
