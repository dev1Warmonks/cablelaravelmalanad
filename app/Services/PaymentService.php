<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Ledger writes for a customer payment (port of Transaction::add_payment, also used by the agent API later).
 *
 * subscriberpayments is a running ledger: each row stores dueamount (balance before), paidamount, discount,
 * duebalance (= due - paid) and dbafterdiscount (= due - paid - discount); subscriberbalance.amountdue mirrors the last row.
 * The old code read the balance, computed in PHP floats and wrote it back with no transaction/lock, so two
 * simultaneous payments for one customer could lose an update. Here the customer row is locked for the duration
 * of the write and all arithmetic is exact (bcmath, 2 decimals = the column scale).
 */
class PaymentService
{
    /** Milliseconds since epoch as a string (Transaction::milliseconds()). */
    public static function milliseconds(): string
    {
        $mt = explode(' ', microtime());

        return sprintf('%.0f', $mt[1] * 1000 + round($mt[0] * 1000));
    }

    /**
     * @param  array{subscriberid:int|string, paidamount:string, discount?:string, comment?:string, trantype:int|string, othermobile?:string}  $in
     * @return array{id:int, due_before:string, balance_after:string, description:string}
     */
    public function record(array $in, int|string $agentId, int|string $ownerUserId): array
    {
        $subscriberId = (string) $in['subscriberid'];
        $rawPaid = trim((string) $in['paidamount']);   // the SMS text shows the amount exactly as typed
        $paid = $this->money($in['paidamount']);
        $discount = $this->money(($in['discount'] ?? '') === '' ? '0' : $in['discount']);
        $trantype = (string) $in['trantype'];
        $comment = (string) ($in['comment'] ?? '');
        $otherMobile = (string) ($in['othermobile'] ?? '');
        $isApp = (bool) ($in['is_app'] ?? false);   // payments made through the mobile app (AgentApi) are flagged is_app=1

        return DB::transaction(function () use ($subscriberId, $rawPaid, $paid, $discount, $trantype, $comment, $otherMobile, $agentId, $ownerUserId, $isApp) {
            // Mutex per customer (the customer row always exists, unlike a not-yet-created balance row).
            DB::table('m_customer')->where('id', $subscriberId)->lockForUpdate()->first(['id']);

            $isMisc = 0;
            $description = '';
            if ($trantype !== '1') {
                $head = DB::table('m_dropdown')->where('id', $trantype)->first(['name']);
                if ($head) {
                    $isMisc = 1;
                    $description = $head->name;
                }
            } else {
                $description = 'Cable subscription';
            }
            if ($comment !== '') {
                $description .= ' ('.$comment.')';
            }

            $due = (string) (DB::table('subscriberbalance')->where('subscriberid', $subscriberId)->value('amountdue') ?? '0');
            $due = $this->money($due);
            $dueBalance = bcsub($due, $paid, 2);
            $after = bcsub($dueBalance, $discount, 2);
            $timestamp = self::milliseconds();

            $paymentId = DB::table('subscriberpayments')->insertGetId([
                'transactiontype' => '1',
                'subscriberid' => $subscriberId,
                'agentid' => (string) $agentId,
                'description' => $description,
                'dueamount' => $due,
                'paidamount' => $paid,
                'discount' => $discount,
                'subscriptioncharge' => 0,
                'duebalance' => $dueBalance,
                'dbafterdiscount' => $after,
                'timestamp' => $timestamp,
                'is_misc' => $isMisc,
            ] + ($isApp ? ['is_app' => 1] : []));

            if (DB::table('subscriberbalance')->where('subscriberid', $subscriberId)->exists()) {
                DB::table('subscriberbalance')->where('subscriberid', $subscriberId)->update(['amountdue' => $after, 'timestamp' => $timestamp]);
            } else {
                DB::table('subscriberbalance')->insert(['subscriberid' => $subscriberId, 'amountdue' => $after, 'timestamp' => $timestamp]);
            }

            $this->queueSms($paymentId, $subscriberId, $rawPaid, $description, $after, $otherMobile, $ownerUserId);

            return ['id' => $paymentId, 'due_before' => $due, 'balance_after' => $after, 'description' => $description];
        });
    }

    /** Stores the SMS text on the payment row (sms_status=1 = queued) when the owner and customer have SMS enabled. */
    private function queueSms(int $paymentId, string $subscriberId, string $paid, string $description, string $after, string $otherMobile, int|string $ownerUserId): void
    {
        $owner = DB::table('m_user')->where('id', $ownerUserId)->first(['sms']);
        if (! $owner || (int) $owner->sms !== 1) {
            return;
        }
        $customer = DB::table('m_customer')->where('id', $subscriberId)->where('sms', 1)->first(['mobile', 'customer_name']);
        if (! $customer) {
            return;
        }
        $mobile = $otherMobile === '' ? str_replace("'91", '', (string) $customer->mobile) : $otherMobile;
        if ($mobile === '' || strlen($mobile) !== 10) {
            return;
        }
        $signature = (string) (DB::table('m_network')->where('created_by', $ownerUserId)->value('sms_signature') ?? '');
        $message = 'ctopup Dear '.$customer->customer_name.', We received your pmt of Rs '.$paid.' for '.$description
            .'. Your bal due amount is Rs '.number_format((float) $after, 2, '.', '').'. Thank you '.$signature;

        DB::table('subscriberpayments')->where('id', $paymentId)->update([
            'sms' => $message, 'sms_status' => 1, 'mobile' => $mobile, 'is_other_mobile' => $otherMobile !== '' ? 1 : 0,
        ]);
    }

    /** Validated 2-decimal money string (the controller validates first; this guards direct callers). */
    private function money(mixed $v): string
    {
        $v = trim((string) $v);
        if (! preg_match('/^-?\d+(\.\d{1,2})?$/', $v)) {
            throw new \InvalidArgumentException('Invalid amount');
        }

        return bcadd($v, '0', 2);
    }
    /**
     * Broadband payment (port of Transaction::add_broadband_payment): same ledger row as a cash payment, but the description is the
     * typed comment, no SMS is queued and is_misc stays 0. Locked and exact like record().
     *
     * @return array{id:int, due_before:string, balance_after:string}
     */
    public function recordBroadband(int|string $subscriberId, string $paid, string $discount, string $comment, int|string $agentId): array
    {
        $paidM = $this->money($paid);
        $discM = $this->money($discount === '' ? '0' : $discount);
        $subscriberId = (string) $subscriberId;

        return DB::transaction(function () use ($subscriberId, $paidM, $discM, $comment, $agentId) {
            DB::table('m_customer')->where('id', $subscriberId)->lockForUpdate()->first(['id']);
            $due = $this->money((string) (DB::table('subscriberbalance')->where('subscriberid', $subscriberId)->value('amountdue') ?? '0'));
            $dueBalance = bcsub($due, $paidM, 2);
            $after = bcsub($dueBalance, $discM, 2);
            $ts = self::milliseconds();
            $id = DB::table('subscriberpayments')->insertGetId([
                'transactiontype' => '1', 'subscriberid' => $subscriberId, 'agentid' => (string) $agentId, 'description' => $comment,
                'dueamount' => $due, 'paidamount' => $paidM, 'discount' => $discM, 'subscriptioncharge' => 0,
                'duebalance' => $dueBalance, 'dbafterdiscount' => $after, 'timestamp' => $ts,
            ]);
            if (DB::table('subscriberbalance')->where('subscriberid', $subscriberId)->exists()) {
                DB::table('subscriberbalance')->where('subscriberid', $subscriberId)->update(['amountdue' => $after, 'timestamp' => $ts]);
            } else {
                DB::table('subscriberbalance')->insert(['subscriberid' => $subscriberId, 'amountdue' => $after, 'timestamp' => $ts]);
            }

            return ['id' => $id, 'due_before' => $due, 'balance_after' => $after];
        });
    }
}
