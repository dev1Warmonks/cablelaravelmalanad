<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SMS queue processing (port of Common_model::sms_payment / sms_bulk_cron_payment).
 *
 * Payment rows carry the text in subscriberpayments.sms with sms_status=1 (queued); sending sets sms_status=2,
 * sms_id=<provider response>, gateway=<sms_apis.id>. Provider URL templates live in sms_apis.api with
 * *mobile* / *message* placeholders.
 *
 * Safety: nothing is sent unless config('cablebill.sms.enabled') is true; tests must use Http::fake().
 * Differences from the old code: only the given payment row (the old sms_payment() flushed EVERY LCO's queue using
 * the current user's gateway), TLS verification stays on, and the provider URL must be http(s).
 */
class SmsService
{
    /** Sends one queued payment SMS using the owner's default gateway. Returns true when handed to the provider. */
    public function sendPayment(int $paymentId, int|string $ownerUserId): bool
    {
        $row = DB::table('subscriberpayments')->where('id', $paymentId)->where('sms_status', '1')->where('sms', '!=', '')->first(['id', 'sms', 'mobile']);
        if (! $row) {
            return false;
        }
        $apiId = DB::table('m_user')->where('id', $ownerUserId)->value('default_sms_api');
        if ($apiId === null || $apiId === '') {
            return false;
        }

        return $this->deliver($row, (int) $apiId);
    }

    /** Cron: up to $limit queued rows, each with the default gateway of the customer's LCO (any active gateway if none). */
    public function processQueue(int $limit = 100): array
    {
        $rows = DB::table('subscriberpayments')->where('sms_status', '1')->where('sms', '!=', '')->limit($limit)->get(['id', 'subscriberid', 'sms', 'mobile']);
        $sent = 0;
        foreach ($rows as $row) {
            $apiId = 0;
            if (! empty($row->subscriberid)) {
                $apiId = (int) DB::table('m_customer as c')->join('m_user as u', 'u.lcoCode', '=', 'c.lcoCode')
                    ->where('c.id', (int) $row->subscriberid)->where('u.default_sms_api', '>', 0)->value('u.default_sms_api');
            }
            if ($this->deliver($row, $apiId)) {
                $sent++;
                usleep(100000);
            }
        }
        Log::channel('single')->info('sms queue', ['processed' => $rows->count(), 'sent' => $sent, 'enabled' => config('cablebill.sms.enabled')]);

        return ['processed' => $rows->count(), 'sent' => $sent];
    }

    private function deliver(object $row, int $apiId): bool
    {
        $mobile = str_replace("'91", '', (string) $row->mobile);
        if ($mobile === '' || strlen($mobile) !== 10 || ! ctype_digit($mobile)) {
            return false;
        }
        $api = DB::table('sms_apis')->where('status', 1)->when($apiId > 0, fn ($q) => $q->where('id', $apiId))->orderBy('id')->first(['id', 'api', 'provider']);
        if (! $api) {
            return false;
        }

        $url = str_replace(['*mobile*', '*message*'], [$mobile, rawurlencode((string) $row->sms)], $api->api);
        $url = preg_replace('/ /', '%20', $url);
        if ($api->provider === 'textsms') {
            $url .= config('cablebill.sms.textsms_template_id');
        }
        if (! preg_match('#^https?://#i', $url)) {
            Log::warning('sms: provider url is not http(s)', ['api' => $api->id]);

            return false;
        }
        if (! config('cablebill.sms.enabled')) {
            Log::info('sms suppressed (SMS_SEND_ENABLED=false)', ['payment' => $row->id, 'api' => $api->id]);

            return false;
        }

        try {
            $response = Http::timeout(config('cablebill.sms.timeout'))->get($url);
            $body = (string) $response->body();
        } catch (\Throwable $e) {
            Log::error('sms send failed', ['payment' => $row->id, 'error' => $e->getMessage()]);

            return false;
        }
        if ($api->provider === 'textsms') {
            $body = json_encode(json_decode($body, true));   // same normalisation as the old code
        }
        DB::table('subscriberpayments')->where('id', $row->id)->update(['sms_status' => 2, 'sms_id' => $body, 'gateway' => $api->id]);

        return true;
    }
}
