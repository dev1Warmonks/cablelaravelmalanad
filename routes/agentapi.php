<?php

use App\Http\Controllers\AgentApiController;
use Illuminate\Support\Facades\Route;

/*
| Mobile app API: /AgentApi/<method> (same URLs as the CodeIgniter app). Registered WITHOUT the web middleware group:
| no session, no cookies, no CSRF - the app authenticates with the agent token in the JSON body.
*/
foreach ([
    'login', 'verify', 'subscriber_list', 'transaction_type_list', 'area_list', 'print_header', 'add_payment',
    'payment_print', 'subscriber_transaction_history', 'complaint_type_list', 'complaint_add', 'dalily_report',
] as $method) {
    Route::match(['get', 'post'], 'AgentApi/'.$method, [AgentApiController::class, $method]);
    Route::match(['get', 'post'], 'agentapi/'.$method, [AgentApiController::class, $method]);
}
