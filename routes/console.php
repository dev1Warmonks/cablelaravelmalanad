<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Queued SMS (payment receipts + monthly bills): every minute, never two runs at once. Needs the standard `schedule:run` cron entry on the server.
Schedule::command('sms:send-queued')->everyMinute()->withoutOverlapping();
