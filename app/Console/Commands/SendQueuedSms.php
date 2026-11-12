<?php

namespace App\Console\Commands;

use App\Services\SmsService;
use Illuminate\Console\Command;

/** Sends the queued SMS (payment receipts and monthly bills). Replaces the old SmsSendCron web URL; nothing leaves unless SMS_SEND_ENABLED=true. */
class SendQueuedSms extends Command
{
    protected $signature = 'sms:send-queued {--limit=100 : rows per queue and run}';

    protected $description = 'Send queued payment and bill SMS';

    public function handle(SmsService $sms): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $p = $sms->processQueue($limit);
        $i = $sms->processInvoiceQueue(null, $limit);
        $this->info(sprintf('payments: %d processed, %d sent; bills: %d processed, %d sent%s', $p['processed'], $p['sent'], $i['processed'], $i['sent'],
            config('cablebill.sms.enabled') ? '' : ' (SMS_SEND_ENABLED=false: nothing is actually sent)'));

        return self::SUCCESS;
    }
}
