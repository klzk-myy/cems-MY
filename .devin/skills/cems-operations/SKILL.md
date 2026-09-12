---
name: cems-operations
description: Operate the CEMS-MY Laravel app end-to-end — bring up local.host, run the business setup wizard, log in, open a counter, and create transactions. Use when browser-testing, setting up a fresh environment, or verifying the setup-to-transaction dataflow.
---

# CEMS-MY Operations Runbook

Learned by driving the real app in a browser. Update this file whenever a test run reveals new behavior or a bug is fixed.

## 1. Environment bring-up (http://local.host)

The site runs under aaPanel nginx + PHP-FPM 8.3 (`/tmp/php-cgi-83.sock`). To make `http://local.host` serve the Laravel app:

1. **Docroot**: `/www/server/panel/vhost/nginx/local.host.conf` must have `root /www/wwwroot/local.host/public;` (NOT the project root — that serves the static `index.html` placeholder). Requires sudo.
2. **Rewrite**: `/www/server/panel/vhost/rewrite/local.host.conf` must contain:
   ```nginx
   location / { try_files $uri $uri/ /index.php?$query_string; }
   ```
3. **`.user.ini`**: copy `/www/wwwroot/local.host/.user.ini` into `public/` so `open_basedir` still applies after the docroot move.
4. **Storage perms**: PHP-FPM runs as `www`. `chmod -R g+w storage bootstrap/cache` (repo group is `www`).
5. **MySQL**: `.env` expects `cems_my_staging` / `cems_staging` / `Staging123`. MySQL root password is in the panel sqlite `config` table (`/www/server/panel/data/default.db`). Create the user+db if missing:
   ```sql
   CREATE DATABASE cems_my_staging CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'cems_staging'@'localhost' IDENTIFIED BY 'Staging123';
   CREATE USER 'cems_staging'@'127.0.0.1' IDENTIFIED BY 'Staging123';
   GRANT ALL ON cems_my_staging.* TO 'cems_staging'@'localhost';
   GRANT ALL ON cems_my_staging.* TO 'cems_staging'@'127.0.0.1';
   ```
6. **Schema**: `php artisan db:seed --class=SchemaSeeder` (destructive — drops all tables; only safe on an empty/disposable DB). Creates 85 tables + reference data.
7. Reload: `sudo /www/server/nginx/sbin/nginx -s reload`.
8. Verify: `GET /` should 302 → `/setup` on a fresh install.

Env notes: `QUEUE_CONNECTION=redis`, `SESSION_DRIVER=file`, `CACHE_DRIVER=redis`, `REDIS_PASSWORD=redpass` (keep `.env` and `phpunit.xml` in sync).

## 2. Setup wizard (`/setup`, 6 steps + review)

GET `/` on a fresh DB redirects to `/setup`. Each step POSTs `setup/step/N` which stores validated data in `session('setup.*')` and redirects to `?step=N+1`. Step 7 is a review screen that POSTs `setup/complete` via fetch → JSON `{success, redirect: /login}`.

| Step | Route | Fields | Session key |
|---|---|---|---|
| 1 | `setup/step/1` | `business_name`*, `business_address`, `business_phone`, `business_email` | `setup.business` |
| 2 | `setup/step/2` | `admin_name`* (becomes `username`), `admin_email`* (unique), `admin_password`* + `admin_password_confirmation` (required, `confirmed` rule + `PasswordComplexityRule`: ≥12 chars, mixed case, digit, symbol) | `setup.admin` |
| 3 | `setup/step/3` | `base_currency`* (3 chars, use `MYR`), `active_currencies[]`* (min 1), optional custom currency: `custom_currency_code` (3 alpha) + `custom_currency_name` (required with code) + `custom_currency_symbol` | `setup.currencies` |
| 4 | `setup/step/4` | `use_default_rates` (boolean — checkbox must submit `value="1"`, not `"on"`), `custom_rates[CODE][buy/sell]` (required when a custom currency was entered in step 3) | `setup.rates` |
| 5 | `setup/step/5` | `initial_myr_cash`* (numeric ≥0), `initial_stock[CODE]` — one input per step-3 selection incl. custom currency | `setup.stock` |
| 6 | `setup/step/6` | `opening_balance_myr`* (numeric ≥0), `opening_balance_foreign[CODE]` — same currency set as step 5 | `setup.opening_balance` |

**Custom ("other") currency path**: step 3 accepts a code not in the seeded list — code is uppercased and folded into `active_currencies`, so steps 5/6 render inputs for it even though it is not yet in `currencies`. Step 4 then requires its buy/sell rates (`custom_rates[CODE]`, rendered automatically). On completion, `executeSetup` `firstOrCreate`s the currency (`decimal_places=2`, `is_active=1`, name/symbol from step 3, code as fallback) and writes the rate as an `exchange_rates` row with `source=setup_custom`. Verified with `thb` (lowercase input) → `THB Thai Baht ฿`, rate 0.1280/0.1320, pool+position 20,000 with avg_cost = buy rate.

`executeSetup()` then: creates `HQ` head-office `Branch` + default `Counter` C01 (bound to HQ — no counter CRUD UI exists), creates admin `User` (username = `admin_name`, role `Admin`, `branch_id`=HQ, `mfa_enabled=false`), seeds `CurrencySeeder` + `EnhancedChartOfAccountsSeeder`, applies `active_currencies` → `currencies.is_active` (MYR always stays active), `ensureFiscalYearAndPeriods()`, optionally seeds `ExchangeRateSeeder` (10 currency rows incl. non-active ones, `source=initial_seed`, `effective_date=NULL` = active immediately), writes `branch_pools` from `initial_stock`, posts opening-balance journal `OB-<year>-0001`, and marks `setup_state` complete. `setup/reset` re-runs SchemaSeeder (full wipe) — non-production only.

Test values used: business `CEMS Demo Exchange Sdn Bhd`, admin `admin` / `admin@cemsdemo.my` / `CemsAdmin@2026`, stock+balances MYR 50000 / USD 10000 / EUR 5000 / GBP 3000 / SGD 8000.

## 3. Verification queries (MySQL `cems_my_staging`, root pw `Klzk@9199`)

```sql
SELECT username,email,role,is_active FROM users;            -- admin row
SELECT code,name,type,is_main FROM branches;               -- HQ head_office
SELECT code,is_active FROM currencies;                     -- active set
SELECT currency_code,buying_rate,selling_rate FROM exchange_rates;
SELECT * FROM branch_pools;                                -- initial stock lands here
SELECT * FROM journal_lines;                               -- opening balance lines
SELECT * FROM setup_state;                                 -- completion marker
```

**Invariant**: the opening-balance journal must balance — `SUM(debit) = SUM(credit)` per `journal_entry_id`. Debits: `1000` MYR cash + `1011` foreign cash. Credit: `4000` Capital (equity). Setup seeds BOTH `branch_pools` and `currency_positions` (position avg_cost = buy rate, MYR = 1.0) — both are required; Sell validates against `currency_positions`.

## 4. Bugs found & fixed during browser test (2026-09-12)

- **Setup step 2 blocked**: `confirmed` rule but no `admin_password_confirmation` field → added to `setup/index.blade.php`.
- **Setup step 3 silently failed**: form submitted `currency_codes[]`; rules expected `base_currency` + `active_currencies[]` → renamed + added base-currency select + `executeSetup` now honors the selection. Also added an `$errors->any()` alert (validation failures were invisible).
- **Setup step 4**: `use_default_rates` checkbox submitted `"on"`, failing the `boolean` rule → checkbox component now defaults `value=1`.
- **Missing step 7**: `@case(7)` absent → added review screen + fetch POST to `setup/complete`.
- **Unbalanced opening journal**: `SetupService` used account `1010` (doesn't exist) for MYR cash and `3000` (**Accounts Payable**) for equity → now uses `AccountCode::CASH_MYR` (`1000`) and `AccountCode::CAPITAL` (`4000`). Verified balanced: 50,000 + 26,000 debit = 76,000 credit.
- **No counters after wizard**: `executeSetup` never seeded counters and no counter CRUD UI exists → now creates default `C01` on HQ; admin `branch_id` set to HQ (branch-scoped queries need it). `CounterSeeder` exists but creates branch-less rows — avoid it.
- **`x-button href=` dead** (137 usages): component only rendered `<button>` → restored `href`→`<a>` plus `disabled`/`loading`/`icon` props. Commit `bbde652a` had gutted 8 core components (button, card, checkbox, input, select, textarea, alert, badge); all restored and merged with later null-safe attribute/error-bag fixes and the `danger`↔`error` aliases.
- **`index()` progress** always shows "40% Complete" — `calculateProgress()` measures persisted components, not wizard position. Cosmetic.
- **Counter open silently failed**: `counters/open.blade.php` submits `opening_floats[CODE]=amount` (map), but `OpenCounterRequest` rules expect `opening_floats.*.{currency_id,amount}` (list). Validation bounced with no visible error. Fixed via `prepareForValidation()` normalizing the map into the list (API list payloads still pass through since `array_is_list` short-circuits). `CounterService::resolveCurrencies()` accepts both codes and numeric IDs.
- **Same bug class still open in close/handover**: `CloseCounterRequest` expects `closing_floats.*.{currency_id,amount}` but `counters/close.blade.php` submits `myr_cash` + `currencies[CODE][count]`; `HandoverCounterRequest` expects `from_user_id`/`to_user_id`/`supervisor_id`/`physical_counts.*` but the handover view submits only `to_user_id`/`pin`/`notes`. Both views are stub-era and need rebuild or request normalization — close/handover will fail until fixed.
- **`LIKE ? ESCAPE '\\'` broken on MariaDB**: `"...ESCAPE '\\'"` in a PHP double-quoted string emits SQL `ESCAPE '\'` — the backslash escapes the closing quote → unterminated string → SQLSTATE 42000. SQLite tolerates `'\'` as a one-char literal, so the whole test suite (SQLite) never caught it; the first real MySQL customer store crashed during sanctions prefiltering. Fixed all 8 call sites to bind the escape char (`ESCAPE ?`, binding `'\\'`) — same convention as `CustomerIndexAction`. Files: `CustomerScreeningService` (2), `ComplianceService` (2), `CustomerRepository` (4 sites in 2 methods), `Api/V1/CustomerController` (1).
- **Encrypted `phone`/`address` rendered as ciphertext**: `CustomerService::encryptCustomerData` encrypts both at rest (with `phone_hash` blind index), but `customers/show` and `customers/edit` rendered `$customer->phone`/`address` raw. Added `decryptPhone()` and passed `decryptedPhone`/`decryptedAddress` from `show()`/`edit()`.
- **Phone validation is strict**: `+60123456789` only — dashes/spaces rejected (`regex:/^(\+?6?01)[0-9]{8,9}$/`).
- **New customers get risk_score 60 / High by default** — risk assessed at creation; a High-risk customer's transaction goes to `PendingApproval` (compliance hold), not `Completed`.

## 4b. Keyboard-only operation (TAB/ENTER, verified 2026-09-12)

The app is operable without a mouse: skip-link is the first TAB stop → nav links → logout → header bell → content. ENTER submits native forms, opens file choosers on `type=file`, and advances the transaction wizard (steps wrapped in `<form @submit.prevent="submitStep()">`). ESC closes every modal/dropdown (`@keydown.escape.window`). Collapsed-sidebar links keep accessible names via `sr-only` labels.

**Wizard is teller-only**: `/transactions/wizard` page AND `api/v1/wizard/transactions/*` require `role:teller` (deliberate — `TellerRoleCheckTest` asserts manager/admin get 403). Admins use `/transactions/create` instead.

Bugs found by the keyboard walkthrough (all fixed):
- Wizard ENTER didn't advance (inputs not in a form) → form wrapper + `submitStep()` dispatcher.
- `type` select sent `buy`/`sell`; `TransactionType` enum is `Buy`/`Sell` → step1 422 on every submit.
- `cdd_level` returned `Standard`/`Enhanced` but compared `=== 'standard'` → required docs + enhanced fields never rendered → step2 always failed for Standard/Enhanced. Normalized via `cddLevel` getter.
- `customer_details` stored `UploadedFile` objects in the wizard session → "Serialization not allowed" crash on every upload → `Arr::except` file keys (paths stored via `processDocuments`).
- `customers/{id}` 500'd: `$transaction->currency` lazy-load under `preventLazyLoading` + enum `type` unechoable → `currency_code` / `type?->value`.
- `APP_URL` mismatch (`staging.local.host:8080` vs actual `local.host`) → `local.host` not in Sanctum stateful domains → every same-origin `/api/v1/*` call (wizard steps, notification polling, till dropdown) returned 401. Fixed `.env`; `.env.example` now documents `SESSION_DOMAIN`/`SANCTUM_STATEFUL_DOMAINS`.

## 5. Counter opening (`/counters/{code}/open`)

Route-model binding is by **counter code** (`C01`), not id. The form shows one float input per active currency; submitting creates:

- `counter_sessions` row: `status=open`, `session_date`=today, `user_id`=opener. Guards: `TillAlreadyOpenException` (counter already open), `UserAlreadyAtCounterException` (teller already at another counter).
- `till_balances` rows: one per currency, `till_id`=counter code string, `opening_balance`=float, `branch_id` set, `closed_at` NULL.
- **No new journal entry** — floats allocate existing branch-pool cash, not new money.
- `system_logs` `counter_opened` entry with `new_values` JSON (floats, opener, date), sealed later by `SealAuditHashJob`.

Verified: session id 4 (counter 4 / user 4), 5 till rows, hash `v2:…` sealed after `queue:work`. Counter index then shows status `Open`.

**Queue worker is required**: `SealAuditHashJob` (and other async effects) queue on Redis `queues:default`/`audit`. With no worker, `system_logs.entry_hash` stays NULL. Run `php artisan queue:work --queue=default,audit --stop-when-empty` to drain during tests.

## 6. Customer creation (`/customers/create`)

Required: `full_name`, `id_type` (`MyKad|Passport|Others`), `id_number` (MyKad = `YYMMDD-PB-NNNN`), `nationality`, `date_of_birth`. Phone must be `+601…` (8-9 digits after 01, no separators). `id_number`/`phone`/`address` are **encrypted at rest** (`id_number_encrypted`, ciphertext in `phone`/`address`) with blind indexes for dedup — views must use `$decryptedPhone`/`$decryptedAddress` passed by the controller. Store triggers sanctions prefiltering + risk scoring; a fresh customer scored **60 → High** (no transaction history). Failure path shows only "Failed to create customer. Please contact support." — check `laravel.log` for the real exception.

## 7. Transaction create → approve (`/transactions/create`)

Fields: `type` (Buy/Sell), `customer_id`, `currency_code`, `amount_foreign`, `rate`, `counter_id`, `purpose`, `source_of_funds` (+ hidden `branch_id`, `idempotency_key`). `counter_id` is mapped to `till_id` (counter **code**) in `prepareForValidation`. The rate must be within `RATE_MAX_DEVIATION` (5%) of the seeded rate — use the seeded sell rate (e.g. USD 4.81) for a Sell.

Status logic (`determineInitialStatus`, also in the `DeterminesTransactionStatus` trait used by the wizard controller, and mirrored in `TransactionImportService`): `Completed` only when MYR total < RM3,000 (`approval.auto_approve` threshold) AND customer `risk_rating` is `Low` AND no compliance hold. Any Medium/High/unknown risk → `PendingApproval` even below RM3,000. **High-risk customer → `PendingApproval` + `hold_reason=Compliance hold` + `cdd_level=Enhanced`.** Verified: Sell USD 500 @4.81 = RM2,405 → PendingApproval (customer was High risk).

**Field-specific errors**: domain exceptions are mapped to inputs via `App\Http\Concerns\MapsTransactionExceptionsToFields` — insufficient stock/position-limit → Foreign Amount, rate deviation/invalid rate → Exchange Rate, invalid currency → Currency, blocked/frozen/KYC-expired/PEP-approval customer → Customer, missing till/allocation → Counter, missing PEP `source_of_wealth`/`source_of_funds` → those fields. Compliance blocks (`TransactionBlockedException`) pin to Customer with a generic message — the specific reason is deliberately not leaked to tellers. Unmapped domain exceptions flash the exception message in the top banner. The wizard returns the same map as JSON `field` + 422; the API v1 response carries `errors.code`/`errors.field`.

**Screening & scoring at create-time**: `preValidate` runs a real sanctions screen per transaction (`screenCustomer` → `screenName`, records `screening_results`, blocks on `action=block`, flags on `action=flag`). Non-blocking flags are added as `severity=critical` risk flags → force `PendingApproval`. After commit, the queued `TransactionCreatedListener` runs `monitorTransaction` (velocity/structuring/aggregate flags → `transaction_flags`) and `RiskScoringEngine::recalculate` which **persists** the score — `customers.risk_score`/`risk_rating`/`risk_assessed_at` write-back, `customer_risk_profiles` + `customer_risk_history` rows, and a `compliance_findings` `Risk_Score_Change` row on delta ≥10. Without a queue worker none of the post-commit effects land — drain with `queue:work --stop-when-empty`. Note: rapid-fire sub-RM3,000 test transactions will themselves trigger the `structuring` critical flag → PendingApproval, and push the customer's rating up (verified: 10→70, Low→High).

Approval requires a **different user** (segregation of duties — creator cannot approve). No manager exists after setup; create one via `/users/create` (role `manager`, same branch), then log in as them and hit Approve on the transaction page.

### Post-approval effects (verified)

- `transactions`: `Completed`, `approved_by`, `journal_entry_id` set, `prev_quantity`/`prev_average_cost` snapshot for reversal.
- `stock_reservations`: `pending` → `consumed` (created at PendingApproval, 24h expiry).
- `currency_positions`: USD quantity decreased by sold amount; `average_cost` unchanged. **Sell requires an existing position row** — `InsufficientStockException` if absent/zero.
- `till_balances`: `transaction_total` += MYR amount, `foreign_total` -= foreign amount, `sell_total_foreign` += amount.
- `journal_entries`: Posted at approval — debit `1000` Cash MYR (amount_local), credit `2000` foreign inventory (amount × average_cost), credit `5000` realized gain (spread). `entry_number` stays NULL on transaction journals.
- `system_logs`: `transaction_created` → `pre_validation_completed` → `transaction_approved` (CRITICAL) → `journal_entry_created` → `deferred_journal_entries_created`. Hash chain links correctly once `SealAuditHashJob` drains.
- **`counter_id` on the transaction stays NULL** — only `till_id` (string) is persisted; show page renders "Counter: N/A". Dataflow gap, not yet fixed.

### Known issues observed

- `user_created` audit + user create 500'd before `UserService::createUser` fix (password_hash was set after `User::create`, tripping the `creating` hook).
- Setup seeded `branch_pools` but never `currency_positions` → every Sell failed with InsufficientStock until `SetupService::createInitialStock` was fixed to seed positions too (cost basis = seeded buy rate, MYR = 1).
- `/system/currencies/create` used to insert only a `currencies` row → the new code had no accounting footprint until lazily provisioned. `CurrencyController::store` now creates zero `branch_pools` + `currency_positions` for every **active** branch (audit `provisioned_branches`). Verified: `KRW` via UI → pool 0.0000 + position 0 on branch 4. Note: no `exchange_rates` row is created — the currency is sellable-at-zero/buyable but has no market rate until one is set in `/rates`.

## 8. Remaining verification
