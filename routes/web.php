<?php

use App\Crud\Crud;
use App\Http\Controllers\AdminSettingsController;
use App\Http\Controllers\BroadbandController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\MasterController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SubscriberController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

/*
 | Legacy URLs: CodeIgniter routing is case-insensitive (login, Login, LOGIN) and has the
 | form Controller/method/params. Explicit routes (no catch-all) so each has its own middleware.
 | legacy() registers the lower-case and Capitalised variants of a URL (the forms the old site used).
 */
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

// Temporary landing targets until the other modules are ported.
legacy(['GET'], 'agent', fn () => 'Agent dashboard: not yet ported', ['legacy.session:3']);

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
