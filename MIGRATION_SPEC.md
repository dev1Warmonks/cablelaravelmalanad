# MIGRATION_SPEC.md — Phase 1 Audit (initial pass, for review)

Source: `C:\laragon\www\cablebill_old\application` (CI 3.1.10, read only). Method: static read of controllers/models; no code was run.
Status legend: ☐ not yet verified · ✔ verified. **Nothing below is verified against a running old site yet.**
No secrets are copied here; locations only.

## 1. Auth & session (exact behaviour to port)
- `Login::index` → if session `usertype` set: 1/2 → `master`, else → `agent`; otherwise view `Login`.
- `Login::process_login` (POST only, else redirect `login`): `Account_model->login(username,password)`:
  1. `m_user` where `username`, `password` (plain), `status=1`, exactly 1 row → session `block_network="0"`, `userid`, `name`, `username`, `usertype`=`type`, `networkname`; if type≠1 also first active `m_network` with `created_by=userid` ordered by id → `network_id`, `network_name`, `lcoCode`.
  2. else `agent` same match → `userid`, `name`, `username`, `usertype`=`emptype`, `agentname`, first `agent_areas`→`m_area` → `areaname`, `areaid`.
  3. else error view `Login` with `error="Invalid Account"`.
  - After success: `Common_model->notifications()`; redirect 1→`master`; 2→`master` if `block_network($userid)=="false"` else `Transaction/monthly_bills`; other→`agent`.
- `Login::logout`: unsets listed keys, redirects `Login`.
- Guards: most controllers only check `has_userdata('usertype')` → `login/logout`. `Agent` requires `usertype==3`. **`AdminSettings` has its auth check commented out.** Roles are mostly not enforced per method (usertype 1/2 check commented out in `Transaction`).

## 2. Route inventory (CI auto-routing: `Controller/method/params`)
Public methods (excluding `_`-prefixed, which CI does not route, and callback helpers). ★ = needs special attention.

| Controller | Methods (URL = `Controller/method`) |
|---|---|
| Login | index, process_login (POST), logout |
| Master | index, change_network/{id}, pdf, products_category_management, products_management, taxrate_management, package_management, area_management, subarea_management, network_management, stb_management, stb_model_management, stb_make_management, stb_cas_management, stb_type_management, agent_management, miscellaneous_heads_management, header_view, menu_list, network_list (CRUD: Grocery_CRUD) |
| Settings | index, general, all_network_list, settings_save, subscriber_details, subscriber_payments, add_payment, menu_list, network_list |
| AdminSettings | ★backup, ★database_list, ★deleteBackup/{name}, ★generateBackup, menu_list, network_list, sms_apis, user_main_menus, users_management, general, all_network_list, settings_save, scripts/{lco}, update_last_paid, subscriber_details, subscriber_payments, add_payment, monthly_bills, monthly_bills_data (+`_old`,`_old2`), generate_monthly_bills, activate_generate_bill_all, gsoft_network_list, payment_types, payment_type_view, import_formats, complete_payment, monthly_bills_view |
| Subscriber | index/{type}, inactive_subscriber/{type}, all_subscriber/{type}, view_subscriber/{id}, edit_subscriber_view/{id}, update_subscriber, updateSMSstat, Mlist, update_mobile, sub_area, non_paid_this_month_report, subscriber_management(+`_old`), get_subscriber_details, generate_network_bill, generate_selected_inactive_bills, delete_network_bill, update_combo_plan, header_view, menu_list, network_list, all_network_list |
| Transaction ★core | index, balance_check, all_network_transaction, subscriber_search_result, import_history, monthly_bills, monthly_bills_data, monthly_bills_view, payment_type_view, initiate_payment, ★upigateway_check_order_status/{txn}/{date}/{bill}, ★create_upi_link/{amount}/{txn}, approve/{…}, approveOld, edit_bill/{…}, payment, ledger/{sub}, ledger_result, ★delete_voucher, subscriber_ledger, subscriber_details, subscriber_payments, print_bill, add_payment, add_broadband_payment, miscellaneous, miscellaneous_report/{type}, add_miscellaneous, expenses, expenses_add, expenseheader_management, bulkoperations, add_bulk_miscellaneous, import_status_wise_history/{ym}/{status}, import_sms_status_wise_history, month_wise_report_old, change_network, all_network_list, menu_list, network_list |
| Report | agent_report(+_data), date_wise_report(+_data), daily_report_data, network_daily_report_data, misc_report_data, month_wise_report, monthly_report_data, miscellaneous_report, payment_sms, agent_wise_collection_report(+_data), expense_report(+_data), discount_report(+_data), menu_list, network_list |
| Import | ★subscribers, importsubscribers, downloadopeningbalancexcel, importsuscriberfromezybill(+_f2), import_suscriberfromezybill(+_f2), update_database, updatesubscribers, importsubscriberopeningbalance, updatesubscribersopeningbalance, importproducts, import_products, settings_save, subscriber_payments, sendsmstopendingcustomers, menu_list, network_list |
| Complaint | index, view_complaint/{id}, complaint_add_view, add_complaint, edit_complaint/{id}, update_complaint, enquiry, complaint_type_management, enquiry_type_management, menu_list, network_list |
| Broadband | isptype_management, broadbandplans_management, comboplans_management, broadband_subscriber_management, broadband_payment, header_view, menu_list, network_list |
| Agent (usertype 3) | index, payment, change_area/{id}, date_wise_report(+_data), daily_report_data |
| ★AgentApi (JSON, no session) | login, verify, subscriber_list, transaction_type_list, area_list, print_header, add_payment, payment_print, subscriber_transaction_history, complaint_type_list, complaint_add, dalily_report (sic) |
| Result | index, result_old_management, save_result, result_new_management, daily_result, save_date |
| SmsSendCron | index/{mobile}, send_bulk_sms/{mobile} (no auth) |
| Cache | clear_opcache?key=… (hard-coded key) |
| PrivacyPolicy | details (public) |
| **Main** | game_type, prize, number, deals, slides… — looks like a **different app's leftovers** (not cable billing). **Proposal: do not migrate. Please confirm.** |

Each row still needs: HTTP verb, params, view, role, SQL — to be filled per module during that module's step 1 (☐).

## 3. Security findings in the old code (must not be reproduced)
| # | Severity | Finding | Location | Laravel fix |
|---|---|---|---|---|
| 1 | Critical | **Production DB credentials hard-coded** in code (backup generator) | `AdminSettings::generateBackup` | `.env`; **rotate the DB password now** (it is in the repo/old server) |
| 2 | Critical | **UPIGateway API key hard-coded** (create order & status check), plus hard-coded redirect URL/email/phone | `Transaction::create_upi_link`, `upigateway_check_order_status` | `.env`/config; rotate key |
| 3 | Critical | `AdminSettings` has **no auth check** (commented out) → DB backup/download/delete URLs reachable unauthenticated | `AdminSettings` constructor, `backup/database_list/deleteBackup/generateBackup` | auth + role:1 middleware; backups outside `public/`, signed download; consider dropping web backup in favour of server `mysqldump` cron |
| 4 | High | `deleteBackup/{name}` → `unlink('assets/uploads/backup/'.$name)` → **path traversal / arbitrary file delete** | `AdminSettings::deleteBackup` | `basename()` + whitelist against directory listing |
| 5 | High | **Plain-text passwords**; AgentApi token is static and returned on login | `Account_model`, `AgentApi::login` | see plan (hash at cut-over; token compare with `hash_equals`; optional token rotation needs app change → not now) |
| 6 | High | AgentApi login **logs raw request body (incl. password) and `$_POST/$_FILES`** to `application/logs/input.log` | `AgentApi::login` | do not port; log only username + result |
| 7 | High | SQL built by string concatenation in `where` strings (e.g. `'lco_code = "'.$_SESSION["lcoCode"].'" and current_month = "'.$_POST['current_month'].'"'`, `client_txn_id = "'.$client_txn_id.'"` from URL) → **SQL injection** | `Transaction::initiate_payment`, `upigateway_check_order_status` and others (≈110 raw/concat sites) | Query Builder bindings; each site tracked in the per-module checklist |
| 8 | High | UPI status check takes `client_txn_id`, `txn_date`, `bill_id` **from the URL**, marks any bill paid if gateway says that txn succeeded (no check that txn belongs to that bill/amount, no duplicate guard) → IDOR / replay | `Transaction::upigateway_check_order_status` | look up `upigateway_history` row by txn id, assert bill/network/amount match, run in transaction with lock, idempotent |
| 9 | High | `initiate_payment`, `delete_voucher`, `approve`, `edit_bill` etc. change data on **GET/any verb with no CSRF** and no ownership check | Transaction | POST + CSRF + policy checks |
| 10 | Medium | Hard-coded secret key `'12345'` for `Cache/clear_opcache` | `Cache` | artisan/`.env` secret or drop (opcache reset belongs to deploy script) |
| 11 | Medium | Cron endpoint public, no secret | `SmsSendCron` | keep URL, add secret/IP allowlist + artisan scheduler |
| 12 | Medium | Role not enforced on most controllers (any logged-in agent could call admin URLs) | all | route-level role middleware |
| 13 | Medium | Import uploads: `move_uploaded_file` ×6, no whitelist seen | `Import` | validate mimes/size, random name, private storage |
| 14 | Medium | `mysqli` credentials/`encryption_key` empty, CSRF off, 30-day session, no login throttling | config | see plan |
| 15 | Low | Backup/test scripts and logs in web root (`debug_db.php`, `test_db_*.php`, `index copy.php`, `php copy.ini`, `application/logs`) | root | not migrated |

**Immediate recommendation (outside the migration):** rotate the DB password and UPIGateway key because they are in source and the server was hacked before. I did not touch the old project.

## 4. Behaviours to preserve exactly (spotted so far)
- UPI success sets `gsoft_billing`: `payment_type=4`, `bill_status=2`, `tran_id=client_txn_id`; `upigateway_history` gets `success_response`/`failed_response`, `modified_on`, `modified_by`. Gateway date format `d-m-Y`.
- `initiate_payment` sets `gsoft_billing.bill_status=1`, `payment_type` for `lco_code` + `current_month`.
- Timezone `Asia/Kolkata`. Session cookie `ci_session`, files driver.
- AgentApi JSON shapes: login → `{token,name,status:"success"}` or `{status:"failed",message:"Invalid Username or Password"|"Data missing"|"Invalid Token"}`; **note: if the owning `m_user` row is missing, old code returns an empty array `[]`** (quirk, to keep byte-compat). Content-Type `application/json`, input is raw JSON body.

## 5. Still to audit (Phase 1 remaining, ☐)
- Every model method + SQL (`Common_model` 17 raw, `Dashboardmodel` 16, `Model_subscriber` 8) and `vw_monthly_billing` definition (need DB copy schema).
- Billing: `generate_monthly_bills`, `getSubscriptionCharge`, `updateDueAmount`, `add_payment`, `approve`, discounts/tax, receipt numbering (`Pdf`, `Pdf_data_modal2`).
- SMS: `Common_model->sms_bulk_cron_payment`, `sms_apis`, templates.
- `block_network` logic, `notifications()`.
- AgentApi remaining 10 endpoints: request/response samples (**need recorded responses from the old app**).
- Import file formats; Report columns/exports; Grocery_CRUD tables, fields, callbacks (Master, Settings, AdminSettings, Complaint, Broadband).
- Views inventory (`home`, `agent`, `transactions`, `excel-upload`, `Login`, `header`).
- Upload folders, `.php` scan of `assets/`, `application/views/uploads`.

## 6. Decisions I'm proceeding with unless you object
1. Explicit generated routes (case-insensitive aliases), not a catch-all.
2. Generic config-driven CRUD controller rendering Grocery_CRUD-identical HTML.
3. Plain-text compare kept (behind one verifier class, timing-safe) until cut-over; rehash then.
4. FPDF/FPDI kept for PDFs (layout parity).
5. `Main` controller not migrated.

## 7. Needed from you to continue
- Confirm / change decisions in §6 (esp. `Main`).
- A **local copy of the database** (schema + data) and its name, so I can fill §5 (view definition, columns) accurately.
- Recorded sample requests/responses from the AgentApi (or permission to build them from a local old-site run).
- The PHP path to use (Laragon's `php.exe`), since `php` isn't on PATH.

---

## 8. Update — DB schema + payment core (audited against local copy `cablebill02nov`)
Full table/column/view snapshot: [docs/db_schema.md](docs/db_schema.md) (58 objects incl. views such as `balance_cross`; no data, structure only).
Volumes (rows): `subscriberpayments` 989,763 · `m_ezybill_invoices` 615,096 · `m_customer` 16,234 · `subscriberbalance` 25,235 · `agent` 115 · `agent_areas` 577 · `gsoft_billing` 1,101 · `m_network` 36 · `m_user` 26 · `upigateway_history` 120 · `sms_apis` 5 · `settings` 4.
→ Large tables: all lists must use server-side pagination/indexed queries; never `->get()` whole tables.
Schema notes: `timestamp` columns are **varchar** (millisecond epoch strings); `agent.password varchar(200)` and `agent.token varchar(45)`; `subscriberpayments` is the running-balance ledger. No `created_at/updated_at` → `$timestamps=false`.
Sessions/cache tables do not exist → `file` drivers (already set in `.env`).

### 8.1 `Transaction::add_payment` (POST; used for cash payment and miscellaneous heads) — behaviour to port exactly
Inputs: `subscriberid, paymentdate, paidamount, discount, comment, trantype, othermobile`.
1. `trantype != 1` → name from `m_dropdown.id=trantype`; if found `is_misc=1`, description = head name; `trantype == 1` → description `"Cable subscription"`. If `comment` non-empty: description `"<desc> (<comment>)"`.
2. `agentid` = session `userid` if `usertype==3`, else 0. `timestamp` = ms epoch string (`milliseconds()`), `transactiontype='1'`, empty discount → `'0'`.
3. `dueamount = subscriberbalance.amountdue` (0 if no row); `duebalance = due − paid`; `dbafterdiscount = due − paid − discount`.
4. INSERT `subscriberpayments` (transactiontype, subscriberid, agentid, description, dueamount, paidamount, discount, subscriptioncharge=0, duebalance, dbafterdiscount, timestamp, is_misc) → `paymentid`.
5. Upsert `subscriberbalance(subscriberid, amountdue=dbafterdiscount, timestamp)`.
6. SMS: owner = session user (or `agent.created_by` for agents); if `m_user.sms==1` and `m_customer.sms==1` and 10-digit mobile (`othermobile` overrides; `'91` prefix stripped; `is_other_mobile` flag): message
   `ctopup Dear {name}, We received your pmt of Rs {paid} for {description}. Your bal due amount is Rs {dbafterdiscount 2dp}. Thank you {m_network.sms_signature}` → stored on the payment row (`sms`, `sms_status=1`, `mobile`, `is_other_mobile`) and `Common_model->sms_payment()` sends it.
7. JSON response: `{"message":"Your transaction id is <id> Please keep this for future references,","status":"success"}`; nothing is returned (empty body) if required POST fields are missing. (`AgentApi::add_payment` is the mobile twin, ~250 lines — ☐ to diff.)

**Defects to fix without changing results**
- Read-modify-write on the balance with **no DB transaction and no lock** → two simultaneous payments (agent + LCO, double click, retry) read the same `amountdue` and one update is lost → ledger drift. Laravel: `DB::transaction` + `lockForUpdate()` on the `subscriberbalance` row; compute inside the lock.
- No check that `subscriberid` belongs to the caller's network/agent areas (IDOR: any logged-in user can post to any customer).
- No validation: negative/non-numeric `paidamount`/`discount`, discount > due; numeric strings used in arithmetic. Laravel: `numeric|min:0` etc., money via `bcmath` (2-dp; the old code relied on PHP floats — keep 2-dp rounding identical, verify with ledger recompute).
- `paymentdate` is read but never used (the ledger timestamp is "now") → keep this behaviour unless you tell me otherwise.
- Double-submit: add an idempotency token on the form (hidden field), no change to the visible UI.
- SMS sends synchronously inside the request; keep behaviour but wrap so an SMS failure never rolls back/blocks a payment.

### 8.2 Ledger invariants (used for the Phase 8 parity tests)
For each customer, last `subscriberpayments.dbafterdiscount` (view `balance_cross`) must equal `subscriberbalance.amountdue`. I will run this check on the DB copy before and after porting each write path; any customer where it already differs in the old data is reported, not "fixed".

### 8.3 Old-code smells confirmed in the helper methods
`getSubscriptionCharge` reads legacy tables `subscriber`/`package` (the current model is `m_customer`) and `Transaction`, `Settings`, `Import`, `AdminSettings` each carry their own copies of `getDueAmount/updateDueAmount/milliseconds`. In Laravel these become **one** `BalanceService` (+ `PaymentService`) used by every controller and the API — one place to lock and test.
