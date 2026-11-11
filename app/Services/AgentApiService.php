<?php

namespace App\Services;

use DateTime;
use DateTimeZone;
use Illuminate\Support\Facades\DB;

/**
 * Port of the CodeIgniter AgentApi controller (mobile app backend). The JSON it returns is byte-identical to the old app
 * (key order, string/number types, messages and their quirks) - verified against recordings of the old app, see tests/agentapi.
 *
 * What changed (mechanism only, not the observable contract):
 *  - every value is bound as a parameter (the old code concatenated paidamount / payment id / customer id into SQL);
 *  - payment amounts are validated and the payment is written through PaymentService (row lock + exact arithmetic);
 *  - the duplicate-payment guard (same customer + amount within 30 minutes) runs inside the same lock, so two simultaneous
 *    requests cannot both pass it;
 *  - the payment receipt PDF is only produced for payments of customers of the agent's own networks (the old code printed ANY
 *    payment id of ANY network);
 *  - request bodies (which contain passwords) are never logged; internal errors are logged, not echoed to the client.
 */
class AgentApiService
{
    public function __construct(private readonly PaymentService $payments, private readonly SmsService $sms) {}

    // ---------------------------------------------------------------- helpers

    private static function tz(): DateTimeZone
    {
        return new DateTimeZone('Asia/Kolkata');
    }

    /** M-d-Y h:i a of a millisecond timestamp, Asia/Kolkata (AgentApi::formatTimeDMY). */
    private static function fmt(mixed $ms): string
    {
        return (new DateTime('@'.(int) round(((float) $ms) / 1000)))->setTimezone(self::tz())->format('M-d-Y h:i a');
    }

    /** Active agent (emptype 3) for a token; exactly one row is required (as before). Empty tokens never match. */
    public function agentByToken(mixed $token): ?object
    {
        if (! is_scalar($token) || (string) $token === '') {
            return null;
        }
        $rows = DB::table('agent')->where('token', (string) $token)->where('status', 1)->where('emptype', 3)->get(['id', 'created_by', 'name']);

        return $rows->count() === 1 ? $rows[0] : null;
    }

    /** @return array<string, mixed>|null null = no usable agent */
    private static function scalarOr(mixed $v, mixed $default): mixed
    {
        return is_scalar($v) ? $v : $default;
    }

    /** A customer id is only valid inside the agent's own networks. */
    private function customerInNetworks(mixed $id, array $lcoCodes): ?object
    {
        $id = self::scalarOr($id, 0);
        if ($lcoCodes === []) {
            return null;
        }

        return DB::table('m_customer')->where('id', (string) $id)->whereIn('lcoCode', $lcoCodes)->first(['id', 'customer_name', 'lcoCode']);
    }

    // ---------------------------------------------------------------- login / verify

    /** @return array<string,mixed> */
    public function login(?array $in): array
    {
        if (! isset($in['username'], $in['password'])) {
            return ['status' => 'failed', 'message' => 'Data missing'];
        }
        if (! is_scalar($in['username']) || ! is_scalar($in['password'])) {
            return ['status' => 'failed', 'message' => 'Invalid Username or Password'];
        }
        $username = (string) $in['username'];
        $password = (string) $in['password'];

        $q = DB::table('agent')->where('username', $username)->where('status', 1)->where('emptype', 3);
        if (config('cablebill.hash_passwords')) {
            $rows = $q->get(['token', 'name', 'created_by', 'password'])->filter(fn ($r) => PasswordVerifier::check($password, $r->password))->values();
        } else {
            $rows = $q->where('password', $password)->get(['token', 'name', 'created_by']);   // plain-text legacy: SQL equality as before
        }
        if ($rows->count() !== 1) {
            return ['status' => 'failed', 'message' => 'Invalid Username or Password'];
        }
        $owner = DB::table('m_user')->where('id', $rows[0]->created_by)->get(['status']);
        if ($owner->count() !== 1) {
            return [];   // old quirk: owner row missing -> empty JSON array
        }
        if ((int) $owner[0]->status === 1) {
            return ['token' => $rows[0]->token, 'name' => $rows[0]->name, 'status' => 'success'];
        }

        return ['status' => 'failed', 'message' => 'Invalid Username or Password'];
    }

    /** @return array<string,mixed> */
    public function verify(?array $in): array
    {
        if (! isset($in['token'])) {
            return ['status' => 'failed', 'message' => 'Data missing'];
        }
        $agent = $this->agentByToken($in['token']);
        if (! $agent) {
            return ['status' => 'failed', 'message' => 'Invalid Token'];
        }
        $owner = DB::table('m_user')->where('id', $agent->created_by)->get(['status']);
        if ($owner->count() !== 1) {
            return [];
        }

        return ['status' => (int) $owner[0]->status === 1 ? 'success' : 'failed'];
    }

    // ---------------------------------------------------------------- list endpoints

    /** @return array<string,mixed> */
    public function subscriberList(?array $in): array
    {
        if (! isset($in['token'])) {
            return ['status' => 'failed', 'message' => 'Data missing'];
        }
        $agent = $this->agentByToken($in['token']);
        if (! $agent) {
            return ['data' => [], 'status' => 'failed', 'message' => 'Invalid Token'];
        }
        $hasAreas = DB::table('agent_areas')->where('agent_id', $agent->id)->exists();
        $thisMonth = (new DateTime('now', self::tz()))->format('m');
        $data = [];
        foreach (DB::table('m_network')->where('created_by', $agent->created_by)->where('status', 1)->get(['lcoCode']) as $net) {
            $q = DB::table('m_customer')
                ->leftJoin('subscriberbalance', 'subscriberbalance.subscriberid', '=', 'm_customer.id')
                ->leftJoin('m_sub_area', 'm_sub_area.id', '=', 'm_customer.subarea_id')
                ->selectRaw('m_customer.id as customer_id,customer_name,billing_address,lco_customer_id,mobile,subscriberbalance.amountdue as due,subscriberbalance.timestamp as lastpaid,m_customer.status as status,m_customer.serial_number,m_customer.vc_number,m_customer.subarea_id,m_sub_area.areaname');
            if ($hasAreas) {
                $q->whereIn('m_customer.subarea_id', DB::table('agent_areas')->select('agent_area_id')->where('agent_id', $agent->id));
            }
            $rows = $q->where('lcoCode', $net->lcoCode)->where('m_customer.status', '!=', 2)->orderBy('m_customer.lcoCode')->get();
            foreach ($rows as $r) {
                $lastpaid = ($r->lastpaid !== null && $r->lastpaid !== '') ? self::fmt($r->lastpaid) : '';
                $paid = ($lastpaid !== '' && (new DateTime('@'.(int) round(((float) $r->lastpaid) / 1000)))->setTimezone(self::tz())->format('m') === $thisMonth) ? 1 : 0;
                $data[] = [
                    'name' => $r->customer_name ?? '', 'customer_id' => $r->customer_id ?? '', 'address' => $r->billing_address ?? '',
                    'area_id' => $r->subarea_id ?? '', 'areaname' => $r->areaname ?? '', 'lco_custid' => $r->lco_customer_id ?? '',
                    'serial_number' => $r->serial_number ?? '', 'vc_number' => $r->vc_number ?? '', 'mobile' => $r->mobile ?? '',
                    'due' => $r->due ?? '', 'paid' => $paid, 'lastpaid' => $lastpaid, 'status' => $r->status ?? '',
                ];
            }
        }

        return ['data' => $data, 'status' => 'success'];
    }

    /** @return array<string,mixed> */
    public function transactionTypeList(?array $in): array
    {
        if (! isset($in['token'])) {
            return ['status' => 'failed', 'message' => 'Data missing'];
        }
        if (! $this->agentByToken($in['token'])) {
            return ['data' => [], 'status' => 'failed', 'message' => 'Invalid Token'];
        }
        $data = [['id' => 1, 'name' => 'Cable Subscription']];
        foreach (DB::table('m_dropdown')->where('type', 3)->get(['id', 'name']) as $h) {
            $data[] = ['id' => $h->id, 'name' => $h->name];
        }

        return ['data' => $data, 'status' => 'success'];
    }

    /** @return array<string,mixed> */
    public function areaList(?array $in): array
    {
        if (! isset($in['token'])) {
            return ['status' => 'failed', 'message' => 'Data missing'];
        }
        $agent = $this->agentByToken($in['token']);
        if (! $agent) {
            return ['data' => [], 'status' => 'failed', 'message' => 'Invalid Token'];
        }
        $areas = DB::table('agent_areas')->where('agent_id', $agent->id)->get(['agent_area_id']);
        $data = [];
        foreach ($areas as $a) {
            if ($a->agent_area_id === null) {
                continue;
            }
            $name = DB::table('m_sub_area')->where('id', $a->agent_area_id)->first(['areaname']);
            if ($name) {
                $data[] = ['id' => $a->agent_area_id, 'name' => $name->areaname];
            }
        }
        if ($areas->count() > 0) {
            return ['data' => $data, 'status' => 'success', 'message' => ''];
        }

        return ['data' => $data, 'status' => 'success'];
    }

    /** @return array<string,mixed> */
    public function printHeader(?array $in): array
    {
        if (! isset($in['token'])) {
            return ['status' => 'failed', 'message' => 'Token missing'];
        }
        $agent = $this->agentByToken($in['token']);
        if (! $agent) {
            return ['status' => 'failed', 'message' => 'Invalid Token'];
        }
        $nets = DB::table('m_network')->where('created_by', $agent->created_by)->get(['network_name', 'address', 'phone']);
        if ($nets->isEmpty()) {
            return ['status' => 'failed', 'message' => 'Invalid Network'];
        }
        $network = $nets->map(fn ($n) => ['network_name' => $n->network_name ?? '', 'address' => $n->address ?? '', 'phone' => $n->phone ?? ''])->all();

        return ['network' => $network, 'status' => 'success', 'message' => ''];
    }

    /** @return array<string,mixed> */
    public function complaintTypeList(?array $in): array
    {
        if (! isset($in['token'])) {
            return ['status' => 'failed', 'message' => 'Data missing'];
        }
        if (! $this->agentByToken($in['token'])) {
            return ['data' => [], 'status' => 'failed', 'message' => 'Invalid Token'];
        }
        $rows = DB::table('m_dropdown')->where('type', 1)->where('status', 1)->orderBy('name')->get(['name', 'id']);

        return ['data' => $rows->map(fn ($r) => ['name' => $r->name, 'id' => $r->id])->all(), 'status' => 'success'];
    }

    // ---------------------------------------------------------------- history / report

    /** @return array<string,mixed> */
    public function history(?array $in): array
    {
        if (! isset($in['token'])) {
            return ['status' => 'failed', 'message' => 'Token missing'];
        }
        $agent = $this->agentByToken($in['token']);
        if (! $agent) {
            return ['data' => [], 'status' => 'failed', 'message' => 'Invalid Token'];
        }
        $customer = $this->customerInNetworks($in['subscriberid'] ?? 0, DB::table('m_network')->where('created_by', $agent->created_by)->pluck('lcoCode')->all());
        if (! $customer) {
            return ['data' => [], 'status' => 'failed', 'message' => 'Invalid Customer'];
        }
        $rows = DB::select('SELECT a.*,b.name as agent FROM subscriberpayments a left outer join agent b on b.id=a.agentid WHERE a.subscriberid = ? ORDER BY a.timestamp DESC', [(string) $customer->id]);
        $data = [];
        foreach ($rows as $r) {
            $type = (int) $r->transactiontype;
            $sub = ($type === 0 || $type === 2) ? $r->subscriptioncharge : 0;
            $misc = $type === 3 ? $r->subscriptioncharge : 0;
            $agentName = ($r->agent ?? '') === '' ? 'Admin' : $r->agent;
            $data[] = [
                'timestamp' => self::fmt($r->timestamp), 'description' => $r->description ?? '',
                'miscellaneous' => number_format((float) $misc, 2, '.', ''), 'subscriptioncharge' => number_format((float) $sub, 2, '.', ''),
                'previous_due' => $r->dueamount ?? '', 'paidamount' => $r->paidamount ?? '', 'discount' => $r->discount ?? '',
                'due' => $r->dbafterdiscount ?? '', 'agent' => $agentName, 'tran_id' => $r->id ?? '',
            ];
        }

        return ['data' => $data, 'status' => 'success'];
    }

    /** Today's collection of this agent (Model_settings::daily_report_data). @return array<string,mixed> */
    public function dailyReport(?array $in): array
    {
        if (! isset($in['token'])) {
            return ['status' => 'failed', 'message' => 'Data missing'];
        }
        $agent = $this->agentByToken($in['token']);
        if (! $agent) {
            return ['data' => [], 'status' => 'failed', 'message' => 'Invalid Token'];
        }
        $result = ['data' => []];
        if (DB::table('m_network')->where('created_by', $agent->created_by)->exists()) {
            $today = new DateTime('today', self::tz());
            $date = $today->format('Y-m-d');
            $from = $today->getTimestamp() * 1000;
            $to = $from + 24 * 60 * 60 * 1000;
            $aid = (int) $agent->id;

            $c = DB::selectOne('SELECT sum(paidamount) as collectionamount, count(id) as collectioncount FROM subscriberpayments WHERE timestamp > ? AND timestamp < ? and transactiontype = 1 and agentid = ?', [$from, $to, $aid]);
            $d = DB::selectOne("SELECT sum(discount) as discountamount, count(id) as discountcount FROM subscriberpayments WHERE timestamp > ? AND timestamp < ? and transactiontype = 1 and agentid = ? AND discount != '0.0000'", [$from, $to, $aid]);

            $rows = DB::table('subscriberpayments')
                ->leftJoin('m_customer', 'm_customer.id', '=', 'subscriberpayments.subscriberid')
                ->leftJoin('agent', 'agent.id', '=', 'subscriberpayments.agentid')
                ->selectRaw('subscriberpayments.*,m_customer.customer_name as customer_name,m_customer.billing_address,m_customer.mobile,agent.name as agent,m_customer.lco_customer_id')
                ->where('subscriberpayments.agentid', $aid)
                ->whereRaw('subscriberpayments.timestamp > ? AND subscriberpayments.timestamp < ?', [(string) $from, (string) $to])
                ->where('subscriberpayments.description', '!=', 'Opening balance')
                ->orderByDesc('subscriberpayments.id')->get();
            foreach ($rows as $r) {
                $result['data'][] = [
                    'lco_customer_id' => $r->lco_customer_id, 'customer_name' => $r->customer_name, 'billing_address' => $r->billing_address,
                    'mobile' => str_replace("'91", '', (string) $r->mobile), 'description' => $r->description,
                    'dueamount' => number_format((float) $r->dueamount, 2, '.', ''), 'paidamount' => number_format((float) $r->paidamount, 2, '.', ''),
                    'discount' => number_format((float) $r->discount, 2, '.', ''), 'dbafterdiscount' => number_format((float) $r->dbafterdiscount, 2, '.', ''),
                ];
            }
            $result['collectioncount'] = $c->collectioncount ?? 0;
            $result['collectionamount'] = number_format((float) ($c->collectionamount ?? 0), 2);
            $result['discountcount'] = $d->discountcount ?? 0;
            $result['discountamount'] = number_format((float) ($d->discountamount ?? 0), 2);
            $result['status'] = 'success';
            $result['date'] = $date;
        }

        return $result;
    }

    // ---------------------------------------------------------------- writes

    /** @return array<string,mixed> */
    public function addPayment(?array $in): array
    {
        if (! isset($in['token'])) {
            return ['status' => 'failed', 'message' => 'Token missing'];
        }
        $agent = $this->agentByToken($in['token']);
        if (! $agent) {
            return ['status' => 'failed', 'message' => 'Invalid Token'];
        }
        $customer = $this->customerInNetworks($in['subscriberid'] ?? 0, DB::table('m_network')->where('created_by', $agent->created_by)->pluck('lcoCode')->all());
        if (! $customer) {
            return ['status' => 'failed', 'message' => 'Invalid Customer'];
        }
        $paid = self::scalarOr($in['paidamount'] ?? 0, null);
        $discount = self::scalarOr($in['discount'] ?? 0, null);
        $paid = $paid === null ? null : trim((string) $paid);
        $discount = $discount === null ? null : trim((string) $discount);
        $discount = $discount === '' ? '0' : $discount;
        $money = '/^\d+(\.\d{1,2})?$/';
        if ($paid === null || $discount === null || ! preg_match($money, $paid) || ! preg_match($money, $discount) || bccomp($paid, '0', 2) <= 0) {
            return ['status' => 'failed', 'message' => 'Invalid Amount'];
        }
        $trantype = isset($in['trantype']) && is_scalar($in['trantype']) ? $in['trantype'] : 1;
        $trantype = (is_numeric($trantype) && (float) $trantype == 1) ? '1' : (string) $trantype;
        $comment = isset($in['comment']) && is_scalar($in['comment']) ? (string) $in['comment'] : '';
        $other = isset($in['othermobile']) && is_scalar($in['othermobile']) ? (string) $in['othermobile'] : '';

        $result = DB::transaction(function () use ($customer, $paid, $discount, $trantype, $comment, $other, $agent) {
            DB::table('m_customer')->where('id', (string) $customer->id)->lockForUpdate()->first(['id']);
            $cutoff = (new DateTime('now', self::tz()))->getTimestamp() - 30 * 60;
            $dup = DB::table('subscriberpayments')->where('subscriberid', (string) $customer->id)->whereRaw('paidamount = ?', [$paid])
                ->where('timestamp', '>', $cutoff * 1000)->exists();
            if ($dup) {
                return null;
            }

            return $this->payments->record([
                'subscriberid' => $customer->id, 'paidamount' => $paid, 'discount' => $discount, 'comment' => $comment,
                'trantype' => $trantype, 'othermobile' => $other, 'is_app' => true,
            ], $agent->id, $agent->created_by);
        });
        if ($result === null) {
            return ['status' => 'failed', 'message' => 'Please try after 30 minutes'];
        }
        $this->sms->sendPayment($result['id'], $agent->created_by);   // no-op unless SMS_SEND_ENABLED=true; never affects the payment

        return ['message' => 'Payment Success', 'status' => 'success', 'print' => ''];
    }

    /** @return array<string,mixed> */
    public function complaintAdd(?array $in): array
    {
        if (! isset($in['token'])) {
            return ['status' => 'failed', 'message' => 'Token missing'];
        }
        $agent = $this->agentByToken($in['token']);
        if (! $agent) {
            return ['status' => 'failed', 'message' => 'Invalid Token'];
        }
        $nets = DB::table('m_network')->where('created_by', $agent->created_by)->get(['lcoCode', 'id']);
        $customer = $this->customerInNetworks($in['customer_id'] ?? 0, $nets->pluck('lcoCode')->all());
        if (! $customer) {
            return ['status' => 'failed', 'message' => 'Invalid Customer'];
        }
        DB::table('m_complaints')->insert([
            'customer_id' => $customer->id,
            'type' => self::scalarOr($in['complaint_id'] ?? 0, 0),
            'network_id' => $nets[0]->id,
            'description' => (string) self::scalarOr($in['description'] ?? '', ''),
            'created_by' => $agent->id,
            'status' => 1,
            'created_on' => (new DateTime('now', self::tz()))->format('Y-m-d H:i:s'),
        ]);

        return ['message' => 'Complaint added successfully', 'status' => 'success'];
    }

    // ---------------------------------------------------------------- receipt PDF

    /**
     * Receipt PDF (90 x 150 mm "Cash Receipt") or a JSON array describing why not.
     *
     * @return array<string,mixed>|string PDF bytes when a receipt could be produced
     */
    public function paymentPrint(?array $in): array|string|null
    {
        if (! isset($in['token'])) {
            return null;   // old quirk: no output at all
        }
        $agent = $this->agentByToken($in['token']);
        if (! $agent) {
            return ['status' => 'failed', 'message' => 'Token missing'];   // old quirk: the message for a bad token
        }
        $nets = DB::table('m_network')->where('created_by', $agent->created_by)->get(['lcoCode', 'network_name', 'address', 'phone']);
        if ($nets->isEmpty()) {
            return ['status' => 'failed', 'message' => 'Invalid Token'];
        }
        $paymentId = $in['payment_id'] ?? $in['paymentid'] ?? $in['invoice_id'] ?? $in['id'] ?? 0;
        $notFound = ['status' => 'failed', 'message' => 'Invoice data not found'];
        if (! is_scalar($paymentId)) {
            return $notFound;
        }
        $inv = DB::selectOne('SELECT a.*,b.name as agent FROM subscriberpayments a left outer join agent b on b.id=a.agentid WHERE a.id = ?', [(string) $paymentId]);
        if (! $inv) {
            return $notFound;
        }
        // the old code printed any payment of any network: only payments of customers of this agent's networks are served
        $cust = DB::table('m_customer')->where('id', (string) $inv->subscriberid)->whereIn('lcoCode', $nets->pluck('lcoCode')->all())
            ->first(['customer_name', 'customer_id', 'billing_address', 'mobile']);
        if (! $cust) {
            return $notFound;
        }
        $net = $nets[0];

        return $this->renderReceipt($net, $inv, $cust);
    }

    private function renderReceipt(object $net, object $inv, object $cust): string
    {
        $t = fn (mixed $v): string => (string) iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', (string) ($v ?? ''));   // FPDF core fonts are cp1252
        $pdf = new \FPDF('P', 'mm', [90, 150]);
        $pdf->AliasNbPages();
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 20);
        $pdf->Cell(0, 10, $t($net->network_name), 0, 1, 'C');
        $pdf->SetFont('Arial', '', 10);
        $pdf->MultiCell(0, 5, $t($net->address), 0, 'C');
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(0, 8, 'Ph: '.$t($net->phone), 0, 1, 'C');
        $pdf->SetFont('Arial', 'B', 15);
        $pdf->Cell(0, 8, 'Cash Receipt', 0, 1, 'C');
        $pdf->SetFont('Arial', '', 12);
        $line = '------------------------------------------------';
        $pdf->Cell(0, 4, $line, 0, 1, 'C');
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(35, 5, $t($cust->customer_name), 0, 0, 'L', false);
        $pdf->Cell(35, 5, $t($cust->customer_id), 0, 1, 'R', false);
        $pdf->MultiCell(0, 5, $t($cust->billing_address), 0, 'L');
        $pdf->Cell(0, 5, 'P: '.$t(str_replace("'91", '', (string) $cust->mobile)), 0, 1, 'L');
        $pdf->Cell(35, 5, $t($inv->id), 0, 0, 'L', false);
        $pdf->Cell(35, 5, self::fmt($inv->timestamp), 0, 1, 'R', false);
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(0, 4, $line, 0, 1, 'C');
        $pdf->SetFont('Arial', '', 10);
        foreach ([
            'Description' => $inv->description, 'Prev BAL' => $inv->dueamount, 'Cash Paid' => $inv->paidamount,
            'Discount' => $inv->discount, 'Balance' => $inv->dbafterdiscount, 'Agent' => ($inv->agent ?? '') === '' ? 'Admin' : $inv->agent,
        ] as $label => $value) {
            $pdf->Cell(35, 5, $label, 0, 0, 'L', false);
            $pdf->Cell(35, 5, $t($value), 0, 1, 'R', false);
        }

        return $pdf->Output('S');
    }
}
