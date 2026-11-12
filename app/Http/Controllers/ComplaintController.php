<?php

namespace App\Http\Controllers;

use App\Crud\Crud;
use App\Services\MenuService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Complaints and enquiries (port of the CI Complaint controller; usertype 1 and 2 via the legacy.session middleware,
 * the enquiry / type screens also need menu 7). The old view/edit/update actions took any complaint id (and add took any
 * customer id): now only complaints and customers of the user's own networks.
 */
class ComplaintController extends Controller
{
    private const STATUS = [1 => 'Pending', 2 => 'On hold', 3 => 'Resolved'];

    public function __construct(private MenuService $menus) {}

    private function page(string $view, array $data = [])
    {
        return view($view, $data + ['menulist' => $this->menus->menuList(), 'network_list' => $this->menus->networkList()]);
    }

    private function needMenu(int $id): void
    {
        abort_unless(in_array($id, $this->menus->menuList()), 403);
    }

    /** @return list<int|string> ids of the user's networks */
    private function networkIds(): array
    {
        return DB::table('m_network')->where('created_by', session('userid'))->pluck('id')->all();
    }

    private function complaints()
    {
        return DB::table('m_complaints as S')
            ->leftJoin('m_customer as A', 'A.id', '=', 'S.customer_id')
            ->leftJoin('m_network as B', 'B.id', '=', 'S.network_id')
            ->leftJoin('m_area as C', 'C.id', '=', 'A.area_id')
            ->leftJoin('m_dropdown as D', fn ($j) => $j->on('D.id', '=', 'S.type')->where('D.type', 1))
            ->leftJoin('agent as E', 'E.id', '=', 'S.technician_id')
            ->whereIn('S.network_id', $this->networkIds())
            ->select(['A.billing_address as address', 'A.customer_name', 'B.network_name', 'A.mobile', 'A.lco_customer_id', 'C.areaname as area', 'D.name as type', 'S.status',
                'S.description', 'S.created_on', 'S.accepted_on', 'S.resolved_on', 'E.name as technician', 'S.technician_remarks', 'S.o_message', 'S.t_message',
                'S.c_message', 'S.o_response', 'S.t_response', 'S.c_response', 'S.id as complaint_id']);
    }

    // ------------------------------------------------------------------ complaints

    public function index()
    {
        $rows = $this->complaints()->orderBy('S.created_on')->get()->map(function ($r) {
            $r->status_text = self::STATUS[(int) $r->status] ?? $r->status;

            return $r;
        });

        return $this->page('admin.complaint.list', ['rows' => $rows]);
    }

    private function one(string $id): array
    {
        $row = ctype_digit($id) ? $this->complaints()->where('S.id', $id)->first() : null;

        return $row ? [(array) $row] : [];
    }

    public function viewComplaint(string $id)
    {
        return $this->page('admin.complaint.view', ['subscriber' => $this->one($id)]);
    }

    public function editComplaint(string $id)
    {
        return $this->page('admin.complaint.edit', ['subscriber' => $this->one($id)]);
    }

    public function complaintAddView()
    {
        return $this->page('admin.complaint.add', [
            'subscriber' => DB::table('m_customer')->whereIn('lcoCode', DB::table('m_network')->select('lcoCode')->where('created_by', session('userid')))
                ->orderBy('customer_name')->get(['id', 'customer_name', 'mobile', 'lco_customer_id'])->map(fn ($r) => (array) $r)->all(),
            'complaint_types' => DB::table('m_dropdown')->where('type', 1)->where('status', 1)->orderBy('name')->get(['name', 'id'])->map(fn ($r) => (array) $r)->all(),
        ]);
    }

    public function addComplaint(Request $request)
    {
        if (! $request->has(['subscriber', 'complaint_type', 'description'])) {
            return response('Failed');
        }
        $customer = $request->input('subscriber');
        $type = $request->input('complaint_type');
        if (! is_scalar($customer) || ! ctype_digit((string) $customer) || ! is_scalar($type) || ! ctype_digit((string) $type)
            || ! DB::table('m_customer')->where('id', $customer)->whereIn('lcoCode', DB::table('m_network')->select('lcoCode')->where('created_by', session('userid')))->exists()
            || ! DB::table('m_dropdown')->where('id', $type)->where('type', 1)->exists()) {
            return response('Failed');
        }
        DB::table('m_complaints')->insert([
            'description' => (string) $request->input('description'), 'type' => $type, 'customer_id' => $customer, 'status' => 1,
            'created_by' => session('userid'), 'created_on' => date('Y-m-d H:i:s'), 'network_id' => session('network_id'),
        ]);

        return response('Success');
    }

    public function updateComplaint(Request $request)
    {
        if (! $request->has(['technician_remarks', 'statuslist', 'complaint_id'])) {
            return response('Failed');
        }
        $id = $request->input('complaint_id');
        $status = $request->input('statuslist');
        if (! is_scalar($id) || ! ctype_digit((string) $id) || ! in_array((string) $status, ['1', '2', '3'], true)) {
            return response('Failed');
        }
        $n = DB::table('m_complaints')->where('id', $id)->whereIn('network_id', $this->networkIds())
            ->update(['technician_remarks' => (string) $request->input('technician_remarks'), 'status' => (int) $status]);
        if (! DB::table('m_complaints')->where('id', $id)->whereIn('network_id', $this->networkIds())->exists()) {
            Log::channel('audit')->warning('complaint update refused: not in own networks', ['by' => session('userid'), 'complaint' => $id]);

            return response('Failed');
        }

        return response('Success');
    }

    // ------------------------------------------------------------------ enquiries and types (menu 7)

    private function run(Crud $crud, ?string $state, ?string $p1)
    {
        $r = $crud->run($state, $p1);

        return is_array($r) ? $this->page('admin.crud', ['crud' => $r]) : $r;
    }

    public function enquiry(Request $request, ?string $state = null, ?string $p1 = null)
    {
        $this->needMenu(7);
        $crud = Crud::make($request, 'complaint/enquiry')->table('m_enquiries')->subject('Enquiry')
            ->unsetDelete()->unsetClone()->unsetRead()
            ->columns('name', 'address', 'mobile', 'type', 'description', 'status')
            ->displayAs('network_id', 'Network')->displayAs('type', 'Enquiry type');
        foreach (['network_id', 'technician_id', 'technician_remarks', 'o_message', 't_message', 'c_message', 'o_response', 't_response', 'c_response', 'created_on', 'accepted_on', 'resolved_on', 'token'] as $f) {
            $crud->fieldType($f, 'invisible');
        }
        $crud->relation('type', 'm_dropdown', 'name', ['type' => 2, 'status' => 1])->requiredFields('name', 'type', 'mobile', 'description');
        if ((string) session('usertype') !== '1') {
            $crud->fieldType('network_id', 'invisible')->fieldType('created_by', 'invisible')->whereIn('network_id', $this->networkIds());
        }   // (the network column of the list is a relation, see the 'list' branch; on the forms network_id stays hidden and is set on insert)
        switch ($state) {
            case null:
            case 'list':
            case 'ajax_list':
            case 'ajax_list_info':
            case 'success':
                $crud->fieldType('status', 'dropdown', ['1' => 'Pending', '2' => 'On hold', '3' => 'Resolved'])
                    ->relation('network_id', 'm_network', 'network_name', ['status' => 1])->relation('technician_id', 'agent', 'name')->unsetAdd();
                break;
            case 'edit':
                $crud->fieldType('status', 'dropdown', ['1' => 'Pending', '2' => 'On hold', '3' => 'Resolved'])->requiredFields('status', 'name', 'type', 'mobile', 'description');
                break;
            default:
                $crud->fieldType('status', 'invisible');
        }
        $crud->callbackBeforeInsert(function (array $p) {
            $p['status'] = 1;
            $p['created_on'] = date('Y-m-d H:i:s');
            $p['network_id'] = session('network_id');
            $p['created_by'] = session('userid');

            return $p;
        });

        return $this->run($crud, $state, $p1);
    }

    private function typeCrud(Request $request, string $uri, int $type, string $subject, string $nameLabel, ?string $state, ?string $p1)
    {
        $this->needMenu(7);
        $crud = Crud::make($request, $uri)->table('m_dropdown')->where('type', $type)->subject($subject)
            ->callbackBeforeInsert(function (array $p) use ($type) {
                $p['type'] = $type;

                return $p;
            })
            ->fieldType('type', 'invisible')->columns('name', 'status')->displayAs('name', $nameLabel)
            ->requiredFields('name', 'status')->changeFieldType('status', 'true_false');

        return $this->run($crud, $state, $p1);
    }

    public function complaintTypeManagement(Request $request, ?string $state = null, ?string $p1 = null)
    {
        return $this->typeCrud($request, 'complaint/complaint_type_management', 1, 'Complaint Type', 'Complaint Name', $state, $p1);
    }

    public function enquiryTypeManagement(Request $request, ?string $state = null, ?string $p1 = null)
    {
        return $this->typeCrud($request, 'complaint/enquiry_type_management', 2, 'Enquiry Type', 'Enquiry Name', $state, $p1);
    }
}
