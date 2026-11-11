# CodeIgniter → Laravel Migration (Exact Copy) — Cable Bill

## Goal
Rebuild the existing CodeIgniter **cable TV / broadband billing system** (admin/LCO panel + agent panel + agent mobile API) in Laravel as an **exact copy**:
same pages, same design, same URLs, same billing/payment flow, same SMS, same agent API responses, same database.
Users (admins, LCO owners, agents) should not notice any difference, except that the app runs on a modern PHP version.

## Paths
- **Old CodeIgniter project (READ ONLY):** `C:\laragon\www\cablebill_old` (CI 3.1.10, branch `cablebillmalanad`)
- **New Laravel project (work here):** `C:\laragon\www\cablelaravel` (create it; adjust if you prefer another name)

## Local project paths
| Project | Path |
|---|---|
| Old CodeIgniter (READ ONLY) | `C:\laragon\www\cablebill_old` |
| New Laravel (work here) | `C:\laragon\www\cablelaravel` |

Use these paths only (no web links). Compare old vs new by opening the same page in each local project.

## Project facts (already audited — do not re-discover)
- **Framework:** CodeIgniter 3.1.10, default controller `Login`, no custom routes, no `index.php` in URL (`.htaccess` rewrite). URLs are the CI auto-route form `Controller/method/param` (e.g. `Transaction/monthly_bills`).
- **Roles** (session `usertype`): `1` = super admin, `2` = LCO / network owner (table `m_user`), other values = agent (table `agent`, `emptype`). Login redirects: 1 → `master`; 2 → `master` or `Transaction/monthly_bills` (depends on `Common_model->block_network`); agent → `agent`.
- **Login:** `Account_model->login` matches `username` + `password` in `m_user` (status=1), then falls back to `agent`. **Passwords are stored in PLAIN TEXT.** Session keys: `userid, name, username, usertype, networkname, network_id, network_name, lcoCode, areaname, areaid, block_network, blocknetworkMSG, DefaultNotification, agentname`.
- **Controllers (17):** Login, Main, Master, AdminSettings, Settings, Subscriber, Transaction (2.6k lines, billing core), Report, Import (Excel import), Complaint, Broadband, Agent, **AgentApi** (mobile API), Result, SmsSendCron (cron), Cache, PrivacyPolicy.
- **Models:** Account_model, Common_model (notifications, SMS, network block), Dashboardmodel, Model_subscriber, Model_settings, Pdf, Pdf_data_modal2, Grocery_crud_model.
- **Main tables:** `m_customer, subscriberpayments, subscriberbalance, m_network, m_ezybill_invoices, m_user, agent, agent_areas, m_area, m_sub_area, settings, m_user_roles, m_main_menus, gsoft_billing, gsoft_payment_types, m_dropdown, m_combo_plan, m_products, m_package, dailyresult, invoice_import_history, ezybill_customers, upigateway_history, sms_apis, m_complaints, m_broadband_customer, m_broadband_plan, expenses, expenseheader, vw_monthly_billing (view), configuration` (+ legacy: subscriber, stype, package, deals, numbers, temp_cust, daily_result).
- **Payment gateway:** UPIGateway (`upigateway_history`; order status polled by `Transaction::upigateway_check_order_status`).
- **SMS:** providers stored in `sms_apis`; bulk/payment SMS via `Common_model->sms_bulk_cron_payment`, triggered by cron controller `SmsSendCron`.
- **Libraries to replace:** Grocery_CRUD (admin CRUD screens), Image_CRUD, Image_MOO, `callApi.php` (curl wrapper → Laravel `Http`), `reader.php` (Excel reader → `maatwebsite/excel` or `phpspreadsheet`), FPDF/FPDI in `assets/` (PDF bills → keep FPDF/FPDI via composer or `dompdf`), Guzzle 7 already in composer.
- **Static folders to copy:** `assets/`, `css/`, `cssjs/`, `js/`, `common/`, `assets/uploads`, `application/views/uploads`.
- **Messy / ignore (don't migrate):** `Subscriber - Copy.php`, `Subscriber copy*.php`, `NewSubscriber.php` + `views/newsubscriber/` (deleted in git), `*01102026.php` backup views, `debug_db.php`, `test_db_*.php`, `index copy.php`, `php copy.ini`, `application/logs/*`. Ask before migrating anything that looks like a backup.

---

## GOLDEN RULES (never break these)
1. **The live site is in use (LCOs collect payments daily). Never block or disturb it.**
   - Never edit, move, or delete anything in the old CodeIgniter project. Read only.
   - Never connect to the **production database** for development. Use a **copy** of the database locally.
   - Never run `migrate:fresh`, `migrate:reset`, `db:wipe`, or any command that drops or alters existing tables.
2. **Same database structure.** Laravel uses the existing tables as they are. No renaming tables or columns. Laravel's own tables (`sessions`, `cache`, `jobs`, `migrations`) may be added only after approval — prefer `SESSION_DRIVER=file` and `CACHE_STORE=file` to avoid touching the DB.
3. **Same URLs.** Every URL must keep working, **including CI's `Controller/method/arg` pattern and case-insensitivity** (`login`, `Login`, `master`). The agent app (**AgentApi**) URLs and JSON responses must stay byte-compatible — the mobile app must not need an update.
4. **Same look.** HTML, CSS classes, JS, and images stay identical (same admin template).
5. **Billing, payments (UPIGateway), SMS, and AgentApi are the highest priority.** Totals, balances, and ledger entries must match the old site to the paisa.
6. **Ask before assuming.** If old code is unclear, ask instead of guessing.
7. Work **one module at a time** and say when each module is ready to test.
8. **Security:** this server was hacked before. Never copy `.php`/`.phtml`/unknown executables from upload folders. Never commit DB credentials, UPIGateway keys, or SMS API keys — they go in `.env`.

---

## Phase 0 — Version check (DONE)
CodeIgniter **3.1.10**. Target: Laravel latest stable, PHP 8.3+. (Old code runs on a legacy PHP — expect deprecations, dynamic properties, `$this->db` query-builder semantics to port.)

## Phase 1 — Audit (no coding yet)
Create `MIGRATION_SPEC.md` in the new project, covering:
- Every URL = every public method in every controller (the 17 above), with HTTP method, params, role/permission required, and the view it renders. Include the `m_user_roles` / `m_main_menus` menu-permission logic.
- Every model method and the SQL it runs (incl. raw queries and the `vw_monthly_billing` view).
- All views and partials (`header`, `leftsidebar`, `footer`, per-role variants under `views/home` and `views/agent`).
- **Billing flow:** monthly bill generation, customer/plan/combo/package pricing, discounts, GST/tax, balance carry-forward (`subscriberbalance`), payment entry (`subscriberpayments`, `view_makepayment`, `edit_bill`), receipts/PDF (`Pdf`, `Pdf_data_modal2`), ledger, expenses, daily result.
- **UPIGateway:** create-order, redirect/callback/status-check URLs, `upigateway_history` fields, how a successful payment updates bill/balance, duplicate-prevention.
- **SMS:** providers in `sms_apis`, templates, when SMS is sent (payment, bulk pending, notifications), cron schedule for `SmsSendCron`.
- **Network block logic:** `Common_model->block_network` and the `blocknetworkMSG` flow (LCO subscription/expiry).
- **AgentApi:** every endpoint, request params, auth method, JSON response shape, error format, multi-network search.
- **Import:** Excel/CSV import (`Import`, `invoice_import_history`, `ezybill_customers`, `m_ezybill_invoices`) formats and validations.
- **Reports:** every report in `Report` + `views/home/report` + `views/agent/report` (filters, columns, Excel/PDF export).
- **Grocery_CRUD screens:** list each CRUD table, its fields, relations, callbacks, and validations (AdminSettings, Master, Settings).
- Config: base URL, session (`files`, 30-day expiry, `ci_session` cookie), encryption key (empty), timezone, DB, SMTP if any.
- File upload folders and sizes.

Stop after Phase 1 and wait for review.

## Phase 2 — Laravel setup
- Latest stable Laravel, PHP 8.3+, MySQL (same charset/collation as the copy).
- `.env` → **local copy** of the database. Session/cache = file.
- Copy public assets with identical paths into `public/` (`assets`, `css`, `cssjs`, `js`, `common`, uploads). Scan for and list any `.php`/suspicious files instead of copying them.
- Install packages: `maatwebsite/excel` (import/export), FPDF/FPDI or `dompdf`, `guzzlehttp/guzzle` (via `Http`), a CRUD replacement approach (see Phase 5).
- Set timezone and locale to match the old app (check `config.php`/`index.php`).

## Phase 3 — Models
- One Eloquent model per table above (skip legacy tables until confirmed unused). Set `$table`, `$primaryKey`, `$timestamps = false` unless real `created_at/updated_at` columns exist.
- `vw_monthly_billing` is a **view** → read-only model, no migration.
- Relationships from existing keys: customer→network, customer→area/sub_area, agent→agent_areas→area, payments/balance→customer, user→networks (`m_network.created_by`).
- Port `Common_model`, `Model_subscriber`, `Dashboardmodel`, `Model_settings` into service/repository classes; keep the **same SQL results** (verify with side-by-side queries).
- Migration files, if needed, are **"schema-documentation only" / `create-if-not-exists` guarded** and never run against production.

## Phase 4 — Authentication
- Passwords are currently **plain text** in `m_user.password` and `agent.password`. Port login exactly: check `m_user` (status=1) first, then `agent` (status=1); same session keys; same redirects by role.
- Custom guard/session login (do not use Laravel's default hashed `Auth::attempt` as-is). Plan (needs my approval): on successful login, add a nullable `password_hash` column **or** rehash in place to bcrypt — **both change the schema/old site compatibility, so keep plain-text compare until cut-over is decided**, because the old site stays live and shares the same DB.
- Middleware replicating CI session checks per controller (usertype 1/2/agent) and `m_user_roles` menu permissions.
- Logout clears the same session keys.

## Phase 5 — Pages (one module at a time)
Suggested order:
1. Layout (header/leftsidebar/footer, role-based menu) + Login + Dashboard
2. Master / Settings / AdminSettings (CRUD screens)
3. Subscriber (list, add, edit, inactive, broadband)
4. **Transaction** (monthly bills, make payment, edit bill, ledger, history, miscellaneous, expenses, bulk operations, SMS to pending)
5. Reports (+ Excel/PDF exports)
6. Import (Excel)
7. Complaint
8. Agent panel
9. **AgentApi**
10. PrivacyPolicy, Cache, Result, SmsSendCron

Rules:
- Blade views with the **same HTML**.
- Routes in `routes/web.php` / `routes/api.php` replicate the CI URLs exactly, using a catch-all `Route::any('{controller}/{method?}/{params?}')` dispatcher (case-insensitive) **or** explicit routes per method — get my approval on the approach first.
- Grocery_CRUD replacement: reproduce the same list/add/edit/delete screens with the same HTML (either port Grocery_CRUD's rendering or build a small generic CRUD controller). Ask before choosing.
- Port validation rules and messages exactly.
- CSRF: CI had none; enable Laravel CSRF but exempt AgentApi and gateway callbacks.

## Phase 6 — Billing & Payment (highest care)
- Same bill generation, totals, discounts, tax, balance carry-forward, and ledger entries; wrap multi-table writes in DB transactions.
- UPIGateway: same keys (from `.env`), same callback/status-check paths, idempotent processing (no double credit), same `upigateway_history` rows.
- Receipts/PDF bills identical in layout and numbering; invoice/receipt number format and sequence continue from current values.
- Same SMS triggers and templates; SMS provider read from `sms_apis`.
- LCO network-block logic works exactly as before.

## Phase 7 — SMS, Cron & Agent API
- `SmsSendCron` → Laravel Artisan command + scheduler **but keep the old URL working** until the server cron is switched.
- AgentApi: identical endpoints, params, auth, and JSON; test with the real mobile app against the local copy.
- Excel import produces the same rows in the same tables.

## Phase 8 — Testing
- Feature tests: login per role, monthly bill generation, make payment, balance after payment, UPIGateway callback (sandbox/mock), duplicate callback, SMS dispatch (fake), report totals vs old site, Excel import, AgentApi responses vs recorded old responses.
- Side-by-side check of every page, old vs new (design and numbers), for each role (admin, LCO, agent).
- Compare report totals and customer balances between old and new on the same DB copy.
- Keep a checklist in `MIGRATION_SPEC.md`; tick items only when verified.

## Phase 9 — Go-live plan (prepare it, don't run it)
- Full backup of old site files and database before switching.
- Cut over at low-usage time (e.g. night, outside the monthly billing/collection peak), downtime of minutes.
- Laravel connects to the live DB (no data migration; same tables). Old and new must **not** both write at the same time.
- Update server cron (SMS cron), UPIGateway callback URL (only if it changes), and the agent mobile app base URL (only if it changes).
- Keep the old site ready for instant rollback.
- Monitor payments, balances, SMS delivery, and agent API errors for the first 24 hours (Laravel logs).

---

## Working style
- Small steps, clear summaries.
- After each module: what was done, what to test, any differences from the old site.
- Never mark something done without testing it.
