<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Miscellaneous charges and voucher deletion on the customer ledger (ports of Transaction::add_miscellaneous,
 * add_bulk_miscellaneous and delete_voucher). Same row layout as PaymentService; customer rows are locked while writing.
 */
class LedgerService
{
    /** PHP-style number text, as the old code printed floats inside SMS messages (9327.0 -> "9327"). */
    private function num(float|string $v): string
    {
        return (string) (float) $v;
    }

    private function money(string $v): string
    {
        $v = trim($v);
        if (! preg_match('/^-?\d{1,8}(\.\d{1,2})?$/', $v)) {
            throw new \InvalidArgumentException('Invalid amount');
        }

        return bcadd($v, '0', 2);
    }

    /** "<head> (<comment>)" description, or null when the head does not exist. */
    public function description(string|int $headId, string $comment): ?string
    {
        $name = DB::table('m_dropdown')->where('id', $headId)->where('type', 3)->value('name');
        if ($name === null) {
            return null;
        }

        return $comment !== '' ? $name.' ('.$comment.')' : $name;
    }

    private function smsText(string $name, string $description, string $rawAmount, string $after, string $signature): string
    {
        return 'ctopup e charge llp Dear '.$name.', You have a bill for '.$description.' Rs.'.$rawAmount.' . Your balance due amount is Rs '.$this->num($after).'. Thank you '.$signature.'.';
    }

    /** misc_add_sms flag and signature of the LCO user that owns the charge. */
    private function smsContext(int|string $ownerUserId): array
    {
        $enabled = (int) DB::table('m_user')->where('id', $ownerUserId)->value('misc_add_sms') === 1;
        $signature = $enabled ? (string) (DB::table('m_network')->where('created_by', $ownerUserId)->value('sms_signature') ?? '') : '';

        return [$enabled, $signature];
    }

    /**
     * One customer. $isMiscFlag: add_miscellaneous wrote is_misc=0 (column default) while the bulk screen's single mode wrote 1;
     * both are kept as they were because reports filter on it.
     */
    public function chargeOne(string|int $customerId, string $description, string $amount, int|string $agentId, int|string $ownerUserId, bool $isMiscFlag): array
    {
        $raw = trim($amount);
        $amt = $this->money($amount);

        return DB::transaction(function () use ($customerId, $description, $raw, $amt, $agentId, $ownerUserId, $isMiscFlag) {
            $customer = DB::table('m_customer')->where('id', $customerId)->lockForUpdate()->first(['id', 'mobile', 'customer_name', 'sms']);
            $due = $this->money((string) (DB::table('subscriberbalance')->where('subscriberid', $customerId)->value('amountdue') ?? '0'));
            $after = bcadd($due, $amt, 2);
            $ts = PaymentService::milliseconds();
            $row = [
                'transactiontype' => '3', 'subscriberid' => (string) $customerId, 'agentid' => (string) $agentId, 'description' => $description,
                'dueamount' => $due, 'paidamount' => 0, 'discount' => 0, 'subscriptioncharge' => $amt, 'duebalance' => $after,
                'dbafterdiscount' => $after, 'timestamp' => $ts,
            ];
            if ($isMiscFlag) {
                $row['is_misc'] = 1;
            }
            $id = DB::table('subscriberpayments')->insertGetId($row);

            if (DB::table('subscriberbalance')->where('subscriberid', $customerId)->exists()) {
                DB::table('subscriberbalance')->where('subscriberid', $customerId)->update(['amountdue' => $after, 'timestamp' => $ts]);
            } else {
                DB::table('subscriberbalance')->insert(['subscriberid' => $customerId, 'amountdue' => $after, 'timestamp' => $ts]);
            }

            [$smsOn, $signature] = $this->smsContext($ownerUserId);
            if ($smsOn && (int) $customer->sms === 1) {
                $mobile = str_replace("'91", '', (string) $customer->mobile);
                if ($mobile !== '' && strlen($mobile) === 10) {
                    DB::table('subscriberpayments')->where('id', $id)->update([
                        'sms' => $this->smsText($customer->customer_name, $description, $raw, $after, $signature), 'sms_status' => 1, 'mobile' => $mobile,
                    ]);
                }
            }

            return ['id' => $id, 'balance_after' => $after];
        });
    }

    /** Every customer of the LCO except status 2 (bulk screen, "all"). One transaction; all-or-nothing. */
    public function chargeAll(string $lcoCode, string $description, string $amount, int|string $agentId, int|string $ownerUserId): int
    {
        $raw = trim($amount);
        $amt = $this->money($amount);
        [$smsOn, $signature] = $this->smsContext($ownerUserId);

        $count = DB::transaction(function () use ($lcoCode, $description, $raw, $amt, $agentId, $smsOn, $signature) {
            $customers = DB::table('m_customer')->where('lcoCode', $lcoCode)->where('status', '!=', 2)->orderBy('id')->lockForUpdate()
                ->get(['id', 'mobile', 'customer_name', 'sms']);
            if ($customers->isEmpty()) {
                return 0;
            }
            $ids = $customers->pluck('id')->all();
            $balances = [];   // subscriberid => [balance row id, amountdue]
            foreach (array_chunk($ids, 1000) as $chunk) {
                foreach (DB::table('subscriberbalance')->whereIn('subscriberid', $chunk)->get(['id', 'subscriberid', 'amountdue']) as $b) {
                    $balances[$b->subscriberid] = [$b->id, $b->amountdue];
                }
            }

            $base = (int) PaymentService::milliseconds();
            $payments = [];
            $updates = [];
            $inserts = [];
            foreach ($customers as $i => $c) {
                $due = $this->money((string) ($balances[$c->id][1] ?? '0'));
                $after = bcadd($due, $amt, 2);
                $ts = (string) ($base + $i);
                $sms = ''; $status = 0; $mobile = '';
                if ($smsOn && (int) $c->sms === 1) {
                    $m = str_replace("'91", '', (string) $c->mobile);
                    if ($m !== '' && strlen($m) === 10) {
                        $mobile = $m; $status = 1;
                        $sms = $this->smsText($c->customer_name, $description, $raw, $after, $signature);
                    }
                }
                $payments[] = [
                    'transactiontype' => '3', 'is_misc' => 1, 'subscriberid' => (string) $c->id, 'agentid' => (string) $agentId, 'description' => $description,
                    'dueamount' => $due, 'paidamount' => 0, 'discount' => 0, 'subscriptioncharge' => $amt, 'duebalance' => $after, 'dbafterdiscount' => $after,
                    'timestamp' => $ts, 'sms' => $sms, 'sms_status' => (string) $status, 'mobile' => $mobile,
                ];
                if (isset($balances[$c->id])) {
                    $updates[$balances[$c->id][0]] = [$after, $ts];
                } else {
                    $inserts[] = ['subscriberid' => $c->id, 'amountdue' => $after, 'timestamp' => $ts];
                }
            }
            foreach (array_chunk($payments, 200) as $chunk) {
                DB::table('subscriberpayments')->insert($chunk);
            }
            foreach (array_chunk($updates, 300, true) as $chunk) {   // by primary key (subscriberbalance has no index on subscriberid)
                $caseDue = ''; $caseTs = ''; $bind = [];
                foreach ($chunk as $bid => [$after, $ts]) {
                    $caseDue .= ' WHEN ? THEN ?';
                    array_push($bind, $bid, $after);
                }
                $bindTs = [];
                foreach ($chunk as $bid => [$after, $ts]) {
                    $caseTs .= ' WHEN ? THEN ?';
                    array_push($bindTs, $bid, $ts);
                }
                $in = implode(',', array_fill(0, count($chunk), '?'));
                DB::update("UPDATE subscriberbalance SET amountdue = CASE id{$caseDue} END, `timestamp` = CASE id{$caseTs} END WHERE id IN ({$in})", array_merge($bind, $bindTs, array_keys($chunk)));
            }
            if ($inserts) {
                DB::table('subscriberbalance')->insert($inserts);
            }

            return count($customers);
        });
        Log::channel('audit')->info('bulk miscellaneous charge', ['lco' => $lcoCode, 'description' => $description, 'amount' => $amt, 'customers' => $count, 'by' => session('userid')]);

        return $count;
    }

    /**
     * Deletes the LATEST ledger row of a customer and restores the balance to that row's "due before".
     * Only the newest row can be removed (as before), now under a lock and with an audit entry.
     * Returns "Deleted" or "Failed" (the old method's plain-text replies).
     */
    public function deleteVoucher(int|string $voucherId, string $lcoCode, string $isAdmin): string
    {
        return DB::transaction(function () use ($voucherId, $lcoCode, $isAdmin) {
            $row = DB::table('subscriberpayments')->where('id', $voucherId)->first();
            if (! $row) {
                return 'Failed';
            }
            $customerQuery = DB::table('m_customer')->where('id', (int) $row->subscriberid);
            if ($isAdmin !== '1') {
                $customerQuery->where('lcoCode', $lcoCode);
            }
            if (! $customerQuery->lockForUpdate()->exists()) {
                return 'Failed';
            }
            $latest = DB::table('subscriberpayments')->where('subscriberid', (string) $row->subscriberid)->orderByDesc('id')->value('id');
            if ((string) $latest !== (string) $row->id) {
                return 'Failed';
            }
            DB::table('subscriberpayments')->where('id', $row->id)->delete();
            DB::table('subscriberbalance')->where('subscriberid', $row->subscriberid)->update(['amountdue' => $row->dueamount]);
            Log::channel('audit')->info('voucher deleted', ['by' => session('userid'), 'username' => session('username'), 'ip' => request()->ip(), 'row' => (array) $row]);

            return 'Deleted';
        });
    }
}
