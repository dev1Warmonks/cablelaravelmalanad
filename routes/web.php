<?php

use App\Crud\Crud;
use App\Http\Controllers\AdminSettingsController;
use App\Http\Controllers\AgentController;
use App\Http\Controllers\BroadbandController;
use App\Http\Controllers\ComplaintController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\MasterController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SmsCronController;
use App\Http\Controllers\SubscriberController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

/*
 | Legacy URLs: CodeIgniter routing is case-insensitive (login, Login, LOGIN) and has the
 | form Controller/method/params. Explicit routes (no catch-all) so each has its own middleware.
 | legacy() registers the lower-case and Capitalised variants of a URL (the forms the old site used).
 */
if (! function_exists('legacy')) {
    function legacy(array $methods, string $uri, $action, array $middleware = [], array $where = []): void
    {
        $segments = explode('/', $uri);
        $variants = array_unique([
            $uri,
            strtolower($uri),
            ucfirst(strtolower($segments[0])).(count($segments) > 1 ? '/'.implode('/', array_slice($segments, 1)) : ''),
        ]);
        foreach ($variants as $i => $variant) {
            $route = Route::match($methods, $variant, $action)->middleware($middleware)->where($where);
            if ($i === 0 && ! str_contains($uri, '{')) {
                $route->name('legacy.'.strtolower(str_replace('/', '.', $uri)));
            }
        }
    }
}   // (guarded: the test suite boots the app, and so loads this file, once per test)

// Default controller = Login
Route::get('/', [LoginController::class, 'index']);
legacy(['GET'], 'Login', [LoginController::class, 'index']);
legacy(['GET'], 'login/index', [LoginController::class, 'index']);
legacy(['POST'], 'login/process_login', [LoginController::class, 'processLogin']);
// CI allowed GET on any method; the old process_login redirected non-POST to login.
legacy(['GET'], 'login/process_login', fn () => redirect('login'));
legacy(['GET'], 'login/logout', [LoginController::class, 'logout']);

// Master (usertype 1 and 2; blocked LCOs are logged out, as in Master::__construct)
$master = ['legacy.session:1,2', 'legacy.network.notblocked'];
legacy(['GET'], 'master', [MasterController::class, 'index'], $master);
legacy(['GET'], 'master/index', [MasterController::class, 'index'], $master);
legacy(['GET'], 'master/change_network/{id}', [MasterController::class, 'changeNetwork'], $master);

// Reporting (usertype 1 and 2, as Report::__construct)
$report = ['legacy.session:1,2'];
foreach ([
    'agent_report' => 'agentReport', 'date_wise_report' => 'dateWiseReport', 'month_wise_report' => 'monthWiseReport',
    'miscellaneous_report' => 'miscellaneousReport', 'agent_wise_collection_report' => 'agentWiseCollectionReport',
    'expense_report' => 'expenseReport', 'discount_report' => 'discountReport',
] as $uri => $method) {
    legacy(['GET'], "report/$uri", [ReportController::class, $method], $report);
}
foreach ([
    'agent_report_data' => 'agentReportData', 'date_wise_report_data' => 'dateWiseReportData', 'daily_report_data' => 'dailyReportData',
    'network_daily_report_data' => 'networkDailyReportData', 'monthly_report_data' => 'monthlyReportData', 'payment_sms' => 'paymentSms',
    'agent_wise_collection_report_data' => 'agentWiseCollectionReportData', 'expense_report_data' => 'expenseReportData',
    'discount_report_data' => 'discountReportData',
] as $uri => $method) {
    legacy(['POST'], "report/$uri", [ReportController::class, $method], $report);
}

// Imports (usertype 1 and 2, as Import::__construct; menu checks inside the controller)
$import = ['legacy.session:1,2'];
foreach ([
    'subscribers' => 'subscribers', 'importsuscriberfromezybill' => 'importsuscriberfromezybill', 'importsuscriberfromezybill_f2' => 'importsuscriberfromezybillF2',
    'importsubscriberopeningbalance' => 'importsubscriberopeningbalance', 'importproducts' => 'importproducts', 'downloadopeningbalancexcel' => 'downloadopeningbalancexcel',
] as $uri => $method) {
    legacy(['GET'], "import/$uri", [ImportController::class, $method], $import);
}
foreach ([
    'importsubscribers' => 'importsubscribers', 'import_suscriberfromezybill' => 'postEzybill', 'import_suscriberfromezybill_f2' => 'postEzybillF2',
    'update_database' => 'updateDatabase', 'updatesubscribersopeningbalance' => 'updatesubscribersopeningbalance', 'import_products' => 'postProducts',
] as $uri => $method) {
    legacy(['POST'], "import/$uri", [ImportController::class, $method], $import);
}
legacy(['GET'], 'import/send_sms_to_pending_customers/{month}/{userid?}', [ImportController::class, 'sendSmsToPendingCustomers'], $import);
legacy(['POST'], 'import/sendsmstopendingcustomers/{month}', [ImportController::class, 'postSendSms'], $import);

// Complaints / enquiries (usertype 1 and 2, as Complaint::__construct; menu 7 inside the controller for the enquiry screens)
$complaint = ['legacy.session:1,2'];
legacy(['GET'], 'complaint', [ComplaintController::class, 'index'], $complaint);
legacy(['GET'], 'complaint/index', [ComplaintController::class, 'index'], $complaint);
legacy(['GET'], 'complaint/view_complaint/{id}', [ComplaintController::class, 'viewComplaint'], $complaint);
legacy(['GET'], 'complaint/edit_complaint/{id}', [ComplaintController::class, 'editComplaint'], $complaint);
legacy(['GET'], 'complaint/complaint_add_view', [ComplaintController::class, 'complaintAddView'], $complaint);
legacy(['POST'], 'complaint/add_complaint', [ComplaintController::class, 'addComplaint'], $complaint);
legacy(['POST'], 'complaint/update_complaint', [ComplaintController::class, 'updateComplaint'], $complaint);
foreach (['enquiry' => 'enquiry', 'complaint_type_management' => 'complaintTypeManagement', 'enquiry_type_management' => 'enquiryTypeManagement'] as $uri => $method) {
    legacy(['GET', 'POST'], "complaint/$uri/{state?}/{p1?}/{p2?}", [ComplaintController::class, $method], $complaint, ['state' => implode('|', Crud::STATES)]);
}

// SMS cron URL (needs ?key=SMS_CRON_SECRET) and the public privacy policy page. Not ported: Cache/clear_opcache (hard-coded key 12345),
// Result (lottery leftovers with unauthenticated writes, like Main).
legacy(['GET'], 'SmsSendCron', [SmsCronController::class, 'index'], ['throttle:6,1']);
legacy(['GET'], 'SmsSendCron/index/{mobile?}', [SmsCronController::class, 'index'], ['throttle:6,1']);
legacy(['GET'], 'SmsSendCron/send_bulk_sms/{mobile?}', [SmsCronController::class, 'index'], ['throttle:6,1']);
legacy(['GET'], 'PrivacyPolicy/details', fn () => view('privacypolicy.details'));
// Agent panel (usertype 3, as Agent::__construct)
$agent = ['legacy.session:3'];
legacy(['GET'], 'agent', [AgentController::class, 'index'], $agent);
legacy(['GET'], 'agent/index', [AgentController::class, 'index'], $agent);
legacy(['GET'], 'agent/payment', [AgentController::class, 'payment'], $agent);
legacy(['GET'], 'agent/change_area/{area}', [AgentController::class, 'changeArea'], $agent);
legacy(['GET'], 'agent/date_wise_report', [AgentController::class, 'dateWiseReport'], $agent);
legacy(['POST'], 'agent/date_wise_report_data', [AgentController::class, 'dateWiseReportData'], $agent);
legacy(['POST'], 'agent/daily_report_data', [AgentController::class, 'dailyReportData'], $agent);

// Master CRUD screens: state (list/add/edit/...) and id follow the method name, like the old Grocery_CRUD URLs.
foreach ([
    'area_management' => 'areaManagement', 'subarea_management' => 'subareaManagement',
    'products_category_management' => 'productsCategoryManagement', 'products_management' => 'productsManagement',
    'taxrate_management' => 'taxrateManagement', 'package_management' => 'packageManagement',
    'network_management' => 'networkManagement', 'stb_management' => 'stbManagement',
    'stb_model_management' => 'stbModelManagement', 'stb_make_management' => 'stbMakeManagement',
    'stb_cas_management' => 'stbCasManagement', 'stb_type_management' => 'stbTypeManagement',
    'agent_management' => 'agentManagement', 'miscellaneous_heads_management' => 'miscellaneousHeadsManagement',
] as $uri => $method) {
    legacy(['GET', 'POST'], "master/$uri/{state?}/{p1?}/{p2?}", [MasterController::class, $method], $master);
}

// AdminSettings: administrator only (the old controller had no auth check at all)
$admin = ['legacy.admin'];
foreach (['payment_types' => 'paymentTypes', 'sms_apis' => 'smsApis', 'user_main_menus' => 'userMainMenus', 'users_management' => 'usersManagement', 'import_formats' => 'importFormats'] as $uri => $method) {
    legacy(['GET', 'POST'], "AdminSettings/$uri/{state?}/{p1?}/{p2?}", [AdminSettingsController::class, $method], $admin);
}
legacy(['GET'], 'AdminSettings/general', [AdminSettingsController::class, 'general'], $admin);
legacy(['POST'], 'AdminSettings/settings_save', [AdminSettingsController::class, 'settingsSave'], $admin);
legacy(['POST'], 'AdminSettings/scripts/{lcoCode?}', [AdminSettingsController::class, 'scripts'], $admin);
legacy(['POST'], 'AdminSettings/update_last_paid', [AdminSettingsController::class, 'updateLastPaid'], $admin);
legacy(['POST'], 'AdminSettings/activate_generate_bill_all', [AdminSettingsController::class, 'activateGenerateBillAll'], $admin);
legacy(['GET'], 'AdminSettings/backup', [AdminSettingsController::class, 'backup'], $admin);
legacy(['POST'], 'AdminSettings/database_list', [AdminSettingsController::class, 'databaseList'], $admin);
legacy(['POST'], 'AdminSettings/generateBackup', [AdminSettingsController::class, 'generateBackup'], $admin);
legacy(['POST'], 'AdminSettings/deleteBackup/{name}', [AdminSettingsController::class, 'deleteBackup'], $admin);
legacy(['GET'], 'AdminSettings/download_backup/{name}', [AdminSettingsController::class, 'downloadBackup'], $admin);

// Settings (usertype 1 or 2)
$settings = ['legacy.session:1,2', 'legacy.network.notblocked'];
legacy(['GET'], 'Settings/general', [SettingsController::class, 'general'], $settings);
legacy(['POST'], 'Settings/settings_save', [SettingsController::class, 'settingsSave'], $settings);
$crudStates = ['state' => implode('|', Crud::STATES)];
legacy(['GET', 'POST'], 'Settings/index/{state?}/{p1?}/{p2?}', [SettingsController::class, 'index'], $settings, $crudStates);
legacy(['GET', 'POST'], 'Settings/{state?}/{p1?}/{p2?}', [SettingsController::class, 'index'], $settings, $crudStates);
// Subscriber (usertype 1 and 2; blocked LCOs are logged out like Master)
$sub = ['legacy.session:1,2', 'legacy.network.notblocked'];
legacy(['GET', 'POST'], 'Subscriber', [SubscriberController::class, 'index'], $sub);
legacy(['GET', 'POST'], 'Subscriber/index/{type?}', [SubscriberController::class, 'index'], $sub);
legacy(['GET', 'POST'], 'Subscriber/inactive_subscriber/{type?}', [SubscriberController::class, 'inactive'], $sub);
legacy(['GET', 'POST'], 'Subscriber/all_subscriber/{type?}', [SubscriberController::class, 'all'], $sub);
legacy(['GET', 'POST'], 'Subscriber/Mlist', [SubscriberController::class, 'mlistPage'], $sub);
legacy(['GET'], 'Subscriber/non_paid_this_month_report', [SubscriberController::class, 'nonPaidThisMonth'], $sub);
legacy(['GET'], 'Subscriber/view_subscriber/{id}', [SubscriberController::class, 'view'], $sub);
legacy(['GET'], 'Subscriber/edit_subscriber_view/{id}', [SubscriberController::class, 'edit'], $sub);
legacy(['POST'], 'Subscriber/update_subscriber', [SubscriberController::class, 'update'], $sub);
legacy(['POST'], 'Subscriber/updateSMSstat', [SubscriberController::class, 'updateSmsStat'], $sub);
legacy(['POST'], 'Subscriber/update_mobile', [SubscriberController::class, 'updateMobile'], $sub);
legacy(['POST'], 'Subscriber/update_combo_plan', [SubscriberController::class, 'updateComboPlan'], $sub);
legacy(['POST'], 'Subscriber/sub_area', [SubscriberController::class, 'subArea'], $sub);
legacy(['POST'], 'Subscriber/get_subscriber_details', [SubscriberController::class, 'details'], $sub);
legacy(['GET', 'POST'], 'Subscriber/subscriber_management/{state?}/{p1?}/{p2?}', [SubscriberController::class, 'management'], $sub, ['state' => implode('|', Crud::STATES)]);

// Transaction (billing core). The three payment endpoints are shared with the agent panel (usertype 3).
$txn = ['legacy.session:1,2', 'legacy.network.notblocked'];
$txnAll = ['legacy.session:1,2,3'];
legacy(['GET'], 'Transaction', [TransactionController::class, 'index'], $txn);
legacy(['GET'], 'Transaction/index', [TransactionController::class, 'index'], $txn);
legacy(['GET'], 'Transaction/all_network_transaction', [TransactionController::class, 'allNetworkTransaction'], $txn);
legacy(['GET'], 'Transaction/payment', [TransactionController::class, 'payment'], $txn);
legacy(['POST'], 'Transaction/subscriber_details', [TransactionController::class, 'subscriberDetails'], $txnAll);
legacy(['POST'], 'Transaction/subscriber_payments', [TransactionController::class, 'subscriberPayments'], $txnAll);
legacy(['POST'], 'Transaction/add_payment', [TransactionController::class, 'addPayment'], $txnAll);
legacy(['GET'], 'Transaction/ledger', [TransactionController::class, 'ledger'], $txn);
legacy(['GET'], 'Transaction/ledger/{subscriber}', [TransactionController::class, 'ledger'], $txn);
legacy(['POST'], 'Transaction/ledger_result', [TransactionController::class, 'ledgerResult'], $txn);
legacy(['POST'], 'Transaction/delete_voucher', [TransactionController::class, 'deleteVoucher'], $txn);
legacy(['GET'], 'Transaction/miscellaneous', [TransactionController::class, 'miscellaneous'], $txn);
legacy(['POST'], 'Transaction/miscellaneous_report/{type}', [TransactionController::class, 'miscellaneousReport'], $txn);
legacy(['POST'], 'Transaction/add_miscellaneous', [TransactionController::class, 'addMiscellaneous'], $txn);
legacy(['GET'], 'Transaction/bulkoperations', [TransactionController::class, 'bulkoperations'], $txn);
legacy(['POST'], 'Transaction/add_bulk_miscellaneous', [TransactionController::class, 'addBulkMiscellaneous'], $txn);
legacy(['GET'], 'Transaction/expenses', [TransactionController::class, 'expenses'], $txn);
legacy(['POST'], 'Transaction/expenses_add', [TransactionController::class, 'expensesAdd'], $txn);
legacy(['GET', 'POST'], 'Transaction/expenseheader_management/{state?}/{p1?}/{p2?}', [TransactionController::class, 'expenseheaderManagement'], $txn, ['state' => implode('|', Crud::STATES)]);

// GSoft monthly bills: also open to blocked LCOs (this is where they pay), so no notblocked middleware
$bills = ['legacy.session:1,2'];
legacy(['GET'], 'Transaction/monthly_bills', [TransactionController::class, 'monthlyBills'], $bills);
legacy(['POST'], 'Transaction/monthly_bills_data', [TransactionController::class, 'monthlyBillsData'], $bills);
legacy(['POST'], 'Transaction/monthly_bills_view', [TransactionController::class, 'monthlyBillsView'], $bills);
legacy(['POST'], 'Transaction/payment_type_view', [TransactionController::class, 'paymentTypeView'], $bills);
legacy(['POST'], 'Transaction/initiate_payment', [TransactionController::class, 'initiatePayment'], $bills);

// Invoice history / approval (lists use the CRUD engine; approve is POST: it changes money)
$crudState = ['state' => implode('|', Crud::STATES)];
legacy(['GET', 'POST'], 'Transaction/import_history/{state?}/{p1?}/{p2?}', [TransactionController::class, 'importHistory'], $txn, $crudState);
legacy(['GET', 'POST'], 'Transaction/import_status_wise_history/{yearmonth}/{status}/{state?}/{p1?}/{p2?}', [TransactionController::class, 'importStatusWiseHistory'], $txn, $crudState);
legacy(['GET', 'POST'], 'Transaction/import_sms_status_wise_history/{yearmonth}/{status}/{state?}/{p1?}/{p2?}', [TransactionController::class, 'importSmsStatusWiseHistory'], $txn, $crudState);
legacy(['POST'], 'Transaction/approve/{primaryId}/{customerId}/{amount}/{days}', [TransactionController::class, 'approve'], $txn);
legacy(['GET'], 'Transaction/edit_bill/{primaryId}/{customerId}/{amount}/{days}', [TransactionController::class, 'editBill'], $txn);

legacy(['POST'], 'Subscriber/generate_network_bill', [SubscriberController::class, 'generateNetworkBill'], $sub);
legacy(['POST'], 'Subscriber/generate_selected_inactive_bills', [SubscriberController::class, 'generateSelectedInactiveBills'], $sub);
legacy(['POST'], 'Subscriber/delete_network_bill', [SubscriberController::class, 'deleteNetworkBill'], $sub);

// GSoft monthly bills (administrator only; the old complete_payment had no check at all, any logged-in user could mark any bill paid or free)
legacy(['GET'], 'AdminSettings/monthly_bills', [AdminSettingsController::class, 'monthlyBills'], $admin);
legacy(['POST'], 'AdminSettings/generate_monthly_bills', [AdminSettingsController::class, 'generateMonthlyBills'], $admin);
legacy(['POST'], 'AdminSettings/monthly_bills_data', [AdminSettingsController::class, 'monthlyBillsData'], $admin);
legacy(['POST'], 'AdminSettings/monthly_bills_view', [AdminSettingsController::class, 'monthlyBillsView'], $admin);
legacy(['POST'], 'AdminSettings/payment_type_view', [AdminSettingsController::class, 'monthlyPaymentTypeView'], $admin);
legacy(['POST'], 'AdminSettings/complete_payment', [AdminSettingsController::class, 'completePayment'], $admin);
legacy(['POST'], 'AdminSettings/gsoft_network_list', [AdminSettingsController::class, 'gsoftNetworkList'], $admin);

// Broadband (menu 10 is checked in the controller)
$bb = ['legacy.session:1,2', 'legacy.network.notblocked'];
foreach (['isptype_management' => 'ispTypeManagement', 'broadbandplans_management' => 'broadbandPlansManagement', 'comboplans_management' => 'comboPlansManagement', 'broadband_subscriber_management' => 'broadbandSubscriberManagement'] as $uri => $method) {
    legacy(['GET', 'POST'], "Broadband/$uri/{state?}/{p1?}/{p2?}", [BroadbandController::class, $method], $bb, ['state' => implode('|', Crud::STATES)]);
}
legacy(['GET'], 'Broadband/broadband_payment', [BroadbandController::class, 'broadbandPayment'], $bb);
legacy(['POST'], 'Transaction/add_broadband_payment', [TransactionController::class, 'addBroadbandPayment'], $txn);
