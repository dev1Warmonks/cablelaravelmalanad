<?php

namespace App\Http\Controllers;

use App\Services\MenuService;
use App\Services\SubscriberListService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Subscriber (usertype 1 and 2). Ported: lists, view/edit/update, SMS flag, mobile update, sub-area lookup.
 * Ownership: an LCO (usertype 2) may only touch customers of its own lcoCode. The old code checked this on the
 * view/edit pages but NOT on update_subscriber / updateSMSstat / update_combo_plan / get_subscriber_details,
 * so any LCO could edit or read any customer by id.
 * Bill generation endpoints (generate_network_bill, ...) are ported with the billing module.
 */
class SubscriberController extends Controller
{
    public function __construct(private MenuService $menus, private SubscriberListService $lists)
    {
    }

    private function isAdmin(): bool
    {
        return (string) session('usertype') === '1';
    }

    private function customerScope()
    {
        $q = DB::table('m_customer');
        if (! $this->isAdmin()) {
            $q->where('m_customer.lcoCode', session('lcoCode'));
        }

        return $q;
    }

    private function owns($id): bool
    {
        return is_scalar($id) && is_numeric($id) && $this->customerScope()->where('m_customer.id', $id)->exists();
    }

    private function page(string $view, array $data = [])
    {
        return view($view, $data + [
            'menulist' => $this->menus->menuList(),
            'network_list' => $this->menus->networkList(),
        ]);
    }

    private function areas(): array
    {
        return DB::table('m_area')->where('created_by', session('userid'))->where('status', 1)->get(['id', 'areaname'])->map(fn ($r) => (array) $r)->all();
    }

    private function header(string $report): array
    {
        $n = DB::table('m_network')->where('id', session('network_id'))->first(['network_name', 'lcoCode', 'address', 'phone']);

        return [
            'network_name' => $n->network_name ?? '', 'lcoCode' => $n->lcoCode ?? '', 'address' => $n->address ?? '',
            'phone' => $n->phone ?? '', 'report_name' => $report,
        ];
    }

    private function hasGenerateBill(): bool
    {
        $id = DB::table('m_main_menus')->where('menu_name', 'generatebill')->value('menu_id');

        return $this->isAdmin() || ($id !== null && in_array($id, $this->menus->menuList()));
    }

    private function comboPlans(): array
    {
        return DB::table('m_combo_plan')->where('status', 1)->get(['id', 'name', 'rate'])->map(fn ($r) => (array) $r)->all();
    }

    // ---------------------------------------------------------------- lists

    /** Subscriber/index/{type}: "list" and "Mlist" return JSON, otherwise the page. */
    public function index(Request $request, ?string $type = null)
    {
        $f = [$request->input('filteramount', 0), $request->input('area', -1), $request->input('subarea', -1), $request->input('status', -1), $request->input('paidstat', -1)];
        $f[0] = $f[0] === '' ? 0 : $f[0];
        $f[4] = $f[4] === '' ? -1 : $f[4];

        return match ($type) {
            'list' => response()->json($this->lists->subscriberList(...$f)),
            'Mlist' => response()->json($this->lists->mList(...$f)),
            default => $this->page('admin.subscriber.list', [
                'header' => $this->header('Subscriber List'), 'arealist' => $this->areas(), 'comboplan' => $this->comboPlans(),
                'has_generate_bill' => $this->hasGenerateBill(),
            ]),
        };
    }

    public function inactive(Request $request, ?string $type = null)
    {
        $amount = $request->input('filteramount', 0);
        $paid = $request->input('paidstat', -1);
        $args = [$amount === '' ? 0 : $amount, $request->input('area', -1), $request->input('subarea', -1), $request->input('status', 0), $paid === '' ? -1 : $paid];

        if ($type === 'list') {
            return response()->json($this->lists->subscriberList(...$args));
        }

        return $this->page('admin.subscriber.inactive', [
            'header' => $this->header('Inactive Subscriber List'), 'arealist' => $this->areas(), 'comboplan' => $this->comboPlans(),
            'has_generate_bill' => $this->hasGenerateBill(),
        ]);
    }

    public function all(Request $request, ?string $type = null)
    {
        abort_unless($this->isAdmin(), 403);
        if ($type === 'list') {
            return response()->json($this->lists->allList($request->input('network'), $request->input('area', -1), $request->input('subarea', -1), $request->input('status', -1)));
        }

        return $this->page('admin.subscriber.all', [
            'arealist' => $this->areas(),
            'all_network_list' => DB::table('m_network')->where('status', 1)->get(['network_name', 'id', 'lcoCode'])->map(fn ($r) => (array) $r)->all(),
            'has_generate_bill' => $this->hasGenerateBill(),
        ]);
    }

    public function mlistPage()
    {
        return $this->page('admin.subscriber.mlist', ['header' => $this->header('Subscriber List'), 'arealist' => $this->areas()]);
    }

    public function nonPaidThisMonth()
    {
        return $this->page('admin.subscriber.nonpaid', ['header' => $this->header('Subscriber List'), 'arealist' => $this->areas()]);
    }

    // ---------------------------------------------------------------- one customer

    public function view(string $id)
    {
        abort_unless($this->owns($id), 404);
        $rows = DB::table('m_customer as S')
            ->leftJoin('m_area as A', 'A.id', '=', 'S.area_id')
            ->leftJoin('m_sub_area as B', 'B.id', '=', 'S.subarea_id')
            ->where('S.id', $id)
            ->get(['S.*', 'A.areaname as area', 'B.areaname as subarea'])->map(fn ($r) => (array) $r)->all();

        return $this->page('admin.subscriber.view', ['subscriber' => $rows]);
    }

    public function edit(string $id)
    {
        abort_unless($this->owns($id), 404);
        $rows = DB::table('m_customer as S')
            ->leftJoin('m_area as A', 'A.id', '=', 'S.area_id')
            ->leftJoin('m_broadband_customer as B', 'B.customer_id', '=', 'S.id')
            ->where('S.id', $id)
            ->get(['S.*', 'A.areaname as area', 'B.broadband_plan'])->map(fn ($r) => (array) $r)->all();
        $latest = DB::table('m_ezybill_invoices')->where('customer_id', $id)->whereNotNull('package')->where('package', '!=', '')->orderByDesc('id')->value('package');

        return $this->page('admin.subscriber.edit', [
            'subscriber' => $rows,
            'broadplans' => DB::table('m_broadband_plan')->get(['id', 'name'])->map(fn ($r) => (array) $r)->all(),
            'comboplan' => DB::table('m_combo_plan')->where('status', 1)->get(['id', 'name'])->map(fn ($r) => (array) $r)->all(),
            'latest_package' => $latest ?? '',
            'arealist' => $this->areas(), 'subarealist' => [],
        ]);
    }

    public function update(Request $request)
    {
        foreach (['subscriberid', 'mobile', 'area', 'subarea', 'status', 'appenable', 'smsenable'] as $k) {
            if (! $request->has($k)) {
                return response('Failed');
            }
        }
        $id = $request->input('subscriberid');
        if (! $this->owns($id)) {
            return response('Failed');
        }
        $lco = trim((string) $request->input('lco_customer_id', ''));
        $newid = trim((string) $request->input('newid', ''));
        if ($lco === '') {
            return response('LCO Customer ID is required');
        }
        if ($newid === '') {
            return response('NUID is required');
        }
        if (DB::table('m_customer')->where('lco_customer_id', $lco)->where('id', '!=', $id)->exists()) {
            return response('LCO Customer ID already exists');
        }
        if (DB::table('m_customer')->where('newid', $newid)->where('id', '!=', $id)->exists()) {
            return response('NUID already exists');
        }
        foreach (['area', 'subarea', 'status', 'appenable', 'smsenable'] as $k) {
            if (! is_numeric($request->input($k))) {
                return response('Failed');
            }
        }

        DB::transaction(function () use ($request, $id, $lco, $newid) {
            DB::table('m_customer')->where('id', $id)->update([
                'mobile' => (string) $request->input('mobile'),
                'status' => $request->input('status'),
                'area_id' => $request->input('area'),
                'enable_app' => $request->input('appenable'),
                'subarea_id' => $request->input('subarea'),
                'combo_plan' => $request->input('comboplan'),
                'sms' => $request->input('smsenable'),
                'newid' => $newid,
                'lco_customer_id' => $lco,
                'amc_status' => $request->input('amc_status'),
                'amc_end_date' => $request->input('amcenddate'),
            ]);
            $plan = $request->input('broadbandplan', '');
            if ($plan !== '' && $plan !== null) {
                if (DB::table('m_broadband_customer')->where('customer_id', $id)->count() === 1) {
                    DB::table('m_broadband_customer')->where('customer_id', $id)->update(['broadband_plan' => $plan]);
                } else {
                    DB::table('m_broadband_customer')->insert(['customer_id' => $id, 'status' => 1, 'broadband_plan' => $plan]);
                }
            }
        });

        return response('Success');
    }

    public function updateSmsStat(Request $request)
    {
        if (! $request->has('customer_id') || ! $request->has('smsStat') || ! $this->owns($request->input('customer_id')) || ! is_numeric($request->input('smsStat'))) {
            return response('Failed');
        }
        DB::table('m_customer')->where('id', $request->input('customer_id'))->update(['sms' => $request->input('smsStat')]);

        return response('Success');
    }

    public function updateMobile(Request $request)
    {
        $id = $request->input('customer_id');
        if (! $request->has('customer_id') || ! $request->has('new_number')) {
            return response('Failed');
        }
        $exists = DB::table('m_customer')->where('id', $id)->where('lcoCode', session('lcoCode'))->count() === 1;
        if (! $exists) {
            return response('Failed');
        }
        DB::table('m_customer')->where('id', $id)->update(['mobile' => (string) $request->input('new_number')]);

        return response('Updated');
    }

    public function updateComboPlan(Request $request)
    {
        $id = $request->input('customer_id');
        if ($id && $this->owns($id)) {
            DB::table('m_customer')->where('id', $id)->update(['combo_plan' => $request->input('combo_plan')]);

            return response()->json(['status' => true, 'message' => 'Package updated successfully']);
        }

        return response()->json(['status' => false, 'message' => 'Invalid request']);
    }

    public function subArea(Request $request)
    {
        if (! $request->has('area_id')) {
            return response('');
        }
        $q = DB::table('m_sub_area')->where('area_id', $request->input('area_id'));
        if (! $this->isAdmin()) {
            $q->whereIn('area_id', DB::table('m_area')->select('id')->where('created_by', session('userid')));
        }

        return response()->json(['success' => true, 'data' => $q->get(['id', 'areaname'])->all()]);
    }

    /** Subscriber::subscriber_management: add / edit customer (Grocery_CRUD screen). */
    public function management(Request $request, ?string $state = null, ?string $p1 = null)
    {
        $uid = session('userid');
        $lco = (string) session('lcoCode');
        abort_if($lco === '', 403);   // the screen is per network; admin has none

        $subscribers = DB::table('m_customer')->where('lcoCode', $lco)->where('status', '!=', 2)->orderBy('customer_name')->get(['id', 'customer_name', 'mobile', 'lco_customer_id'])->all();
        $dropdown = function ($value = null) use ($subscribers) {
            $html = '<select name="subscriber_dropdown" id="subscriber_dropdown" class="form-control select2"><option value="0">- Select -</option>';
            foreach ($subscribers as $row) {
                $sel = (string) $value === (string) $row->id ? 'selected' : '';
                $html .= '<option value="'.e($row->id).'" '.$sel.'>'.e($row->customer_name.' ('.$row->mobile.') '.$row->lco_customer_id).'</option>';
            }

            return $html.'</select>';
        };
        $amcDate = function ($v) {
            $d = \DateTime::createFromFormat('d/m/Y', (string) $v);

            return $d ? $d->format('Y-m-d') : null;
        };
        $listUrl = url('Subscriber/subscriber_management');

        $formFields = ['customer_id', 'caf_number', 'customer_name', 'lco_customer_id', 'area_id', 'subarea_id', 'mobile', 'billing_address',
            'opening_balance', 'status', 'serial_number', 'vc_number', 'newid', 'sms', 'combo_plan', 'amc_status', 'amc_end_date', 'c_type',
            'lcoCode', 'created_by', 'created_on', 'updated_by', 'updated_on'];

        $crud = \App\Crud\Crud::make($request, 'Subscriber/subscriber_management')
            ->table('m_customer')->primaryKey('id')->subject('Customer')
            ->where('lcoCode', $lco)
            ->unsetDelete()->unsetRead()->unsetClone()
            ->requiredFields('customer_name', 'mobile', 'status', 'newid', 'lco_customer_id')
            ->rules('newid', 'NUID', 'trim|required')->rules('lco_customer_id', 'LCO Customer ID', 'trim|required')
            ->uniqueFields('newid', 'lco_customer_id')
            ->columns('customer_id', 'subarea_id', 'lco_customer_id', 'caf_number', 'customer_name', 'mobile', 'billing_address', 'status', 'subscriber_dropdown')
            ->displayAs('customer_id', 'Customer ID (Same as EZYBILL)')->displayAs('lco_customer_id', 'LCO Customer ID')
            ->displayAs('subarea_id', 'Sub Area')->displayAs('caf_number', 'CAF Number (Same as EZYBILL)')
            ->displayAs('subscriber_dropdown', 'Select Subscriber')->displayAs('newid', 'NUID')->displayAs('combo_plan', 'Combo Plan')
            ->relation('area_id', 'm_area', 'areaname', ['status' => 1, 'created_by' => $uid])
            ->relation('subarea_id', 'm_sub_area', 'areaname', fn ($q) => $q->where('r.status', 1)->whereIn('r.area_id', DB::table('m_area')->select('id')->where('created_by', $uid)))
            ->relation('combo_plan', 'm_combo_plan', 'name', ['status' => 1])
            ->fieldType('status', 'dropdown', ['1' => 'Active', '0' => 'Deactive'])
            ->changeFieldType('sms', 'true_false')->changeFieldType('newid', 'string')->unsetTexteditor('billing_address', 'newid')
            ->fieldType('caf_number', 'hidden')->fieldType('customer_id', 'hidden')->fieldType('created_on', 'hidden')
            ->fieldType('created_by', 'hidden')->fieldType('updated_on', 'hidden')->fieldType('updated_by', 'hidden')
            ->fieldType('lcoCode', 'hidden', $lco)
            ->fieldType('amc_end_date', 'date')
            ->fieldType('c_type', 'dropdown', ['1' => 'IPTV Customer', '2' => 'Broadband Customer', '3' => 'Cable TV'])->displayAs('c_type', 'Customer Type')
            ->fieldType('amc_status', 'dropdown', ['1' => 'Active', '0' => 'Inactive'])->displayAs('amc_status', 'AMC Status')->displayAs('amc_end_date', 'AMC End Date')
            ->callbackAddField('subscriber_dropdown', $dropdown)
            ->callbackEditField('subscriber_dropdown', fn ($value) => $dropdown($value))
            // add form: no "Select Subscriber" box; edit form: box first (same as the old code)
            ->fields($formFields, array_merge(['subscriber_dropdown'], $formFields))
            ->callbackBeforeInsert(function (array $post) use ($request, $amcDate, $uid, $lco, $listUrl) {
                foreach (['newid', 'lco_customer_id'] as $k) {
                    if (isset($post[$k])) {
                        $post[$k] = trim($post[$k]);
                    }
                }
                // Old behaviour: an insert carrying subscriber_dropdown updated that customer instead. Kept, but only for the LCO's own customers.
                $existing = (int) $request->input('subscriber_dropdown', 0);
                if ($existing > 0) {
                    if (! DB::table('m_customer')->where('id', $existing)->where('lcoCode', $lco)->exists()) {
                        return false;
                    }
                    $upd = $post;
                    $upd['updated_by'] = $uid;
                    $upd['updated_on'] = date('Y-m-d H:i:s');
                    $upd['amc_end_date'] = $amcDate($post['amc_end_date'] ?? '');
                    unset($upd['lcoCode'], $upd['created_by'], $upd['created_on'], $upd['customer_id'], $upd['caf_number']);
                    DB::table('m_customer')->where('id', $existing)->update(array_intersect_key($upd, array_flip(DB::getSchemaBuilder()->getColumnListing('m_customer'))));

                    return response()->json(['success' => true, 'success_message' => 'Subscriber updated successfully.', 'redirect' => $listUrl]);
                }
                $post['amc_end_date'] = $amcDate($post['amc_end_date'] ?? '');
                $post['customer_id'] = 'CUST'.time();
                $post['caf_number'] = 'CAF'.date('YmdHis');
                $post['lcoCode'] = $lco;
                $post['created_by'] = $uid;
                $post['created_on'] = date('Y-m-d H:i:s');

                return $post;
            })
            ->callbackAfterInsert(fn () => response()->json(['success' => true, 'success_message' => 'Subscriber added successfully.', 'redirect' => $listUrl]))
            ->callbackBeforeUpdate(function (array $post) use ($uid) {
                foreach (['newid', 'lco_customer_id'] as $k) {
                    if (isset($post[$k])) {
                        $post[$k] = trim($post[$k]);
                    }
                }
                unset($post['lcoCode']);   // a customer never changes network from this screen
                $post['updated_by'] = $uid;
                $post['updated_on'] = date('Y-m-d H:i:s');

                return $post;
            });

        $r = $crud->run($state, $p1);
        if (! is_array($r)) {
            return $r;
        }
        $r['output'] = '
			<a href="'.url('Subscriber/subscriber_management/add').'"
			class="btn btn-success"
			style="margin-bottom:10px;">
				<i class="fa fa-plus"></i> ADD SUBSCRIBER
			</a>
		'.$r['output'].view('admin.subscriber.management_js', ['subscriber_url' => url('Subscriber/get_subscriber_details')])->render();

        return $this->page('admin.crud', ['crud' => $r]);
    }

    public function details(Request $request)
    {
        $id = $request->input('id');
        if (! $this->owns($id)) {
            return response()->json([]);
        }
        $row = DB::table('m_customer')->where('id', $id)->first(['customer_id', 'caf_number', 'customer_name', 'lco_customer_id', 'area_id', 'subarea_id', 'mobile', 'billing_address', 'opening_balance', 'serial_number', 'vc_number', 'newid', 'sms', 'combo_plan', 'amc_status', 'amc_end_date', 'c_type', 'status']);

        return response()->json($row);
    }

    // ---------------------------------------------------------------- bill generation (buttons on the subscriber lists)

    /** Admin, or a user holding the "generatebill" menu (the same rule the lists use to show the buttons). */
    private function mayGenerateBills(): bool
    {
        return $this->hasGenerateBill();
    }

    private function billJson(array $payload)
    {
        return response()->json($payload);
    }

    /** POST Subscriber/generate_network_bill: bills every active customer of one network for the chosen month. */
    public function generateNetworkBill(Request $request, \App\Services\BillGenerationService $bills)
    {
        if (! $this->mayGenerateBills()) {
            return $this->billJson(['status' => false, 'message' => 'Unauthorized access']);
        }
        // An LCO can only bill its own network (the old code trusted the posted network, so any LCO with the menu could bill any network).
        $network = $this->isAdmin() ? (string) $request->input('network', '') : (string) session('lcoCode', '');
        if ($network === '') {
            $network = (string) session('lcoCode', '');
        }
        if ($network === '' || ! DB::table('m_network')->where('lcoCode', $network)->exists()) {
            return $this->billJson(['status' => false, 'message' => 'Please select a network']);
        }

        return $this->billJson($bills->generateForNetwork($network, (string) $request->input('bill_month', '')));
    }

    /** POST Subscriber/generate_selected_inactive_bills: a flat amount for the selected customers. */
    public function generateSelectedInactiveBills(Request $request, \App\Services\BillGenerationService $bills)
    {
        if (! $this->mayGenerateBills()) {
            return $this->billJson(['status' => false, 'message' => 'Unauthorized access']);
        }
        $ids = $request->input('customer_ids');
        $amount = trim((string) $request->input('amount', ''));
        $bad = 'Please enter a valid amount greater than 0 and select subscribers';
        if (! is_array($ids) || $ids === [] || ! preg_match('/^\d{1,7}(\.\d{1,2})?$/', $amount) || (float) $amount <= 0) {
            return $this->billJson(['status' => false, 'message' => $bad]);
        }
        $ids = array_values(array_filter($ids, fn ($i) => is_scalar($i) && ctype_digit((string) $i)));
        if ($ids === [] || count($ids) > 5000) {
            return $this->billJson(['status' => false, 'message' => $bad]);
        }
        // only customers this user may touch
        $ids = $this->customerScope()->whereIn('m_customer.id', $ids)->pluck('m_customer.id')->all();

        return $this->billJson($ids === [] ? ['status' => false, 'message' => 'No matching subscribers found'] : $bills->generateForSelected($ids, $amount, (string) $request->input('bill_month', '')));
    }

    /** POST Subscriber/delete_network_bill (administrator only). */
    public function deleteNetworkBill(Request $request, \App\Services\BillGenerationService $bills)
    {
        if (! $this->isAdmin()) {
            return $this->billJson(['status' => false, 'message' => 'Unauthorized access']);
        }
        $network = (string) $request->input('network', '');
        if ($network === '' || ! DB::table('m_network')->where('lcoCode', $network)->exists()) {
            return $this->billJson(['status' => false, 'message' => 'Please select a network']);
        }

        return $this->billJson($bills->deleteNetworkBills($network, (string) $request->input('bill_month', '')));
    }
}
