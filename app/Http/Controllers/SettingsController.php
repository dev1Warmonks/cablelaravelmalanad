<?php

namespace App\Http\Controllers;

use App\Crud\Crud;
use App\Services\MenuService;
use Illuminate\Http\Request;

/** Settings (usertype 1 or 2): payments list (admin) and the general settings page (menu 6). */
class SettingsController extends Controller
{
    public function __construct(private MenuService $menus) {}

    /** Settings::index: read-only list of all payments (admin). */
    public function index(Request $request, ?string $state = null, ?string $p1 = null)
    {
        abort_unless((string) session('usertype') === '1', 403);
        $crud = Crud::make($request, 'Settings')->table('subscriberpayments')->subject('Payments')
            ->orderBy('id', 'desc')
            ->unsetDelete()->unsetClone()->unsetRead()->unsetAdd()->unsetEdit()
            ->callbackColumn('timestamp', fn ($val) => e(date('d/m/Y h:i:s a', ((float) $val) / 1000)))
            ->columns('id', 'timestamp', 'subscriberid', 'description', 'dueamount', 'paidamount', 'discount', 'dbafterdiscount')
            ->displayAs('id', 'Txn#')->displayAs('timestamp', 'Date')->displayAs('dueamount', 'Previous Due')
            ->displayAs('paidamount', 'Paid')->displayAs('dbafterdiscount', 'Balance')->displayAs('subscriberid', 'Customer')
            ->relation('subscriberid', 'm_customer', 'customer_name');
        $r = $crud->run($state, $p1);

        return is_array($r)
            ? view('admin.crud', ['crud' => $r, 'menulist' => $this->menus->menuList(), 'network_list' => $this->menus->networkList()])
            : $r;
    }

    private function needGeneralMenu(): void
    {
        abort_unless(in_array(6, $this->menus->menuList()), 403);
    }

    public function general(AdminSettingsController $admin)
    {
        $this->needGeneralMenu();

        return $admin->generalPage();
    }

    public function settingsSave(Request $request, AdminSettingsController $admin)
    {
        $this->needGeneralMenu();

        return $admin->saveSetting($request);
    }
}
