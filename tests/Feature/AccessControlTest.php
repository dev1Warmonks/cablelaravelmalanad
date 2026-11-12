<?php

use Illuminate\Support\Facades\DB;

/** Pages by the role that may open them (the old CI constructors redirected everyone else to login/logout). */
const LCO_PAGES = ['master', 'Subscriber', 'Transaction/payment', 'Transaction', 'report/date_wise_report', 'report/agent_report', 'import/subscribers', 'complaint', 'Settings/general'];
const AGENT_PAGES = ['agent', 'agent/payment', 'agent/date_wise_report'];
const ADMIN_ONLY_PAGES = ['AdminSettings/general', 'AdminSettings/backup', 'Transaction/all_network_transaction'];

test('anonymous visitors are sent to logout/login from every protected page', function (string $page) {
    $res = $this->get($page);
    $res->assertRedirect();
    expect($res->headers->get('Location'))->toContain('login');
})->with(array_merge(LCO_PAGES, AGENT_PAGES, ADMIN_ONLY_PAGES));

test('an agent cannot open LCO pages', function (string $page) {
    loginAs(agentUser());
    $res = $this->get($page);
    expect($res->status())->not->toBe(200);
})->with(LCO_PAGES);

test('an LCO user cannot open the agent panel', function (string $page) {
    loginAs(lcoUser());
    expect($this->get($page)->status())->not->toBe(200);
})->with(AGENT_PAGES);

test('administrator-only pages refuse LCO users and agents', function (string $page) {
    loginAs(lcoUser());
    expect($this->get($page)->status())->toBeIn([302, 403]);
    loginAs(agentUser());
    expect($this->get($page)->status())->toBeIn([302, 403]);
})->with(ADMIN_ONLY_PAGES);

test('the LCO pages open for an LCO user', function (string $page) {
    loginAs(lcoUser());
    $this->get($page)->assertOk();
})->with(['master', 'Transaction', 'Transaction/payment', 'report/date_wise_report', 'complaint', 'Subscriber']);

test('the agent pages open for an agent', function (string $page) {
    loginAs(agentUser());
    $this->get($page)->assertOk();
})->with(AGENT_PAGES);

test('state-changing endpoints are POST only (a link cannot trigger them)', function (string $url) {
    loginAs(lcoUser());
    expect($this->get($url)->status())->toBeIn([404, 405]);
})->with(['Transaction/add_payment', 'Transaction/add_bulk_miscellaneous', 'import/update_database', 'import/import_products', 'complaint/add_complaint', 'Transaction/delete_voucher']);

test('menu permissions are enforced inside the controllers', function () {
    loginAs(lcoUser());
    $has = fn (int $id) => DB::table('m_user_roles')->where('user_id', lcoUser()->id)->where('menu_id', $id)->exists();
    $products = $this->get('import/importproducts')->status();
    expect($products)->toBe($has(1) ? 200 : 403);
    $opening = $this->get('import/importsubscriberopeningbalance')->status();
    expect($opening)->toBe($has(12) ? 200 : 403);
});
