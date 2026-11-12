<?php

namespace App\Http\Controllers;

use App\Crud\Crud;
use App\Services\DashboardService;
use App\Services\MenuService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MasterController extends Controller
{
    public function __construct(private MenuService $menus) {}

    /** Master::index, dashboard. usertype 1 sees empty tiles (as before), LCO sees its network numbers. */
    public function index(DashboardService $dashboard)
    {
        $data = (string) session('usertype') !== '1' ? $dashboard->all() : $dashboard->emptyAll();

        return view('admin.dashboard', $data + [
            'menulist' => $this->menus->menuList(),
            'network_list' => $this->menus->networkList(),
        ]);
    }

    /**
     * Master::change_network/{id}. Fix vs old: only networks owned by the logged-in user can be selected
     * (the old code accepted any id, so an LCO could browse another LCO's data).
     */
    public function changeNetwork(string $networkId)
    {
        $network = DB::table('m_network')
            ->where('id', $networkId)->where('created_by', session('userid'))
            ->first(['network_name', 'lcoCode']);

        abort_unless($network, 403);

        session()->forget(['network_id', 'network_name', 'lcoCode']);
        session([
            'network_id' => $networkId,
            'network_name' => $network->network_name,
            'lcoCode' => $network->lcoCode,
        ]);

        return redirect()->back(fallback: url('master'));
    }

    // ---------------------------------------------------------------- CRUD screens (Grocery_CRUD replacement)

    /** Old behaviour: no permission => blank page. We answer 403. */
    private function needMenu(int $id): void
    {
        abort_unless(in_array($id, $this->menus->menuList()), 403);
    }

    private function crudView($crud)
    {
        return view('admin.crud', [
            'crud' => $crud,
            'menulist' => $this->menus->menuList(),
            'network_list' => $this->menus->networkList(),
        ]);
    }

    private function withCreatedBy(array $post): array
    {
        $post['created_by'] = session('userid');
        $post['created_on'] = date('Y-m-d H:i:s');

        return $post;
    }

    /** Master::area_management */
    public function areaManagement(Request $request, ?string $state = null, ?string $p1 = null)
    {
        $this->needMenu(3);
        $crud = Crud::make($request, 'master/area_management')
            ->table('m_area')->subject('Area')
            ->columns('areaname', 'status')
            ->displayAs('areaname', 'Area Name')
            ->requiredFields('areaname', 'status')
            ->changeFieldType('status', 'true_false')
            ->fieldType('area_code', 'invisible')->fieldType('created_on', 'invisible')->fieldType('created_by', 'invisible')
            ->callbackBeforeInsert(fn (array $p) => $this->withCreatedBy($p));
        if ((string) session('usertype') !== '1') {
            $crud->where('created_by', session('userid'));
        }

        return $this->respond($crud->run($state, $p1));
    }

    /** Master::subarea_management */
    public function subareaManagement(Request $request, ?string $state = null, ?string $p1 = null)
    {
        $this->needMenu(3);
        $crud = Crud::make($request, 'master/subarea_management')
            ->table('m_sub_area')->subject('Sub Area')
            ->unsetDelete()->unsetClone()->unsetRead()
            ->columns('areaname', 'area_id', 'last_created_number', 'status')
            ->displayAs('areaname', 'Subarea Name')->displayAs('area_id', 'Area')
            ->displayAs('last_created_number', 'Last created LCO Customer ID')
            ->requiredFields('areaname', 'area_id', 'status')
            ->relation('area_id', 'm_area', 'areaname', ['status' => 1, 'created_by' => session('userid')])
            ->changeFieldType('status', 'true_false');
        if ((string) session('usertype') !== '1') {
            // m_sub_area has no created_by; the old where('created_by') resolved to the joined m_area, i.e. "areas I own".
            $crud->whereVia('area_id', 'created_by', session('userid'));
        }

        return $this->respond($crud->run($state, $p1));
    }

    /** Crud returns a Response (JSON/export) or the page parts for the admin layout. */
    private function respond($result)
    {
        return is_array($result) ? $this->crudView($result) : $result;
    }

    /** Shared shape of the many "name + status" lookup screens. */
    private function simpleScreen(Request $request, ?string $state, ?string $p1, string $uri, int $menu, string $table, string $subject, array $columns, array $display, array $required, array $nonNegative = []): mixed
    {
        $this->needMenu($menu);
        $crud = Crud::make($request, "master/$uri")->table($table)->subject($subject)
            ->columns(...$columns)->requiredFields(...$required)->nonNegative(...$nonNegative)->changeFieldType('status', 'true_false');
        foreach ($display as $field => $label) {
            $crud->displayAs($field, $label);
        }

        return $this->respond($crud->run($state, $p1));
    }

    public function productsCategoryManagement(Request $r, ?string $state = null, ?string $p1 = null)
    {
        return $this->simpleScreen($r, $state, $p1, 'products_category_management', 1, 'm_products_category', 'Products Category', ['name', 'status'], [], ['name', 'status']);
    }

    public function taxrateManagement(Request $r, ?string $state = null, ?string $p1 = null)
    {
        return $this->simpleScreen($r, $state, $p1, 'taxrate_management', 1, 'm_tax_rate', 'Tax Rate', ['tax', 'status', 'description'], ['tax' => 'Tax Rate'], ['tax', 'status'], ['tax']);
    }

    public function stbModelManagement(Request $r, ?string $state = null, ?string $p1 = null)
    {
        return $this->simpleScreen($r, $state, $p1, 'stb_model_management', 5, 'm_stb_model', 'STB Model', ['name', 'status'], ['name' => 'Model Name'], ['name', 'status']);
    }

    public function stbMakeManagement(Request $r, ?string $state = null, ?string $p1 = null)
    {
        return $this->simpleScreen($r, $state, $p1, 'stb_make_management', 5, 'm_stb_make', 'STB Make', ['name', 'status'], ['name' => 'Make Name'], ['name', 'status']);
    }

    public function stbCasManagement(Request $r, ?string $state = null, ?string $p1 = null)
    {
        return $this->simpleScreen($r, $state, $p1, 'stb_cas_management', 5, 'm_stb_cas', 'Cutomer CAS', ['name', 'status'], ['name' => 'CAS Name'], ['name', 'status']);
    }

    public function stbTypeManagement(Request $r, ?string $state = null, ?string $p1 = null)
    {
        return $this->simpleScreen($r, $state, $p1, 'stb_type_management', 5, 'm_stb_type', 'Cutomer STB', ['name', 'status'], ['name' => 'STB Name'], ['name', 'status']);
    }

    /** Master::package_management: packages with their channels (many-to-many to m_products). */
    public function packageManagement(Request $request, ?string $state = null, ?string $p1 = null)
    {
        $this->needMenu(2);
        $crud = Crud::make($request, 'master/package_management')
            ->table('m_package')->subject('packages')->unsetDelete()
            ->columns('name', 'total_amount', 'status')
            ->requiredFields('name', 'total_amount', 'total_amount_manual', 'status')->nonNegative('total_amount', 'total_amount_manual')
            ->relationNN('channels', 'm_package_channels', 'm_products', 'package_id', 'product_id', 'product_name')
            ->changeFieldType('status', 'true_false');

        return $this->respond($crud->run($state, $p1));
    }

    /** Master::products_management */
    public function productsManagement(Request $request, ?string $state = null, ?string $p1 = null)
    {
        $this->needMenu(1);
        $add = fn (string $name, string $a, string $b) => fn () => '<div class="pretty-radio-buttons"><div class="radio"><label><input type="radio" name="'.$name.'" value="1" /> '.$a.'</label></div>
        <div class="radio"><label><input type="radio" name="'.$name.'" value="0" /> '.$b.'</label></div></div>';
        $edit = fn (string $name, string $a, string $b) => function ($value) use ($name, $a, $b) {
            $one = (string) $value === '1' ? 'checked' : '';
            $zero = (string) $value === '0' ? 'checked' : '';

            return ' <input '.$one.' type="radio" name="'.$name.'" value="1" /> '.$a.'
        <input '.$zero.' type="radio" name="'.$name.'" value="0" /> '.$b;
        };

        $crud = Crud::make($request, 'master/products_management')
            ->table('m_products')->subject('Product')
            ->columns('product_name', 'Is_alacarte', 'channels', 'base_rate', 'tax_rate', 'total_amount', 'status')
            ->displayAs('Is_alacarte', 'Is A-la-carte')
            ->requiredFields('product_name', 'Is_alacarte', 'channels', 'status', 'base_rate', 'tax_rate', 'total_amount')->nonNegative('base_rate', 'tax_rate', 'total_amount')
            ->fieldType('pricing_structure', 'dropdown', ['1' => 'RECURRING', '0' => 'NON-RECURRING'])
            ->fieldType('Is_alacarte', 'dropdown', ['1' => 'Yes', '0' => 'No'])
            ->callbackAddField('pricing_structure', $add('pricing_structure', 'RECURRING', 'NON-RECURRING'))
            ->callbackAddField('Is_alacarte', $add('Is_alacarte', 'Yes', 'No'))
            ->callbackEditField('pricing_structure', $edit('pricing_structure', 'RECURRING', 'NON-RECURRING'))
            ->callbackEditField('Is_alacarte', $edit('Is_alacarte', 'Yes', 'No'))
            ->relation('category', 'm_products_category', 'name', ['status' => 1])
            ->changeFieldType('status', 'true_false');

        return $this->respond($crud->run($state, $p1));
    }

    /** Master::network_management */
    public function networkManagement(Request $request, ?string $state = null, ?string $p1 = null)
    {
        $this->needMenu(4);
        $uid = session('userid');
        $limit = DB::table('m_user')->where('id', $uid)->value('network_count');
        $owned = DB::table('m_network')->where('created_by', $uid)->count();

        $crud = Crud::make($request, 'master/network_management')->table('m_network')->subject('Network');
        if ((int) $limit === $owned) {
            $crud->unsetAdd();
        }
        if ((string) session('usertype') !== '1') {
            $crud->where('created_by', $uid)->fieldType('print', 'invisible')->fieldType('sms_signature', 'invisible');
        } else {
            $crud->changeFieldType('print', 'true_false');
        }
        $crud->unsetDelete()->unsetRead()->unsetClone()
            ->columns('network_name', 'lcoCode', 'address', 'Subscriber_count', 'last_lco_cust_id', 'bill_type', 'status')
            ->displayAs('name', 'ISP Type Name')->displayAs('lcoCode', 'LCO Code (Same as EZYBill)')->displayAs('last_lco_cust_id', 'last LCO Customer ID')
            ->requiredFields('network_name', 'address', 'lcoCode', 'address', 'status')
            ->changeFieldType('status', 'true_false')
            ->changeFieldType('bill_type', 'true_false', ['Prepaid', 'Postpaid'])
            ->callbackColumn('Subscriber_count', fn ($val, $row) => (string) DB::table('m_customer')->where('lcoCode', $row->lcoCode)->count())
            ->callbackBeforeInsert(fn (array $p) => $this->withCreatedBy($p))
            ->fieldType('created_on', 'invisible')->fieldType('created_by', 'invisible')
            ->fieldType('logo', 'invisible')->fieldType('postal_licence_number', 'invisible');

        return $this->respond($crud->run($state, $p1));
    }

    /** Master::stb_management (rows are scoped to the logged-in user, as in the old where). */
    public function stbManagement(Request $request, ?string $state = null, ?string $p1 = null)
    {
        $this->needMenu(5);
        // The old customer dropdown listed customers of EVERY network (name leak); limited to the current network here.
        $customers = ['status' => 1, 'lcoCode' => session('lcoCode')];
        $crud = Crud::make($request, 'master/stb_management')
            ->table('m_stb')->subject('STB')->where('created_by', session('userid'))
            ->columns('customer_id', 'serial_number', 'type', 'status')
            ->displayAs('customer_id', 'Customer Name')
            ->relation('customer_id', 'm_customer', 'customer_name', $customers)
            ->relation('type', 'm_stb_type', 'name', ['status' => 1])
            ->relation('model', 'm_stb_model', 'name', ['status' => 1])
            ->relation('make', 'm_stb_make', 'name', ['status' => 1])
            ->relation('cas_provider', 'm_stb_cas', 'name', ['status' => 1])
            ->requiredFields('customer_id', 'serial_number', 'status')
            ->changeFieldType('status', 'true_false');

        return $this->respond($crud->run($state, $p1));
    }

    /** Master::agent_management */
    public function agentManagement(Request $request, ?string $state = null, ?string $p1 = null)
    {
        $this->needMenu(8);
        $uid = session('userid');
        $crud = Crud::make($request, 'master/agent_management')
            ->table('agent')->subject('Agent')->where('created_by', $uid)
            ->unsetDelete()->unsetClone()->unsetRead()
            ->columns('emptype', 'name', 'mobile', 'balance', 'areaid', 'status')
            ->displayAs('emptype', 'Employee Type')->displayAs('areaid', 'Sub Areas')
            ->relationNN('areaid', 'agent_areas', 'm_sub_area', 'agent_id', 'agent_area_id', '{areaname}',
                fn ($q) => $q->whereIn('area_id', DB::table('m_area')->select('id')->where('created_by', $uid)))
            ->fieldType('timestamp', 'invisible')
            ->requiredFields('emptype', 'password', 'username', 'name', 'mobile', 'balance', 'salary')
            ->changeFieldType('status', 'true_false')
            ->callbackBeforeInsert(fn (array $p) => $this->withCreatedBy($p))
            ->fieldType('created_on', 'invisible')->fieldType('created_by', 'invisible')
            ->fieldType('emptype', 'dropdown', ['3' => 'AGENT', '4' => 'TECHNICIAN', '5' => 'ADMINISTRATION']);

        return $this->respond($crud->run($state, $p1));
    }

    /** Master::miscellaneous_heads_management (admin only). */
    public function miscellaneousHeadsManagement(Request $request, ?string $state = null, ?string $p1 = null)
    {
        abort_unless((string) session('usertype') === '1', 403);
        $crud = Crud::make($request, 'master/miscellaneous_heads_management')
            ->table('m_dropdown')->subject('Miscellaneous heads')->where('type', 3)
            ->callbackBeforeInsert(function (array $p) {
                $p['type'] = 3;

                return $p;
            })
            ->fieldType('type', 'invisible')
            ->columns('name', 'status')->displayAs('name', 'Enquiry Name')
            ->requiredFields('name', 'status')->changeFieldType('status', 'true_false');

        return $this->respond($crud->run($state, $p1));
    }
}
