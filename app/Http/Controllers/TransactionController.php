<?php

namespace App\Http\Controllers;

use App\Services\GsoftBillingService;
use App\Services\InvoiceService;
use App\Services\LedgerService;
use App\Services\MenuService;
use App\Services\PaymentService;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Transaction: payments and ledger (billing core). Part 1: make payment (page, customer lookup, history, add_payment).
 *
 * Access: usertype 1/2 (admin, LCO) and 3 (agent, same endpoints as the old shared payment view).
 * The old code let any signed-in user read or pay ANY customer by id; now an LCO is limited to its own lcoCode and an
 * agent to the customers of its network (and its areas, when it has any), exactly like the agent's customer list.
 */
class TransactionController extends Controller
{
    public function __construct(private MenuService $menus)
    {
    }

    private function usertype(): string
    {
        return (string) session('usertype');
    }

    /** Customers the current user may see/pay. */
    private function customerScope()
    {
        $q = DB::table('m_customer');
        switch ($this->usertype()) {
            case '1':
                break;
            case '3':
                $agent = DB::table('agent')->where('id', session('userid'))->where('status', 1)->first(['created_by']);
                if (! $agent) {
                    return $q->whereRaw('1 = 0');
                }
                $q->whereIn('m_customer.lcoCode', DB::table('m_network')->select('lcoCode')->where('created_by', $agent->created_by));
                if (DB::table('agent_areas')->where('agent_id', session('userid'))->count() > 0) {
                    $q->whereIn('m_customer.subarea_id', DB::table('agent_areas')->select('agent_area_id')->where('agent_id', session('userid')));
                }
                break;
            default:
                $q->where('m_customer.lcoCode', session('lcoCode'));
        }

        return $q;
    }

    private function canAccess($customerId): bool
    {
        return is_scalar($customerId) && is_numeric($customerId) && $this->customerScope()->where('m_customer.id', $customerId)->exists();
    }

    /** The LCO user whose settings (SMS flag, signature) apply: the agent's creator for agents. */
    private function ownerUserId(): int|string
    {
        if ($this->usertype() === '3') {
            return DB::table('agent')->where('id', session('userid'))->value('created_by') ?? session('userid');
        }

        return session('userid');
    }

    // ---------------------------------------------------------------- pages

    /** Transaction/payment (usertype 1/2): the make-payment screen. */
    public function payment()
    {
        $lco = (string) session('lcoCode');
        $network = DB::table('m_network')->where('id', session('network_id'))->first(['network_name', 'lcoCode', 'address', 'phone']);

        return view('admin.payment.make_payment', [
            'menulist' => $this->menus->menuList(),
            'network_list' => $this->menus->networkList(),
            'subscriber' => DB::table('m_customer')->where('lcoCode', $lco)->where('status', '!=', 2)->orderBy('customer_name')->get(['id', 'customer_name', 'mobile', 'lco_customer_id', 'newid'])->map(fn ($r) => (array) $r)->all(),
            'miscellaneousheads_list' => DB::table('m_dropdown')->where('type', 3)->get(['id', 'name'])->map(fn ($r) => (array) $r)->all(),
            'header' => [
                'network_name' => $network->network_name ?? '', 'lcoCode' => $network->lcoCode ?? '', 'address' => $network->address ?? '',
                'phone' => $network->phone ?? '', 'report_name' => 'Payment history',
            ],
        ]);
    }

    // ---------------------------------------------------------------- ajax

    public function subscriberDetails(Request $request)
    {
        $id = $request->input('subscriberid');
        if ($id === null || ! $this->canAccess($id)) {
            return response()->json(['success' => false]);
        }
        $row = DB::table('m_customer as S')->leftJoin('subscriberbalance as SB', 'S.id', '=', 'SB.subscriberid')->where('S.id', $id)
            ->first(['S.id', 'S.customer_id', 'S.amc_status', 'S.opening_balance', 'S.customer_name', 'S.mobile', 'SB.amountdue', 'S.serial_number as stb', 'S.newid', 'S.billing_address as address']);
        $data = (array) $row;
        $data['success'] = true;

        return response()->json($data);
    }

    public function subscriberPayments(Request $request)
    {
        $id = $request->input('subscriberid');
        if ($id === null || ! $this->canAccess($id)) {
            return response('');
        }
        $out = [];
        // subscriberid is varchar in this table: bind a string so the index is used
        foreach (DB::table('subscriberpayments')->where('subscriberid', (string) $id)->orderByDesc('timestamp')->get() as $r) {
            $t = $r->transactiontype;
            $type = ($t == 0 || $t == 3) ? 'Bill' : (($t == 1) ? 'Payment' : '');
            $subscription = ($t == 0 || $t == 2) ? $r->subscriptioncharge : 0;
            $misc = $t == 3 ? $r->subscriptioncharge : 0;
            $out[] = [
                'timestamp' => date('d/m/Y H:i', (int) ($r->timestamp / 1000)),
                'description' => $r->description,
                'type' => $type,
                'miscellaneous' => number_format((float) $misc, 2, '.', ''),
                'subscriptioncharge' => number_format((float) $subscription, 2, '.', ''),
                'dueamount' => $r->dueamount, 'paidamount' => $r->paidamount, 'discount' => $r->discount, 'dbafterdiscount' => $r->dbafterdiscount,
            ];
        }

        return response()->json($out);
    }

    public function addPayment(Request $request, PaymentService $payments, SmsService $sms)
    {
        foreach (['subscriberid', 'paidamount', 'discount', 'comment', 'trantype'] as $k) {
            if (! $request->has($k)) {
                return response('');   // the old method simply produced no output
            }
        }
        $fail = fn (string $m) => response()->json(['status' => 'failed', 'message' => $m]);

        $id = $request->input('subscriberid');
        if (! $this->canAccess($id)) {
            return $fail('Invalid customer');
        }
        $paid = trim((string) $request->input('paidamount'));
        $discount = trim((string) $request->input('discount')) === '' ? '0' : trim((string) $request->input('discount'));
        if (! preg_match('/^\d{1,8}(\.\d{1,2})?$/', $paid) || ! preg_match('/^\d{1,8}(\.\d{1,2})?$/', $discount)) {
            return $fail('Invalid amount');
        }
        $trantype = (string) $request->input('trantype');   // an unknown head posts as a plain payment (is_misc=0), as before
        $other = substr(trim((string) $request->input('othermobile', '')), 0, 15);   // not 10 digits => no SMS (as before)

        $agentId = $this->usertype() === '3' ? session('userid') : 0;
        $owner = $this->ownerUserId();
        $result = $payments->record([
            'subscriberid' => $id, 'paidamount' => $paid, 'discount' => $discount, 'comment' => (string) $request->input('comment'),
            'trantype' => $trantype, 'othermobile' => $other,
        ], $agentId, $owner);

        Log::info('payment recorded', ['payment' => $result['id'], 'customer' => $id, 'paid' => $paid, 'discount' => $discount, 'by' => session('userid'), 'usertype' => $this->usertype()]);
        $sms->sendPayment($result['id'], $owner);   // no-op unless SMS_SEND_ENABLED=true; failure never affects the payment

        return response()->json(['message' => 'Your transaction id is '.$result['id'].' Please keep this for future references,', 'status' => 'success']);
    }

    // ================================================================ part 2: ledger, miscellaneous, bulk, expenses

    private function needMenu(int $id): void
    {
        abort_unless(in_array($id, $this->menus->menuList()), 403);
    }

    private function page(string $view, array $data = [])
    {
        return view($view, $data + ['menulist' => $this->menus->menuList(), 'network_list' => $this->menus->networkList()]);
    }

    /** Customers of the current LCO (id, name, mobile, lco id) for the dropdown screens. */
    private function lcoCustomers(bool $excludeInvisible = true): array
    {
        $q = DB::table('m_customer')->where('lcoCode', (string) session('lcoCode'))->orderBy('customer_name');
        if ($excludeInvisible) {
            $q->where('status', '!=', 2);
        }

        return $q->get(['id', 'customer_name', 'mobile', 'lco_customer_id'])->map(fn ($r) => (array) $r)->all();
    }

    public function ledger()
    {
        return $this->page('admin.payment.ledger', ['customer' => $this->lcoCustomers(false), 'package' => DB::table('m_package')->get()->map(fn ($r) => (array) $r)->all()]);
    }

    public function ledgerResult(Request $request)
    {
        $id = $request->input('subscriberid');
        if ($id === null || ! $this->canAccess($id)) {
            return response('');
        }
        $rows = DB::table('subscriberpayments')->where('subscriberid', (string) $id)->orderByDesc('id')->get();

        return response(view('admin.payment.ledger_result', ['rows' => $rows, 'canDelete' => in_array(13, $this->menus->menuList())])->render());
    }

    /** Removes the newest ledger row of a customer (menu 13 only; the old code only hid the button). */
    public function deleteVoucher(Request $request, LedgerService $ledger)
    {
        if (! $request->has('voucherid')) {
            return response('');
        }
        if (! in_array(13, $this->menus->menuList())) {
            Log::channel('audit')->warning('voucher delete refused: no permission', ['by' => session('userid'), 'voucher' => $request->input('voucherid'), 'ip' => $request->ip()]);

            return response('Failed');
        }

        return response($ledger->deleteVoucher($request->input('voucherid'), (string) session('lcoCode'), $this->usertype()));
    }

    public function miscellaneous()
    {
        $this->needMenu(9);

        return $this->page('admin.payment.miscellaneous', [
            'miscellaneousheads' => DB::table('m_dropdown')->where('type', 3)->where('status', 1)->get()->map(fn ($r) => (array) $r)->all(),
            'subscriber' => $this->lcoCustomers(),
        ]);
    }

    public function bulkoperations()
    {
        $this->needMenu(9);

        return $this->page('admin.payment.bulkoperations', [
            'miscellaneousheads' => DB::table('m_dropdown')->where('type', 3)->where('status', 1)->get()->map(fn ($r) => (array) $r)->all(),
            'subscriber' => $this->lcoCustomers(),
        ]);
    }

    /** Transaction/miscellaneous_report/{0|1}: 0 = charges added (type 3), 1 = payments against misc heads (is_misc=1). */
    public function miscellaneousReport(Request $request, string $type)
    {
        $from = (string) $request->input('drsearchfromdate', '');
        $to = (string) $request->input('drsearchtodate', '');
        $head = (string) $request->input('head', '');
        if (! in_array($type, ['0', '1'], true) || $from === '' || $to === '' || ($f = strtotime($from)) === false || ($t = strtotime($to)) === false) {
            return response()->json([]);
        }
        $fdate = (string) (strtotime(date('Y-m-d', $f)) * 1000);
        $tdate = (string) (strtotime(date('Y-m-d', $t)) * 1000 + 24 * 60 * 60 * 1000);
        $head = $head === '0' ? '' : $head;

        $q = DB::table('subscriberpayments as E')->leftJoin('m_customer as b', 'b.id', '=', 'E.subscriberid')
            ->whereIn('E.subscriberid', DB::table('m_customer')->select(DB::raw('CAST(id AS CHAR)'))->where('lcoCode', (string) session('lcoCode')))
            ->where('E.timestamp', '>', $fdate)->where('E.timestamp', '<', $tdate)   // string comparison, as before (same-length ms strings)
            ->where('E.description', 'like', '%'.addcslashes($head, '%_\\').'%')->orderByDesc('E.timestamp');
        if ($type === '0') {
            $q->where('E.transactiontype', '3')->selectRaw('E.description, E.subscriptioncharge as amount, b.customer_name, b.lco_customer_id, E.timestamp');
        } else {
            $q->where('E.is_misc', 1)->where('E.transactiontype', '1')->selectRaw('E.description, E.paidamount as amount, b.customer_name, b.lco_customer_id, E.timestamp');
        }
        $out = [];
        foreach ($q->get() as $r) {
            $out[] = [
                'customer_name' => $r->customer_name, 'description' => $r->description, 'lco_customer_id' => $r->lco_customer_id,
                'amount' => $r->amount, 'date' => $r->timestamp !== '' ? date('d-m-Y h:i a', (int) ($r->timestamp / 1000)) : '',
            ];
        }

        return response()->json($out);
    }

    private function chargeInput(Request $request): ?array
    {
        foreach (['subscriberid', 'headid', 'date', 'amount', 'comment'] as $k) {
            if (! $request->has($k)) {
                return null;
            }
        }

        return [(string) $request->input('subscriberid'), $request->input('headid'), trim((string) $request->input('amount')), (string) $request->input('comment')];
    }

    private function chargeError(string $m)
    {
        return response()->json(['message' => $m, 'status' => 'error']);
    }

    public function addMiscellaneous(Request $request, LedgerService $ledger)
    {
        $this->needMenu(9);
        if (($in = $this->chargeInput($request)) === null) {
            return response('');
        }
        [$customer, $head, $amount, $comment] = $in;
        if (! $this->canAccess($customer) || ($desc = $ledger->description($head, $comment)) === null) {
            return $this->chargeError('Invalid customer or head');
        }
        if (! preg_match('/^-?\d{1,8}(\.\d{1,2})?$/', $amount)) {
            return $this->chargeError('Invalid amount');
        }
        $agent = $this->usertype() === '3' ? session('userid') : 0;
        $r = $ledger->chargeOne($customer, $desc, $amount, $agent, $this->ownerUserId(), false);
        Log::channel('audit')->info('miscellaneous charge', ['payment' => $r['id'], 'customer' => $customer, 'amount' => $amount, 'by' => session('userid')]);
        app(SmsService::class)->sendPayment($r['id'], $this->ownerUserId());

        return response()->json(['message' => 'Success', 'status' => 'success']);
    }

    /** subscriberid = "all" charges every customer of the LCO (status != 2); otherwise one customer. */
    public function addBulkMiscellaneous(Request $request, LedgerService $ledger)
    {
        $this->needMenu(9);
        if (($in = $this->chargeInput($request)) === null) {
            return response('');
        }
        [$customer, $head, $amount, $comment] = $in;
        if (($desc = $ledger->description($head, $comment)) === null) {
            return $this->chargeError('Invalid head');
        }
        if (! preg_match('/^-?\d{1,8}(\.\d{1,2})?$/', $amount)) {
            return $this->chargeError('Invalid amount');
        }
        $agent = $this->usertype() === '3' ? session('userid') : 0;
        try {
            if ($customer === 'all') {
                abort_if((string) session('lcoCode') === '', 403);
                $ledger->chargeAll((string) session('lcoCode'), $desc, $amount, $agent, $this->ownerUserId());
            } else {
                if (! $this->canAccess($customer)) {
                    return $this->chargeError('Invalid customer');
                }
                $r = $ledger->chargeOne($customer, $desc, $amount, $agent, $this->ownerUserId(), true);
                Log::channel('audit')->info('miscellaneous charge (bulk screen)', ['payment' => $r['id'], 'customer' => $customer, 'amount' => $amount, 'by' => session('userid')]);
            }
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('bulk miscellaneous failed', ['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);

            return $this->chargeError('An error occurred');
        }

        return response()->json(['message' => 'Success', 'status' => 'success']);
    }

    // ---------------------------------------------------------------- expenses

    public function expenses()
    {
        $this->needMenu(9);
        $uid = session('userid');

        return $this->page('admin.payment.expenses', [
            'expenseheader' => DB::table('expenseheader')->where('created_by', $uid)->get()->map(fn ($r) => (array) $r)->all(),
            'expensedata' => DB::table('expenses as E')->leftJoin('expenseheader as EH', 'E.expenseid', '=', 'EH.id')->where('E.created_by', $uid)
                ->orderByDesc('E.created_on')->get(['E.amount', 'E.expensedate', 'E.remarks', 'EH.title'])->map(fn ($r) => (array) $r)->all(),
        ]);
    }

    public function expensesAdd(Request $request)
    {
        $this->needMenu(9);
        foreach (['expenseheadid', 'expensedate', 'expenseamount', 'expensecomment'] as $k) {
            if (! $request->has($k)) {
                return response('');
            }
        }
        $uid = session('userid');
        $amount = trim((string) $request->input('expenseamount'));
        $date = (string) $request->input('expensedate');
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        if (! DB::table('expenseheader')->where('id', $request->input('expenseheadid'))->where('created_by', $uid)->exists()
            || ! preg_match('/^\d{1,8}(\.\d{1,2})?$/', $amount) || ! $d || $d->format('Y-m-d') !== $date) {
            return response('Invalid expense');
        }
        DB::table('expenses')->insert([
            'expenseid' => (string) $request->input('expenseheadid'), 'amount' => $amount, 'remarks' => (string) $request->input('expensecomment'),
            'created_on' => date('Y-m-d H:i:s'), 'created_by' => $uid, 'expensedate' => $date,
        ]);

        return response('Expense Added');
    }

    public function expenseheaderManagement(Request $request, ?string $state = null, ?string $p1 = null)
    {
        $this->needMenu(9);
        $crud = \App\Crud\Crud::make($request, 'Transaction/expenseheader_management')
            ->table('expenseheader')->subject('Expense Head')->unsetDelete()
            ->columns('title')->requiredFields('title', 'status')
            ->where('created_by', session('userid'))
            ->callbackBeforeInsert(function (array $p) {
                $p['created_by'] = session('userid');
                $p['created_on'] = date('Y-m-d H:i:s');

                return $p;
            })
            ->changeFieldType('status', 'true_false')
            ->fieldType('created_on', 'invisible')->fieldType('created_by', 'invisible')->fieldType('type', 'invisible');
        $r = $crud->run($state, $p1);

        return is_array($r) ? $this->page('admin.crud', ['crud' => $r]) : $r;
    }

    // ================================================================ part 3a: GSoft monthly bills + UPIGateway

    /** Transaction/monthly_bills: the LCO's platform invoices (also reachable by a blocked LCO: it is the payment screen). */
    public function monthlyBills()
    {
        return $this->page('admin.payment.monthly_bills', ['payment_type_list' => DB::table('gsoft_payment_types')->where('status', 1)->get()->map(fn ($r) => (array) $r)->all()]);
    }

    public function monthlyBillsData(Request $request, GsoftBillingService $billing)
    {
        $from = $request->has('sfromdate') ? (string) $request->input('sfromdate') : null;
        $to = $request->has('stodate') ? (string) $request->input('stodate') : null;
        $data = $billing->screen($from, $to, (string) session('lcoCode'), session('userid'));

        return response(view('admin.payment.monthly_bills_data', ['data' => $data])->render());
    }

    public function monthlyBillsView(Request $request, GsoftBillingService $billing)
    {
        if (! $request->has('current_month')) {
            return response('');
        }
        $bill = $billing->billFor((string) session('lcoCode'), (string) $request->input('current_month'));
        if (! $bill) {
            return response('No bill found');
        }
        $names = [0 => 'Pending', 1 => 'Initiated', 2 => 'Success', 3 => 'Failed', 4 => 'Free'];

        return response(view('admin.payment.monthly_bills_view', ['bill' => $bill, 'status' => $names[(int) $bill->bill_status] ?? ''])->render());
    }

    /**
     * Pay dialog. Type 4 = UPIGateway (returns JSON with the payment URL); other types show their instructions/QR.
     * The amount always comes from the bill, not from the browser.
     */
    public function paymentTypeView(Request $request, GsoftBillingService $billing)
    {
        if (! $request->has('payment_type') || ! $request->has('amount')) {
            return response('');
        }
        $type = DB::table('gsoft_payment_types')->where('id', $request->input('payment_type'))->first();
        $bill = DB::table('gsoft_billing')->where('id', (int) $request->input('bill_id'))->where('lco_code', (string) session('lcoCode'))->first();
        if (! $type || ! $bill) {
            return response('Payment error');
        }
        if ((int) $type->id === 4) {
            $r = $billing->startUpiPayment($bill->id, (string) session('lcoCode'), session('userid'), session('network_id'));
            $out = $r['status']
                ? ['payment_url' => $r['payment_url'], 'client_txn_id' => $r['client_txn_id'], 'date' => $r['date'], 'status' => true, 'msg' => $r['msg']]
                : ['status' => false, 'msg' => 'Payment link generation failed'];

            return response()->json($out);
        }
        $qr = str_replace('%amount%', (string) $bill->invoice_total, (string) $type->qrcode_url);
        $html = '<div class="table-responsive"><table class="table table-bordered">
							<tr><td>Payment type</td><td>'.e($type->name).'</td></tr>
							<tr><td>URL</td><td><a href="'.e($type->url).'" target="_blank" rel="noopener">'.e($type->url).'</a></td></tr>
						</table></div>';
        if ($type->image_url !== '' && $type->image_url !== null) {
            $html .= '<div style="text-align:center"><img style="height: 320px;" src="'.e(url('assets/img/'.rawurlencode((string) $type->image_url))).'"/></div>';
        }
        if ($qr !== '') {
            $html .= '<div style="text-align:center"><img src="https://chart.googleapis.com/chart?chs=250x250&cht=qr&chl='.e(rawurlencode($qr)).'" title="Link to Google.com" /></div>
						<div style="text-align:center">
							<span>Scan QRCODE to pay, After payment please click below button</span>
							<button type="button" style="margin-top: 20px;" onClick="paymentCompletedClick();return false;" class="form-control btn-warning">Payment completed</button>
						</div>';
        }

        return response($html);
    }

    /** "Payment completed" for a manual payment type: the bill becomes Initiated and waits for the admin to confirm. */
    public function initiatePayment(Request $request, GsoftBillingService $billing)
    {
        if (! $request->has('current_month') || ! $request->has('payment_type')) {
            return response('');
        }
        $billing->initiate((string) session('lcoCode'), (string) $request->input('current_month'), (int) $request->input('payment_type'));
        Log::channel('audit')->info('gsoft bill marked initiated', ['lco' => session('lcoCode'), 'month' => $request->input('current_month'), 'type' => $request->input('payment_type'), 'by' => session('userid')]);

        return response('');
    }

    // ================================================================ part 3b: invoice history, approval

    private function lcoCustomerIds(string $where = ''): array
    {
        $q = DB::table('m_customer')->where('lcoCode', (string) session('lcoCode'));
        if ($where === 'sms') {
            $q->where('sms', 1);
        } elseif ($where === 'visible') {
            $q->where('status', '!=', 2);
        }

        return $q->pluck('id')->map(fn ($v) => (string) $v)->all();
    }

    private function crudPage(\App\Crud\Crud $crud, ?string $state, ?string $p1, string $prefixHtml = '')
    {
        $r = $crud->run($state, $p1);
        if (! is_array($r)) {
            return $r;
        }
        $r['output'] = $prefixHtml.$r['output'];

        return $this->page('admin.crud', ['crud' => $r]);
    }

    /** Transaction/import_history: one row per imported invoice month with counts (links open the status-wise lists). */
    public function importHistory(Request $request, InvoiceService $invoices, ?string $state = null, ?string $p1 = null)
    {
        $admin = $this->usertype() === '1';
        $ids = $admin ? null : $this->lcoCustomerIds();
        $smsIds = $admin ? null : $this->lcoCustomerIds('sms');
        $visibleIds = $admin ? null : $this->lcoCustomerIds('visible');
        $join = DB::table('m_user')->where('id', session('userid'))->value('join_date');
        $memo = [];
        $counts = function ($row) use (&$memo, $invoices, $ids, $smsIds, $visibleIds) {
            return $memo[$row->current_month] ??= $invoices->monthCounts($row->current_month, $ids, $smsIds, $visibleIds);
        };
        $link = fn ($path, $month, $n) => "<a target='_blank' href='".e(url('transaction/'.$path.'/'.$month.'/'.$n[1]))."'>".$n[0].'</a>';
        $user = DB::table('m_user')->where('id', session('userid'))->first(['sms', 'smscount']);

        $crud = \App\Crud\Crud::make($request, 'Transaction/import_history')
            ->table('invoice_history')->primaryKey('current_month')->subject('Import History')
            ->where('current_month', $join, '>=')->orderBy('current_month', 'desc')
            ->unsetDelete()->unsetClone()->unsetRead()->unsetAdd()->unsetEdit()
            ->callbackColumn('pending', fn ($v, $row) => $link('import_status_wise_history', $row->current_month, [$counts($row)['pending'], 0]))
            ->callbackColumn('completed', fn ($v, $row) => $link('import_status_wise_history', $row->current_month, [$counts($row)['completed'], 1]))
            ->callbackColumn('total', fn ($v, $row) => $link('import_status_wise_history', $row->current_month, [$counts($row)['total'], 2]))
            ->callbackColumn('sms_send', fn ($v, $row) => $link('import_sms_status_wise_history', $row->current_month, [$counts($row)['sms_send'], 2]))
            ->callbackColumn('sms_in_queue', fn ($v, $row) => $link('import_sms_status_wise_history', $row->current_month, [$counts($row)['sms_queue'], 1]))
            ->callbackColumn('sms_pending', function ($v, $row) use ($counts, $link, $user) {
                $n = $counts($row)['sms_pending'];
                $html = $link('import_sms_status_wise_history', $row->current_month, [$n, 0]);
                if ($user && (int) $user->sms === 1 && (float) $user->smscount > 0 && $n > 0) {
                    $html .= '<br><button><a href='.e(url('import/send_sms_to_pending_customers').'/'.$row->current_month.'/'.session('userid')).'>Send&nbsp;SMS</a></button>';
                }

                return $html;
            })
            ->columns('yearmonth', 'pending', 'completed', 'sms_pending', 'sms_in_queue', 'sms_send', 'total')
            ->displayAs('yearmonth', 'Year Month')->displayAs('total', 'Total invoices')->displayAs('completed', 'Invoice generated')->displayAs('pending', 'Invoice pending');

        return $this->crudPage($crud, $state, $p1);
    }

    private function invoiceCrud(Request $request, string $base, string $yearmonth, int $status, bool $smsView): \App\Crud\Crud
    {
        $admin = $this->usertype() === '1';
        $crud = \App\Crud\Crud::make($request, $base.'/'.$yearmonth.'/'.$status)
            ->table('m_ezybill_invoices')->subject($smsView ? 'Sms History' : 'Import History')
            ->where('current_month', $yearmonth)
            ->orderBy('total_amount', 'desc')
            ->unsetDelete()->unsetClone()->unsetRead()->unsetAdd()->unsetEdit()
            ->relation('customer_id', 'm_customer', '{customer_name} ({lco_customer_id})')
            ->displayAs('customer_id', 'Customer');
        if ($smsView) {
            $crud->where('sms_status', $status);
            if (! $admin) {
                $crud->where('status', 1);
            }
        } elseif ($status !== 2) {
            $crud->where('status', $status);
        }
        if (! $admin) {
            $crud->whereIn('customer_id', $this->lcoCustomerIds());
        }

        return $crud;
    }

    /** Transaction/import_status_wise_history/{yearmonth}/{status}: invoices with the system-proposed days/amount and Approve/Edit buttons. */
    public function importStatusWiseHistory(Request $request, InvoiceService $invoices, string $yearmonth, string $status, ?string $state = null, ?string $p1 = null)
    {
        abort_unless(preg_match('/^\d{4}-\d{2}-\d{2}$/', $yearmonth) && in_array($status, ['0', '1', '2'], true), 404);
        $crud = $this->invoiceCrud($request, 'Transaction/import_status_wise_history', $yearmonth, (int) $status, false)
            ->columns('customer_id', 'package', 'serial_number', 'vc_number', 'bill_amount', 'discount_amount', 'tax_amount', 'mso_share', 'lco_share', 'total_amount', 'noofdays')
            ->displayAs('noofdays', 'System calculated days and amount')
            ->callbackColumn('noofdays', function ($v, $row) use ($invoices) {
                $p = $invoices->proposal($row);
                $html = '<span style="color:'.$p['colour'].'">'.$p['days'].' days<br>'.$p['amount'].' Rs</span><div style="display: flex">';
                if ((int) $row->status !== 1) {
                    $args = [$row->id, $row->customer_id, $p['amount'], $p['days']];
                    $html .= '<button><a href="#" onclick="return approveInvoice('.implode(',', array_map(fn ($a) => "'".e((string) $a)."'", $args)).')">Approve</a></button>&nbsp;'
                        .'<button><a href="'.e(url('transaction/edit_bill/'.implode('/', $args))).'">Edit&nbsp;Bill</a></button>';
                }

                return $html.'</div>';
            });
        $script = '<script>
	function approveInvoice(id, customer, amount, days) {
		alertify.confirm("Approve this bill?", function (e) {
			if (!e) { return; }
			$("body").addClass("loading");
			$.ajax({ url: "'.url('Transaction/approve').'/" + id + "/" + customer + "/" + amount + "/" + days, type: "POST", data: {"referer": "1"},
				success: function () { window.location.reload(); },
				error: function () { alertify.error("Failed"); $("body").removeClass("loading"); } });
		});
		return false;
	}
</script>';

        return $this->crudPage($crud, $state, $p1, $script);
    }

    public function importSmsStatusWiseHistory(Request $request, string $yearmonth, string $status, ?string $state = null, ?string $p1 = null)
    {
        abort_unless(preg_match('/^\d{4}-\d{2}-\d{2}$/', $yearmonth) && in_array($status, ['0', '1', '2'], true), 404);
        $crud = $this->invoiceCrud($request, 'Transaction/import_sms_status_wise_history', $yearmonth, (int) $status, true)->columns('customer_id', 'sms');

        return $this->crudPage($crud, $state, $p1);
    }

    /** POST Transaction/approve/{invoice}/{customer}/{amount}/{days}: posts the invoice charge to the customer's ledger. */
    public function approve(Request $request, InvoiceService $invoices, string $primaryId, string $customerId, string $amount, string $days)
    {
        $result = $invoices->approve($primaryId, $customerId, $amount, (int) $days, (string) session('lcoCode'), $this->usertype() === '1');
        if ($result === 'denied') {
            abort(403);
        }
        if ($request->input('referer', '') !== '') {
            return response('success');
        }

        return redirect()->back(fallback: url('transaction/import_history'));
    }

    /** GET Transaction/edit_bill/{invoice}/{customer}/{amount}/{days}: lets the LCO change the proposed amount/days before approving. */
    public function editBill(Request $request, string $primaryId, string $customerId, string $amount, string $days)
    {
        $customer = DB::table('m_customer')->where('id', $customerId);
        if ($this->usertype() !== '1') {
            $customer->where('lcoCode', (string) session('lcoCode'));
        }
        $customer = $customer->first(['id', 'customer_name', 'mobile']);
        $invoice = DB::table('m_ezybill_invoices')->where('id', $primaryId)->where('customer_id', $customerId)->first();
        abort_unless($customer && $invoice, 404);

        $referer = url('/transaction/import_history');
        $prev = (string) $request->headers->get('referer', '');
        if ($prev !== '' && parse_url($prev, PHP_URL_HOST) === $request->getHost()) {
            $referer = $prev;   // only ever go back to this site (the old code trusted any Referer)
        }

        return $this->page('admin.payment.edit_bill', [
            'invoice' => [(array) $invoice], 'customer' => [(array) $customer],
            'current_bill_amount' => $amount, 'no_of_days' => $days, 'referer' => $referer,
        ]);
    }
}
