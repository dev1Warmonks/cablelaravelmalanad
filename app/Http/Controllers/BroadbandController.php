<?php

namespace App\Http\Controllers;

use App\Crud\Crud;
use App\Services\MenuService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Broadband (usertype 1/2, menu 10): ISP types, plans, combo plans, broadband subscribers and the broadband payment page. */
class BroadbandController extends Controller
{
    public function __construct(private MenuService $menus) {}

    private function needMenu(): void
    {
        abort_unless(in_array(10, $this->menus->menuList()), 403);
    }

    private function page(string $view, array $data = [])
    {
        return view($view, $data + ['menulist' => $this->menus->menuList(), 'network_list' => $this->menus->networkList()]);
    }

    private function run(Crud $crud, ?string $state, ?string $p1)
    {
        $r = $crud->run($state, $p1);

        return is_array($r) ? $this->page('admin.crud', ['crud' => $r]) : $r;
    }

    public function ispTypeManagement(Request $request, ?string $state = null, ?string $p1 = null)
    {
        $this->needMenu();

        return $this->run(Crud::make($request, 'Broadband/isptype_management')->table('m_broadband_isp_type')->subject('ISP Type')
            ->columns('name', 'status')->displayAs('name', 'ISP Type Name')->requiredFields('name', 'status')->changeFieldType('status', 'true_false'), $state, $p1);
    }

    public function broadbandPlansManagement(Request $request, ?string $state = null, ?string $p1 = null)
    {
        $this->needMenu();

        return $this->run(Crud::make($request, 'Broadband/broadbandplans_management')->table('m_broadband_plan')->subject('Broadband Plans')->unsetDelete()
            ->columns('name', 'period', 'isp_rate', 'tax_rate', 'operator_rate', 'package_amount', 'status')->displayAs('areaname', 'Area Name')
            ->requiredFields('name', 'period', 'isp_rate', 'tax_rate', 'operator_rate', 'package_amount', 'status')->nonNegative('period', 'isp_rate', 'tax_rate', 'operator_rate', 'package_amount')->changeFieldType('status', 'true_false'), $state, $p1);
    }

    public function comboPlansManagement(Request $request, ?string $state = null, ?string $p1 = null)
    {
        $this->needMenu();

        return $this->run(Crud::make($request, 'Broadband/comboplans_management')->table('m_combo_plan')->subject('Combo Plans')->unsetDelete()
            ->columns('name', 'rate', 'status')->requiredFields('name', 'rate', 'status')->nonNegative('rate')->changeFieldType('status', 'true_false'), $state, $p1);
    }

    public function broadbandSubscriberManagement(Request $request, ?string $state = null, ?string $p1 = null)
    {
        $this->needMenu();
        $lco = (string) session('lcoCode', '0');
        $crud = Crud::make($request, 'Broadband/broadband_subscriber_management')->table('m_broadband_customer')->subject('Broadband Subscriber')
            ->columns('customer_id', 'isp_type', 'status', 'broadband_plan')->displayAs('customer_id', 'Customer Name')
            ->relation('customer_id', 'm_customer', 'customer_name', fn ($q) => $q->where('r.lcoCode', $lco)->where('r.status', '!=', 2))
            ->relation('isp_type', 'm_broadband_isp_type', 'name', ['status' => 1])
            ->relation('broadband_plan', 'm_broadband_plan', 'name', ['status' => 1])
            ->requiredFields('customer_id', 'isp_type', 'status')->changeFieldType('status', 'true_false');
        // rows are limited to this network's customers (the old screen listed every network's broadband subscribers)
        if ((string) session('usertype') !== '1') {
            $crud->whereVia('customer_id', 'lcoCode', $lco);
        }

        return $this->run($crud, $state, $p1);
    }

    /** Broadband/broadband_payment: the payment screen for broadband customers (posts to Transaction/add_broadband_payment). */
    public function broadbandPayment()
    {
        $this->needMenu();
        $lco = (string) session('lcoCode', '0');
        $subscriber = DB::table('m_customer')->join('m_broadband_customer', 'm_broadband_customer.customer_id', '=', 'm_customer.id')
            ->where('m_customer.lcoCode', $lco)->where('m_customer.status', '!=', 2)->orderBy('customer_name')
            ->get(['m_customer.id', 'customer_name', 'mobile', 'lco_customer_id'])->map(fn ($r) => (array) $r)->all();

        return $this->page('admin.payment.broadband', ['subscriber' => $subscriber, 'package' => DB::table('m_package')->get()->map(fn ($r) => (array) $r)->all()]);
    }
}
