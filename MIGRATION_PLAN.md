# Cable Bill: CodeIgniter 3 → Laravel Migration Plan (with OWASP)

Rules: `C:\laragon\www\cablebill_old\rulesfollow.md` (golden rules apply). Old project is READ ONLY.

## Principle
- **Design and business logic = identical** to the old app: same HTML/CSS/JS, URLs, billing math, payment flow, SMS, AgentApi JSON, same DB tables/columns.
- **Everything else = the Laravel way**: Eloquent/Query Builder (bound params), FormRequest validation, middleware, Blade escaping, CSRF, Http client, queues/scheduler, `.env` config, Storage, policies/gates.
- Where old logic is *insecure*, keep the observable behaviour and fix the mechanism underneath (e.g. same screen, parameterized query).

## Audit facts found (from old code)
- ~12.5k lines in 17 controllers; Transaction (2.6k), AdminSettings (1.7k), Import (1.6k), Report (1.5k), AgentApi (1.2k), Subscriber (1.2k).
- ~110 `$this->db->query()` raw calls (Common_model 17, Transaction 16, Dashboardmodel 16, Report 14 …) → each must be reviewed for injection.
- ~290 direct `$_POST/$_GET/$_FILES` uses (Transaction 74, Report 44, Subscriber 43, Import 39, AdminSettings 46).
- 6 `move_uploaded_file` calls in Import (no visible type whitelist).
- CSRF disabled; passwords plain text; `encryption_key` empty; session lifetime 30 days.
- AgentApi auth = static `token` in request body, looked up in `agent` table.
- Laravel skeleton already exists (Laravel 13, PHP ^8.3); no app code yet. `php` is not on the Git Bash PATH (use Laragon's PHP).

## OWASP Top 10 (2021) mapping

| OWASP | Risk in old app | Laravel handling | Compat note |
|---|---|---|---|
| A01 Broken Access Control | Per-controller session checks; role = usertype 1/2/agent; LCO data scoped by `network_id`/`created_by` | Role middleware (`admin`, `lco`, `agent`) + `m_user_roles` menu gate; **global scopes / policy checks so every query is filtered by the user's network_id**; check ownership on every `{id}` (IDOR); deny by default | Same menus/permissions visible |
| A02 Cryptographic Failures | **Plain-text passwords**, empty encryption key, secrets in code | `.env` for DB/UPIGateway/SMS keys; `APP_KEY` set; HTTPS-only cookies; password hashing plan below | Needs your approval (schema/old-site shared) |
| A03 Injection | Raw SQL, direct superglobals, Grocery_CRUD | Query Builder/Eloquent with bindings only; `DB::select('… ?', [..])` for raw; whitelist for dynamic column/order; Blade `{{ }}` escaping (use `{!! !!}` only for audited HTML); validate everything via FormRequest | Same results, verified side by side |
| A04 Insecure Design | Payment callbacks, duplicate credit risk | DB transactions + row locks (`lockForUpdate`), idempotency on `upigateway_history` (unique order id check), amount re-verified server-side with gateway status API | Same ledger numbers |
| A05 Security Misconfiguration | Debug scripts (`debug_db.php`, `test_db_*.php`), logs in app dir, `.php` in uploads | Don't migrate them; `APP_DEBUG=false` in prod; only `public/` web-exposed; security headers middleware (CSP, X-Frame-Options, HSTS, nosniff, Referrer-Policy); block PHP execution in `public/uploads` | |
| A06 Vulnerable Components | PHPExcel (abandoned), CI 3.1.10, old FPDF | `maatwebsite/excel` (PhpSpreadsheet), `dompdf` or maintained FPDF/FPDI via composer; `composer audit` in CI | Same Excel/PDF output layout |
| A07 Auth Failures | No lockout, 30-day sessions, no session regeneration | Custom guard (same lookup: `m_user` status=1 then `agent`), `session()->regenerate()` on login, `RateLimiter` on login (e.g. 5/min per IP+username), secure/httponly/samesite cookies, shorter idle timeout (configurable), logout invalidates + regenerates token | Same session keys & redirects |
| A08 Software/Data Integrity | Unsigned callbacks, `unserialize` none found | Verify gateway callback by re-querying UPIGateway; no unserialize of user data; composer.lock committed | |
| A09 Logging/Monitoring | CI logs only | Laravel `Log` channels: auth failures, payment events, permission denials, import runs (never log passwords/keys); daily rotation | |
| A10 SSRF | `callApi.php` curl wrapper | `Http::` client with fixed base URLs from `.env`, timeouts, no user-supplied URLs | |

Extra controls:
- **CSRF**: enabled on all web routes (add `@csrf` / `X-CSRF-TOKEN` to forms and the AJAX setup, same HTML otherwise). Exempt only: AgentApi, UPIGateway callback, SmsSendCron URL (protected by secret/IP instead).
- **Uploads (Import + images)**: validate `mimes:xlsx,xls,csv`, size cap, store with random name via `Storage` outside `public/`, never keep client filename/extension, scan for `.php` in copied asset folders (list instead of copy).
- **AgentApi**: same URLs/JSON; token compared with `hash_equals`, rate-limited, per-request validation, uniform error JSON as before; no stack traces.
- **Mass assignment**: Eloquent `$fillable` explicitly defined.
- **Money**: `decimal` casts / `bcmath` to keep paisa-exact totals.
- **Output**: JSON via `response()->json`, `Content-Type` correct; file downloads via `Storage::download`.

## Password strategy (decision needed)
Rules say keep plain-text compare while the old site shares the DB. Recommended path:
1. **Dev/testing**: plain-text compare exactly as old (behind one `PasswordVerifier` class, so it's a single switch).
2. **At cut-over** (old site retired): one-time script rehashes `m_user.password` / `agent.password` to bcrypt in place (columns need to fit 60+ chars — check length first), verifier switches to `Hash::check`. Rollback = restore DB backup.
3. Interim safe option: compare with `hash_equals` (timing-safe) and never log or echo passwords.
→ **Approve or change.**

## URL routing approach (decision needed)
Recommended: **explicit routes generated per controller method** (`Route::match(['get','post'], 'Transaction/monthly_bills/{p1?}/{p2?}', …)`), with lower-case aliases, instead of a catch-all dispatcher. Why: catch-all would expose *every* public method (OWASP A01); explicit routes give middleware per route, route caching and testability. A small script will generate the list from the audit.
Alternative: case-insensitive catch-all with a method whitelist.

## Grocery_CRUD replacement (decision needed)
Recommended: a small generic `CrudController` + config array per table (fields, relations, validation, callbacks) that renders the **same Grocery_CRUD HTML/JS/CSS**, server-side via Query Builder (DataTables-style endpoint with bound search/sort whitelist).

## Phases / milestones

| # | Phase | Output | Gate |
|---|---|---|---|
| 0 | Prep | Local **copy** of DB (never prod), `.env`, `SESSION_DRIVER=file`, `CACHE_STORE=file`, timezone from old `config.php`, git init, `.gitignore` for `.env` | App boots against copy |
| 1 | Audit | `MIGRATION_SPEC.md`: every route/method, role, SQL, views, billing, UPIGateway, SMS, AgentApi contract (recorded real responses), Import formats, reports, CRUD screens, **security findings per method** | **Your approval — stop here** |
| 2 | Foundation | Assets copied to `public/` (scan `.php`/exe), master Blade layout (header/leftsidebar/footer), security headers middleware, login guard, role + menu middleware, rate limiter, logging channels | Login for admin/LCO/agent identical |
| 3 | Models | Eloquent per live table (`$table`, `$timestamps=false`, `$fillable`, relations), `vw_monthly_billing` read-only, services replacing `Common_model`, `Model_subscriber`, `Dashboardmodel`, `Model_settings` | Query results = old |
| 4 | Master/Settings/AdminSettings | Generic CRUD + validations | Each screen add/edit/delete parity |
| 5 | Subscribers + Broadband | Lists, add/edit, inactive | Same filters/data |
| 6 | **Transaction (billing/payments)** | Bills, make payment, edit bill, ledger, expenses, bulk ops, PDF, UPIGateway (idempotent, transactional) | Totals/balances exact vs old on same DB |
| 7 | Reports | All reports + Excel/PDF | Totals equal |
| 8 | Import | Excel import, safe upload | Same rows inserted |
| 9 | Complaint, Agent panel, Result, Dashboard | | Parity per role |
| 10 | **AgentApi** | Same endpoints/JSON | Recorded old responses == new |
| 11 | SMS & Cron | Artisan command + scheduler; old `SmsSendCron` URL kept (secret-protected) | Dry-run identical |
| 12 | QA & security | Feature tests (login per role, IDOR/cross-network access denied, CSRF, SQLi payloads, upload abuse, duplicate callback, rate limit), `composer audit`, Pint, side-by-side checklist | All ticked & verified |
| 13 | Go-live runbook | Backup, low-usage cut-over, rollback, 24h monitoring (written, not executed) | Your approval |

Per module: read old controller+view+model → route (same URL) → FormRequest + service + Blade (same HTML) → compare vs old → report done / how to test / differences.

## Security checklist per ported method
- [ ] Route has auth + role middleware
- [ ] Record access scoped to the user's network (no IDOR)
- [ ] Inputs validated via FormRequest (types, ranges, `exists:`)
- [ ] No string-concatenated SQL; bound params only
- [ ] Output escaped; CSRF token on state-changing forms
- [ ] Multi-table writes in `DB::transaction`
- [ ] Sensitive actions logged
- [ ] Response identical to old (HTML/JSON)

## Open questions
1. Password strategy above (approve rehash at cut-over?).
2. Explicit routes vs catch-all.
3. CRUD replacement approach.
4. PDF: keep FPDF/FPDI (identical layout) or dompdf? (FPDF recommended for exact layout.)
5. Path: rules say `cablelaravel`; the older plan says `cablebill_laravel`; I'm using `cablelaravel`.
6. Which PHP binary/MySQL copy name should I use? (`php` isn't on Git Bash PATH.)

---

## Progress log
| Date | Module | State |
|---|---|---|
| 2026-11-11 | Env fix | Apache vhost -> `public/`; sessions/cache/queue = file/sync; PHP 8.3 (Laragon) — verified |
| 2026-11-11 | Assets | Static files copied to `public/`, scripts excluded, uploads/backups excluded — see docs/asset_scan.md — verified (0 script files) |
| 2026-11-11 | Login + auth | `LoginController`, `LegacyAuthService`, `PasswordVerifier`, throttle (5/min), session regenerate, CSRF, security headers — verified by script: admin/LCO/agent redirects match old, bad login message, 419 without CSRF |
| 2026-11-11 | Layout + Dashboard (Master/index) | Blade layout/header/sidebar/control sidebar, `DashboardService`, `MenuService`, `change_network` ownership fix — numbers verified vs old SQL on copy DB (LCO MLNDKBTAJ1: today/month/prev/outstanding/customers/total collection all equal) |

**Not yet verified:** pixel/HTML side-by-side in a browser with the old site (needs your eyes: open both `cablebill_old.test/master` and `cablelaravel.test/master`).
**Known gaps:** all-caps URLs (`/LOGIN`) 404 (CI was fully case-insensitive) — will add a middleware-level normaliser; CSP not yet enforced; `Remember Me` checkbox is cosmetic (as in old).
| 2026-11-11 | Generic CRUD engine (`app/Crud/Crud.php`) + Master `area_management`, `subarea_management` | Grocery_CRUD 1.6.1 / bootstrap-v4 replacement: same HTML, same theme JS/CSS, same state URLs & JSON. Verified by script on copy DB: list, ajax_list (paging/search/order), add/edit/read/clone forms, validation message, insert/update/delete, forged `created_by` ignored, other user's rows 404 on edit/update/delete, SQL-injection attempts in search/order harmless, insert without CSRF = 419, unset_delete = 403. Subarea count (54) matches old join semantics. **Browser check pending.** |

**CRUD engine not yet supported (needed by later screens):** `set_relation_n_n` (packages), upload fields, date/datetime inputs, text editor, `add_action` buttons, `set_rules`, `callback_after_update`, `unique_fields`, `callback_field` on read. Each unsupported type throws a clear error instead of silently misrendering.
**Env note:** `DB_STRICT_MODE=false` (config/database.php) because legacy tables rely on non-strict inserts (e.g. `m_area.area_code` has no default); the old app ran non-strict.
**Old-code findings in CRUD:** edit/update/delete were never scoped by `where()` (only the list was) — fixed; `search_field`/`order_by` were interpolated into SQL — whitelisted; relation options/DB values were echoed unescaped — escaped.
| 2026-11-11 | Master module complete (14 CRUD screens) | area, subarea, products category, products, tax rate, package (n-n channels), network, STB (+model/make/CAS/type), agent (n-n sub areas), miscellaneous heads. CRUD engine now also supports n-n, dropdown, date/datetime, virtual columns, `whereVia`. Verified by script (admin + LCO): all lists/add forms 200, n-n insert/update/search, forged/foreign ids dropped, agent scoping. **Browser check pending.** |

**Security fixes found while porting Master:** n-n posted ids are filtered to the rows the screen allows (old code let an LCO attach any sub area to its agent); STB customer dropdown no longer lists other networks' customers; network "add" limit logic kept (`network_count`).
**Still unsupported in the CRUD engine:** uploads, text editor, `add_action`, `set_rules`, `unique_fields`, `callback_after_update`. **Read view** of relation fields shows the id (Grocery showed the title) — to fix.
**Quirks kept:** columns that do not exist (`balance`, `last_lco_cust_id`) render blank like before; `network_management` add button hidden when the owned-network count equals `m_user.network_count` (for admin both are 0, so Add is hidden there too).
| 2026-11-11 | AdminSettings + Settings (partial) | SMS APIs, Main Menus, Users (+roles n-n, "Activate Generate Bill"), Import Formats, General settings (+save), maintenance scripts, Backup (generate/list/download/delete), Settings/index (payments list), Settings/general. All behind `legacy.admin` (AdminSettings) or menu/role checks. Verified by script: lists 200 for admin, 403 for LCO, settings whitelist, GET on scripts = 405, download traversal = 404. **Pending:** `payment_types` (needs the upload field type), browser check, backup run on the full DB. |

**Security changes in AdminSettings:** old controller had no auth (backup list/download/delete/generate reachable by any signed-in user, and the DB password was hard-coded in `generateBackup`); backups now live in `storage/app/backups` (not web-served), names validated, download via admin-only route; data-changing links (`scripts`, `activate_generate_bill_all`) are POST + CSRF (the page JS was minimally changed to POST). `blocknetworkMSG`/notification texts are HTML-escaped when shown.
**Behaviour kept:** `Settings/general` and `settings_save` allow any user with menu 6 to edit the *global* notification texts (old design) — please confirm that menu 6 is only given to trusted users.
**Performance lesson:** `NOT IN (SELECT ...)` over `m_ezybill_invoices` (615k rows) ran for minutes; ids are materialised first like the old code.
| 2026-11-11 | CRUD file uploads + Payment Types; last-paid check closed | `uploadField()` in the CRUD engine (same widget/JS/URLs as Grocery_CRUD: `upload_file/{field}`, `delete_file/{field}/{name}`), used by `AdminSettings/payment_types`. Verified: valid PNG stored under random name and re-encoded; `.php`, PHP-in-PNG, PDF, 3 MB, empty, polyglot GIF all rejected; forged `../../.env` filename stored as empty; delete refuses static assets and traversal, works for own referenced file and clears the DB column. `update_last_paid` parity: 150 sampled customers, 0 mismatches, rollback restored data. |

**Upload hardening vs Grocery_CRUD:** images only (gif/jpg/png, max 2 MB, max 4000 px) for payment types (old: ~30 types incl. documents/media into the web-served `assets/img`); type decided by `getimagesize()` not the client extension; image re-encoded with GD (strips appended code/metadata; animated GIFs become static); random file names; `assets/img/.htaccess` denies script files; delete limited to files the screen references or this session uploaded. For nginx at go-live add the same rule (no PHP in `assets/img`).
**Performance note:** `subscriberpayments.subscriberid` is **varchar** while `subscriberbalance.subscriberid` and `m_customer.id` are int. Always bind it as a string or MySQL ignores the index (each lookup becomes a 990k-row scan). Keep this in mind for Subscriber/Transaction.
**CRUD engine fixes:** dropdown columns show their label in lists; password columns show `******`.
| 2026-11-11 | Subscriber module, part 1 (lists + view/edit) | Pages: `Subscriber` (+`index/list`, `index/Mlist` JSON), `inactive_subscriber`, `all_subscriber` (admin), `Mlist`, `non_paid_this_month_report`, `view_subscriber/{id}`, `edit_subscriber_view/{id}`, `update_subscriber`, `updateSMSstat`, `update_mobile`, `update_combo_plan`, `sub_area`, `get_subscriber_details`. Views converted from the old PHP by a mechanical converter (same HTML/JS). Verified on the copy DB: LCO list rows/due-sum match SQL (1229 rows, 589,986.29); paid+unpaid = active; admin all-list = 16,234 customers; package map identical to the old query for 300 customers; filteramount SQL-injection attempt harmless. |
| | **Not yet ported (Subscriber)** | `subscriber_management` (add/edit customer CRUD screen with its large inline JS), `generate_network_bill`, `generate_selected_inactive_bills`, `delete_network_bill` (billing module, do with Phase 6). The list pages' bill buttons call those URLs, so they will 404 until then. |

**Security fixes (Subscriber):** `update_subscriber`, `updateSMSstat`, `update_combo_plan`, `get_subscriber_details` had no ownership check (any LCO could edit/read any customer by id) → now limited to the LCO's own `lcoCode`; `sub_area` only returns sub-areas of the user's own areas; `filteramount` was concatenated into SQL; DB names echoed in `<option>` are escaped; blocked LCOs are now logged out of Subscriber pages like Master (old Subscriber had no block check — tell me if you want the old behaviour).
**Global change:** PDO now returns every value as a string (`ATTR_STRINGIFY_FETCHES`) like mysqli did, so JSON output matches the old site byte-for-byte (important for AgentApi).
**Performance finding / recommendation:** `m_ezybill_invoices` (600k rows) has **no index on `customer_id`**; the list's "latest package" lookup took 26 s with `IN (...)`. Rewritten (whole-table group-by ≈ 4 s, list 24 s → 5 s). A one-line, non-breaking index would make it milliseconds and also speed up the old site: `ALTER TABLE m_ezybill_invoices ADD INDEX idx_customer_id (customer_id);` — needs your approval (schema change on the live DB).
| 2026-11-11 | Subscriber module, part 2: `subscriber_management` (add/edit customer) | Grocery_CRUD screen ported on the engine: unique NUID / LCO customer id, `trim|required` rules, hidden `lcoCode`, virtual "Select Subscriber" box (edit form only, as before), amc date, dropdowns, CKEditor/plain textareas, custom JS block (select2 + auto-fill). Engine gained: `fields()`, `uniqueFields()`, `rules()`, virtual form fields, Closure relation filters, `unsetTexteditor()`, text inputs, callbacks that can return their own response. Verified: validation messages, insert (customer_id/caf/created_* set server-side, forged `lcoCode`/`created_by`/`customer_id` ignored), update (network never changes), other LCO's customer = 404, insert carrying a foreign `subscriber_dropdown` rejected, delete = 403. |

**Old-code issues not carried over:** the screen ran `ALTER TABLE m_customer ADD COLUMN c_type` inside a web request (schema change on every load until it exists) — removed (`c_type` already exists in the copy; confirm it exists on the live DB before cut-over); hidden form fields (`created_by`, `lcoCode`, `customer_id`, …) were accepted from the browser — now server-defined; the "update existing customer via insert" branch had no ownership check — now restricted to the LCO's own customers.
**Engine rule:** hidden fields are never read from the browser (only fixed values such as `lcoCode`).
| 2026-11-11 | **Transaction part 1: make payment** (`Transaction/payment`, `subscriber_details`, `subscriber_payments`, `add_payment`) + `PaymentService`, `SmsService`, index `idx_customer_id` applied to the local copy | Verified on the copy DB (inside rolled-back transactions): ledger row (due / duebalance / dbafterdiscount) and `subscriberbalance` match independent old-style arithmetic; SMS text identical to the old format; invalid amounts (negative, text, 3 decimals, `1e3`, empty) rejected with no ledger change; foreign customer rejected for details/history/payment; CSRF 419; agent (usertype 3) can pay only customers of its network **and** areas, ledger `agentid` set; agent cannot open the LCO page. **Concurrency:** two processes x 20 payments on one customer => 40 rows, 0 chain breaks, final balance exactly -40 (no lost update; the old code had no lock). |

**Safety switches (local `.env`):** `SMS_SEND_ENABLED=false` (the DB copy holds real SMS provider URLs and real customer numbers — nothing is sent unless this is true; payments only *queue* SMS rows), `UPIGATEWAY_KEY=` empty. Tests must fake HTTP (`Http::fake`) and never use a live key (project rule from the owner). The old hard-coded UPIGateway key, redirect URL, email and phone are **not** copied; they go in `.env` (`UPIGATEWAY_*`). **Rotate that key** (it is in the old source).
**Behaviour notes:** amounts are validated server-side (digits, max 2 decimals, non-negative; the UI allows 0-9999 integers); the old `sms_payment()` flushed *every* LCO's queued SMS with the current user's gateway — now only the new payment's row (the cron handles the rest); SMS URLs use `rawurlencode` and TLS verification stays on; `Transaction::balance_check` (queries a hard-coded `cablebill_live` database) and `subscriber_search_result` (not used by any view) are not ported.
**Index:** `database/sql/001_idx_ezybill_invoices_customer_id.sql` (approved) — applied to `cablebill02nov`; **must also be run on the live DB at go-live**. Subscriber list: 24 s -> 1.7 s.
| 2026-11-11 | **Transaction part 2**: ledger (`ledger`, `ledger_result`), `delete_voucher`, miscellaneous (`miscellaneous`, `miscellaneous_report/{0,1}`, `add_miscellaneous`), bulk (`bulkoperations`, `add_bulk_miscellaneous` incl. "all customers"), expenses (`expenses`, `expenses_add`, `expenseheader_management`) + `LedgerService` + `audit` log channel | Verified on the copy DB (rolled-back transactions): voucher delete restores the balance exactly and only for the newest row (older row, foreign customer, and users without menu 13 are refused; audit entries written); misc charge row/balance/SMS text identical to the old format (incl. the old float-to-text number style); invalid head/amount/foreign customer rejected; **bulk "all"**: 1229 customers -> 1229 ledger rows, 0 mismatching rows, unique timestamps, status-2 customers untouched, 2.7 s, all-or-nothing transaction; misc report counts match; expense add validates header ownership/amount/date; expense-head CRUD list scoped to the user. |

**Security fixes (part 2):** `delete_voucher` was only protected by hiding the button — menu 13 is now enforced on the server, the delete runs under a row lock, and every deletion (and refused attempt) goes to `storage/logs/audit-*.log` (old: plain file in `application/logs/delete/`); `ledger_result` showed any customer's ledger to any user (now own customers only); `add_miscellaneous` / bulk had no menu or ownership check and no validation; `miscellaneous_report` interpolated head/dates into SQL (now bound; dates validated); expenses could be posted against another user's expense head.
**Behaviour kept on purpose:** `add_miscellaneous` writes `is_misc=0`, the bulk screen's single mode writes `is_misc=1` (reports filter on it); SMS text uses PHP's float formatting ("9327.5", not "9327.50"); misc amounts may be negative (credit), payments may not.
**Not ported:** `Transaction::subscriber_ledger` (debug leftover: `echo $sql; exit;`), `balance_check`, `subscriber_search_result` (unused).
**IMPORTANT — 11 tables are MyISAM (no transactions/rollback):** `expenses, m_user, settings, upigateway_history, m_combo_plan, actdeactlog, broadband, complaintregister, empsalary, nodes, temp_cust`. Rollback-based tests do not protect them, and the UPIGateway flow cannot rely on a DB transaction for `upigateway_history` — idempotency must be enforced through the InnoDB `gsoft_billing` row (lock + status check). Test rule: never modify these tables in tests (two test expense rows were created and removed by hand).
| 2026-11-11 | **Transaction part 3a: GSoft monthly bills + UPIGateway** (`monthly_bills`, `monthly_bills_data`, `monthly_bills_view`, `payment_type_view`, `initiate_payment`) + `GsoftBillingService`, `UpiGatewayService` | **Gateway always faked in tests** (`Http::fake` + `preventStrayRequests`, dummy key; real key never used). Verified: order amount = the bill's `invoice_total` even when the browser posts amount=1; foreign bill / already paid / free bill refused; gateway "created" status, API error, amount mismatch, and wrong bill id never credit; real success credits once (status 2, type 4, tran_id) and a duplicate poll/callback does not credit again; manual "payment completed" cannot move a paid bill back to Initiated; with no key configured no HTTP call is made. Screen numbers equal an independent old-style SQL (June 2026: 639 invoices, 639.00). Screen speed: 17 s per month -> ~3-5 s per month (bill looked up first; literal id list instead of IN-subquery: 15 s -> 0.25 s). |

**Security fixes (3a):** old `create_order` used the amount posted by the browser (pay 1 rupee, bill marked paid); bill id was not checked against the caller's LCO; success was credited without checking amount or that the order belongs to the bill; "payment completed" could regress a paid bill; the gateway key/email/phone/redirect were hard-coded.
**Test hygiene:** `upigateway_history` is MyISAM — test rows are removed by hand (history back to 120 rows).
**Optional recommendation (needs approval):** `ALTER TABLE subscriberpayments ADD INDEX idx_timestamp (timestamp)` — the monthly SMS counts scan 990k rows (~3 s per month) and so do several reports.
| 2026-11-11 | **Transaction part 3b: invoice history + approval** (`import_history`, `import_status_wise_history/{ym}/{status}`, `import_sms_status_wise_history/{ym}/{status}`, `approve`, `edit_bill`) + `InvoiceService`; CRUD engine got `whereIn` | Verified on the copy DB: grid counts (pending/approved/total/SMS sent) equal independent SQL for the LCO; status-wise list 653/653 rows; "system calculated days/amount" equals a direct port of the old algorithm (stored-days, package-match and **prorated** branches); approve posts exactly one "Monthly subscription" ledger row (due 376.33 + 250.50 = 626.83) and moves the balance; approving twice, a bad amount/days, another LCO's invoice, and a mismatched invoice/customer pair change nothing; GET approve = 405 (POST + CSRF only); edit_bill only for own invoices. Import-history page 6 s (was ~15 s per row with sub-queries). |

**Security fixes (3b):** `approve` was a GET link that let any logged-in user charge any amount to any customer by editing the URL (amount/days came from the URL, no ownership check, no CSRF) — now POST, ownership + invoice/customer pair verified, amount/days validated, already-approved refused, locked transaction, audit log; edit_bill only for own invoices and only redirects back to this site.
**Remaining in Transaction part 3:** bill generation buttons (`Subscriber::generate_network_bill`, `generate_selected_inactive_bills`, `delete_network_bill` + Model_subscriber bill generators), AdminSettings monthly bills (`generate_monthly_bills`, admin bill list, `complete_payment`), `add_broadband_payment`, PDF receipt (`Pdf`, `Pdf_data_modal2`, `print_bill`).
