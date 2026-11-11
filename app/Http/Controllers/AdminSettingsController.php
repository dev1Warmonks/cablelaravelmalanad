<?php

namespace App\Http\Controllers;

use App\Crud\Crud;
use App\Services\BackupService;
use App\Services\MaintenanceService;
use App\Services\MenuService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * AdminSettings (usertype 1 only). The old controller had its auth check commented out and several
 * methods (backup/download/delete) were reachable by any logged-in user: all routes here sit behind
 * legacy.session:1.
 */
class AdminSettingsController extends Controller
{
    /** settings.field values the general page may update (same whitelist as the old switch). */
    public const SETTING_FIELDS = ['remindersms', 'percustomeramount', 'blocknetworkMSG', 'DefaultNotification'];

    public function __construct(private MenuService $menus)
    {
    }

    private function page(string $view, array $data = [])
    {
        return view($view, $data + [
            'menulist' => $this->menus->menuList(),
            'network_list' => $this->menus->networkList(),
        ]);
    }

    private function crudPage(Crud $crud, ?string $state, ?string $p1)
    {
        $r = $crud->run($state, $p1);

        return is_array($r) ? $this->page('admin.crud', ['crud' => $r]) : $r;
    }

    // ---------------------------------------------------------------- CRUD screens

    public function smsApis(Request $request, ?string $state = null, ?string $p1 = null)
    {
        $crud = Crud::make($request, 'AdminSettings/sms_apis')->table('sms_apis')->subject('Sms APIS')
            ->unsetDelete()->unsetClone()->unsetRead()
            ->changeFieldType('status', 'true_false')
            ->requiredFields('provider', 'status', 'api', 'balance');

        return $this->crudPage($crud, $state, $p1);
    }

    public function userMainMenus(Request $request, ?string $state = null, ?string $p1 = null)
    {
        $crud = Crud::make($request, 'AdminSettings/user_main_menus')->table('m_main_menus')->subject('Menus')
            ->columns('menu_id', 'menu_name')->orderBy('menu_name', 'asc');

        return $this->crudPage($crud, $state, $p1);
    }

    public function paymentTypes(Request $request, ?string $state = null, ?string $p1 = null)
    {
        $crud = Crud::make($request, 'AdminSettings/payment_types')->table('gsoft_payment_types')->subject('Payment types')
            ->unsetDelete()->unsetClone()->unsetRead()
            ->columns('image_url', 'name', 'url', 'status')
            ->displayAs('image_url', 'Image')
            ->requiredFields('name', 'status')
            ->changeFieldType('status', 'true_false')
            // Old: any of ~30 file types (incl. documents/media) into the web-served assets/img. Images only now.
            ->uploadField('image_url', 'assets/img', ['gif', 'jpeg', 'jpg', 'png']);

        return $this->crudPage($crud, $state, $p1);
    }

    public function importFormats(Request $request, ?string $state = null, ?string $p1 = null)
    {
        $crud = Crud::make($request, 'AdminSettings/import_formats')->table('import_format')->subject('Import format')
            ->unsetDelete()->unsetClone()->unsetRead()
            ->columns('field_name', 'column_no', 'type', 'excel_column_name')
            ->requiredFields('name', 'status')
            ->fieldType('type', 'dropdown', ['1' => 'Customer', '2' => 'Invoice', '3' => 'Customer Format 2']);

        return $this->crudPage($crud, $state, $p1);
    }

    /** Id of the "generatebill" menu, created on first use (same as the old code). */
    private function generateBillMenuId(): int
    {
        $id = DB::table('m_main_menus')->where('menu_name', 'generatebill')->value('menu_id');

        return (int) ($id ?: DB::table('m_main_menus')->insertGetId(['menu_name' => 'generatebill']));
    }

    private function generateBillAllActive(int $menuId): bool
    {
        $total = DB::table('m_user')->where('type', '!=', 1)->count();
        $assigned = DB::table('m_user_roles')->where('menu_id', $menuId)->count();

        return $total > 0 && $assigned >= $total;
    }

    public function usersManagement(Request $request, ?string $state = null, ?string $p1 = null)
    {
        $active = $this->generateBillAllActive($this->generateBillMenuId());
        $label = $active ? 'Activate Generate Bill (active)' : 'Activate Generate Bill (inactive)';
        $bg = $active ? '#28a745' : '#dc3545';
        $cls = $active ? 'btn-success' : 'btn-danger';

        $crud = Crud::make($request, 'AdminSettings/users_management')->table('m_user')->subject('User')
            ->unsetDelete()->unsetClone()->unsetRead()
            ->columns('network_name', 'username', 'type', 'roles', 'default_sms_api', 'status')
            ->fieldType('timestamp', 'invisible')->whereNot('type', 1)
            ->fieldType('type', 'invisible')
            ->relation('default_sms_api', 'sms_apis', 'provider')
            ->requiredFields('network_name', 'username', 'name', 'status', 'password')
            ->changeFieldType('status', 'true_false')->changeFieldType('sms', 'true_false')
            ->changeFieldType('misc_add_sms', 'true_false')->changeFieldType('misc_payment', 'true_false')
            ->changeFieldType('block_network', 'true_false')
            ->relationNN('roles', 'm_user_roles', 'm_main_menus', 'user_id', 'menu_id', 'menu_name')
            ->callbackBeforeInsert(function (array $p) { $p['type'] = 2; return $p; })
            ->displayAs('name', 'Name / Shop name')->displayAs('commission', 'Commission %');

        $r = $crud->run($state, $p1);
        if (! is_array($r)) {
            return $r;
        }
        // The "Activate Generate Bill" button was a GET link; it changes data, so it now submits a CSRF-protected POST.
        $url = url('AdminSettings/activate_generate_bill_all');
        $r['output'] = '<script>
			$(document).ready(function() {
				var addBtn = $("a[href*=\'/add\']");
				if(addBtn.length > 0) {
					$("<a href=\'#\' onclick=\'var f=document.createElement(\"form\");f.method=\"post\";f.action=\"'.$url.'\";var t=document.createElement(\"input\");t.type=\"hidden\";t.name=\"_token\";t.value=\"'.csrf_token().'\";f.appendChild(t);document.body.appendChild(f);f.submit();return false;\' class=\'btn '.$cls.'\' style=\'margin-left: 10px; font-weight: bold; background-color: '.$bg.'; border-color: '.$bg.'; color: #fff; padding: 6px 12px; display: inline-block; border-radius: 4px; text-decoration: none;\'><i class=\'fa fa-bolt\'></i> '.$label.'</a>").insertAfter(addBtn);
				}
			});
			</script>'.$r['output'];

        return $this->page('admin.crud', ['crud' => $r]);
    }

    public function activateGenerateBillAll()
    {
        $menuId = $this->generateBillMenuId();
        DB::transaction(function () use ($menuId) {
            if ($this->generateBillAllActive($menuId)) {
                DB::table('m_user_roles')->where('menu_id', $menuId)->delete();

                return;
            }
            foreach (DB::table('m_user')->where('type', '!=', 1)->pluck('id') as $uid) {
                if (! DB::table('m_user_roles')->where('user_id', $uid)->where('menu_id', $menuId)->exists()) {
                    DB::table('m_user_roles')->insert(['user_id' => $uid, 'menu_id' => $menuId]);
                }
            }
        });

        return redirect('AdminSettings/users_management');
    }

    // ---------------------------------------------------------------- general settings + maintenance scripts

    public function general()
    {
        return $this->generalPage();
    }

    /** Shared with SettingsController::general (menu 6). */
    public function generalPage()
    {
        $settings = DB::table('settings')->get()->map(fn ($r) => (array) $r)->all();
        $customers = DB::table('m_customer')->orderBy('customer_name')->get(['id', 'customer_name', 'billing_address'])->map(fn ($r) => (array) $r)->all();
        $all = DB::table('m_network')->where('status', 1)->get(['network_name', 'id', 'lcoCode'])->map(fn ($r) => (array) $r)->all();

        return $this->page('admin.settings.general', ['settings' => $settings, 'customer' => $customers, 'all_network_list' => $all]);
    }

    public function settingsSave(Request $request)
    {
        return $this->saveSetting($request);
    }

    public function saveSetting(Request $request)
    {
        $field = (string) $request->input('field');
        if (in_array($field, self::SETTING_FIELDS, true)) {
            DB::table('settings')->where('field', $field)->update(['value' => (string) $request->input('value')]);
        }

        return response('Settings Updated');
    }

    public function scripts(MaintenanceService $svc, ?string $lcoCode = '')
    {
        return response($svc->deactivateCustomers((string) $lcoCode).'script executed');
    }

    public function updateLastPaid(MaintenanceService $svc)
    {
        $svc->updateLastPaidDate();

        return response('script executed');
    }

    // ---------------------------------------------------------------- backups

    public function backup()
    {
        return $this->page('admin.settings.backup');
    }

    public function databaseList(BackupService $backups)
    {
        return response(view('admin.settings.backup_list', ['files' => $backups->list()])->render());
    }

    public function generateBackup(BackupService $backups)
    {
        $backups->generate();

        return response('success');
    }

    public function deleteBackup(BackupService $backups, string $name)
    {
        $backups->delete($name);

        return response('success');
    }

    public function downloadBackup(BackupService $backups, string $name)
    {
        $path = $backups->path($name);
        abort_unless($path !== null, 404);

        return response()->download($path, $name);
    }

    // ---------------------------------------------------------------- GSoft monthly bills (administrator)

    public function monthlyBills()
    {
        return $this->page('admin.settings.monthly_bills', [
            'payment_type_list' => DB::table('gsoft_payment_types')->where('status', 1)->get()->map(fn ($r) => (array) $r)->all(),
            'users_list' => DB::table('m_user')->where('type', 2)->orderBy('network_name')->get(['id', 'network_name'])->map(fn ($r) => (array) $r)->all(),
            'networks' => DB::table('m_network')->get(['lcoCode', 'network_name'])->map(fn ($r) => (array) $r)->all(),
        ]);
    }

    public function generateMonthlyBills(\App\Services\GsoftBillingService $billing)
    {
        $billing->generateAll();

        return response('Invoice generated');
    }

    public function monthlyBillsData(Request $request, \App\Services\GsoftBillingService $billing)
    {
        foreach (['user_id', 'network', 'status'] as $k) {
            if (! $request->has($k)) {
                return response('Invalid request');
            }
        }
        $data = $billing->adminScreen((int) $request->input('status'), (int) $request->input('user_id'), (string) $request->input('network'));

        return response(view('admin.settings.monthly_bills_data', ['data' => $data])->render());
    }

    public function monthlyBillsView(Request $request, \App\Services\GsoftBillingService $billing)
    {
        if (! $request->has('current_month') || ! $request->has('network')) {
            return response('');
        }
        $bill = $billing->billFor((string) $request->input('network'), (string) $request->input('current_month'));
        if (! $bill) {
            return response('No bill found');
        }

        return response(view('admin.settings.monthly_bills_view', [
            'bill' => $bill, 'network' => (string) $request->input('network'),
            'status' => \App\Services\GsoftBillingService::STATUS[(int) $bill->bill_status] ?? '',
        ])->render());
    }

    public function monthlyPaymentTypeView(Request $request)
    {
        if (! $request->has('payment_type') || ! $request->has('amount') || ! $request->has('network')) {
            return response('');
        }

        return response('<div style="text-align:center">
				<button type="button" style="margin-top: 20px;" onClick="cashCollectedClick();return false;" class="form-control btn-warning">Cash collected and complete payment</button>
			</div>');
    }

    /** Confirms a bill as paid (payment type 0 = direct cash, ids from gsoft_payment_types) or free (-2). Administrator only. */
    public function completePayment(Request $request, \App\Services\GsoftBillingService $billing)
    {
        if (! $request->has('current_month') || ! $request->has('payment_type') || ! $request->has('network')) {
            return response('');
        }
        $type = (string) $request->input('payment_type');
        if (! preg_match('/^-?\d+$/', $type) || ((int) $type !== -2 && (int) $type !== 0 && ! DB::table('gsoft_payment_types')->where('id', (int) $type)->exists())) {
            return response('Invalid payment type', 422);
        }
        $billing->complete((string) $request->input('network'), (string) $request->input('current_month'), (int) $type);

        return response('');
    }

    /** Networks of a user (or all) for the filter drop-down. */
    public function gsoftNetworkList(Request $request)
    {
        if (! $request->has('user_id')) {
            return response('');
        }
        $q = DB::table('m_network');
        if ((int) $request->input('user_id') !== -1) {
            $q->where('created_by', (int) $request->input('user_id'));
        }

        return response()->json(['success' => true, 'data' => $q->get(['lcoCode', 'network_name'])->all()]);
    }
}
