<?php

namespace App\Http\Controllers;

use App\Services\SmsService;
use Illuminate\Http\Request;

/**
 * Old public URL SmsSendCron/index (anyone could trigger the SMS run). Kept for an external cron that still calls it, but only with the
 * secret (?key=SMS_CRON_SECRET); without a configured secret the URL does not exist. Prefer `php artisan sms:send-queued` (scheduled).
 */
class SmsCronController extends Controller
{
    public function index(Request $request, SmsService $sms, ?string $mobile = null)
    {
        $secret = (string) config('cablebill.sms.cron_secret');
        $given = $request->query('key');
        abort_unless($secret !== '' && is_string($given) && hash_equals($secret, $given), 404);
        $sms->processQueue(100);
        $sms->processInvoiceQueue(null, 100);

        return response("Bulk SMS cron execution finished.\n", 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
