<?php

return [
    // false while the old CodeIgniter site shares the DB (plain-text compare, see PasswordVerifier)
    'hash_passwords' => (bool) env('CABLEBILL_HASH_PASSWORDS', false),

    // Login throttling: attempts per minute per IP+username
    'login_max_attempts' => (int) env('CABLEBILL_LOGIN_MAX_ATTEMPTS', 5),

    'sms' => [
        // SAFETY: the DB copy contains real provider URLs/keys and real customer numbers. Nothing is sent unless this is true
        // (production .env only). When false, queued rows stay queued and the attempt is logged.
        'enabled' => (bool) env('SMS_SEND_ENABLED', false),
        // DLT template id the old code appended for the "textsms" provider (an identifier, not a secret).
        'textsms_template_id' => env('SMS_TEXTSMS_TEMPLATE_ID', '1207161822292728382'),
        // DLT template id of the monthly-bill SMS (a different template than the payment SMS).
        'textsms_invoice_template_id' => env('SMS_TEXTSMS_INVOICE_TEMPLATE_ID', '1207162027495529783'),
        'timeout' => 5,
        // Secret for the legacy URL SmsSendCron/index (/SmsSendCron/<secret>); empty = URL disabled, use `php artisan sms:send-queued`.
        'cron_secret' => env('SMS_CRON_SECRET'),
    ],

    'upigateway' => [
        // Never commit the key. Tests must always fake the HTTP client (Http::fake) and never use a live key.
        'key' => env('UPIGATEWAY_KEY'),
        'base_url' => env('UPIGATEWAY_BASE_URL', 'https://merchant.upigateway.com/api'),
        'redirect_url' => env('UPIGATEWAY_REDIRECT_URL'),
        'customer_email' => env('UPIGATEWAY_CUSTOMER_EMAIL'),
        'default_phone' => env('UPIGATEWAY_DEFAULT_PHONE'),
        'timeout' => 15,
    ],
];
