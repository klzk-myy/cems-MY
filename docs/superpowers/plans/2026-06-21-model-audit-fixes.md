# Models Audit Fixes Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix verified schema mismatches, security gaps, and code quality issues identified across 75 Eloquent models.

**Architecture:** Two migration files fix schema mismatches (CustomerRiskHistory, TransactionImport). Model file changes add missing `$hidden` arrays, fix the HasStatus trait's mergeFillable side effect, and remove dead code. Service dependency cleanup is deferred (architectural debt, not bugs).

**Tech Stack:** Laravel 10, PHP 8.3, PHPUnit 10, MySQL 8.0

## Global Constraints

- PHP 8.3.30, Laravel 10, PHPUnit 10
- Follow existing code conventions — check sibling files for patterns
- Run `vendor/bin/pint --dirty --format agent` before every commit
- Run affected tests after each task: `php artisan test --compact --filter=<TestName>`
- Preserve exact API response shapes — no breaking changes to consumers
- Use `php artisan make:migration` for new migrations
- All Form Requests extend `ApiFormRequest` (API) or `AuthorizedFormRequest` (web)

## Audit Correction Summary

Several original audit findings were false positives after detailed verification:

| Original Finding | Verdict | Evidence |
|---|---|---|
| EddDocumentRequest `status` not in `$fillable` | **FALSE** | `status` IS in `$fillable` (line 24) |
| ComplianceCase phantom `subject()` relationship | **FALSE** | No `subject()` method exists |
| CurrencyPosition schema mismatch | **FALSE** | Legacy aliases with accessors/mutators — intentional |
| StockTransfer schema mismatch | **FALSE** | All fillable names match migration columns |
| User `password` leak | **FALSE** | `password` IS in `$hidden` (line 61) |
| Transaction `is_refund` not cast to boolean | **FALSE** | IS cast to `'boolean'` (line 108) |
| ComplianceCase `resolution` not cast to enum | **FALSE** | IS cast to `CaseResolution::class` (line 51) |
| MfaRecoveryCode missing `$hidden` | **FALSE** | `code_hash` IS in `$hidden` |
| Customer `id_number_encrypted` missing `$hidden` | **FALSE** | IS in `$hidden` |
| DeviceComputations `device_fingerprint` missing `$hidden` | **FALSE** | IS in `$hidden` |
| Inconsistent MoneyCast across 8 models | **FALSE** | All monetary fields already use `MoneyCast::class` |
| Redundant `$with = []` | **FALSE** | No model has empty `$with` |
| Deprecated `$dates` property | **FALSE** | No model uses `$dates` |
| Unused `Model` imports | **FALSE** | None found |
| HasReferenceNumber dead trait | **ALREADY RESOLVED** | Trait file deleted in prior session |

---

## File Structure

```
database/migrations/
├── 2026_06_21_000001_fix_customer_risk_history_columns.php  (CREATE)
├── 2026_06_21_000002_fix_transaction_import_columns.php      (CREATE)

app/Models/
├── CustomerRiskHistory.php     (MODIFY — fix $fillable, $casts, relationship column refs)
├── TransactionImport.php       (MODIFY — fix $fillable, $casts, relationship column refs)
├── SystemLog.php               (MODIFY — add $hidden)
├── Traits/HasStatus.php        (MODIFY — remove mergeFillable side effect)
```

---

### Task 1: Fix CustomerRiskHistory schema mismatch (migration)

**Files:**
- Create: `database/migrations/2026_06_21_000001_fix_customer_risk_history_columns.php`
- Modify: `app/Models/CustomerRiskHistory.php`

**Context:** The `customer_risk_history` migration uses `old_score`, `old_rating`, `assessed_by`, and has no `changed_at` column. The model expects `previous_score`, `previous_rating`, `changed_by`, and `changed_at`. This causes silent write failures — any `create()` or `update()` with the model's field names writes to non-existent columns.

**Interfaces:**
- Consumes: None (first task)
- Produces: Correct schema for CustomerRiskHistory, updated model $fillable/$casts/$relationship

- [ ] **Step 1: Create migration to fix column names**

```bash
php artisan make:migration fix_customer_risk_history_columns --no-interaction
```

- [ ] **Step 2: Write the migration**

Edit `database/migrations/2026_06_21_000001_fix_customer_risk_history_columns.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_risk_history', function (Blueprint $table) {
            $table->renameColumn('old_score', 'previous_score');
            $table->renameColumn('old_rating', 'previous_rating');
            $table->renameColumn('assessed_by', 'changed_by');
            $table->timestamp('changed_at')->nullable()->after('changed_by');
        });
    }

    public function down(): void
    {
        Schema::table('customer_risk_history', function (Blueprint $table) {
            $table->dropColumn('changed_at');
            $table->renameColumn('changed_by', 'assessed_by');
            $table->renameColumn('previous_rating', 'old_rating');
            $table->renameColumn('previous_score', 'old_score');
        });
    }
};
```

- [ ] **Step 3: Update the model to match new schema**

Edit `app/Models/CustomerRiskHistory.php` — the `$fillable` and `$casts` already use the new names, so they're correct after migration. But update the `assessor()` relationship to use the renamed column:

```php
public function assessor(): BelongsTo
{
    return $this->belongsTo(User::class, 'changed_by');
}
```

This is already correct — `changed_by` matches the renamed column. No model change needed.

- [ ] **Step 4: Run migration and verify**

```bash
php artisan migrate --no-interaction
```

Expected: Migration runs successfully, `customer_risk_history` table has columns `previous_score`, `previous_rating`, `changed_by`, `changed_at`.

- [ ] **Step 5: Run tests**

```bash
php artisan test --compact --filter=CustomerRiskHistory
```

Expected: PASS (or no tests exist — verify with grep).

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_06_21_000001_fix_customer_risk_history_columns.php
git commit -m "fix: rename customer_risk_history columns to match model fillable names"
```

---

### Task 2: Fix TransactionImport schema mismatch (migration)

**Files:**
- Create: `database/migrations/2026_06_21_000002_fix_transaction_import_columns.php`
- Modify: `app/Models/TransactionImport.php`

**Context:** The `transaction_imports` migration uses `user_id`, `errors`, `started_at`. The model expects `imported_by`, `error_details`, `imported_at`. Additionally, the model has `file_hash`, `file_size`, `processed_rows` in `$fillable` that don't exist in the migration.

**Interfaces:**
- Consumes: None
- Produces: Correct schema for TransactionImport, updated model

- [ ] **Step 1: Create migration**

```bash
php artisan make:migration fix_transaction_import_columns --no-interaction
```

- [ ] **Step 2: Write the migration**

Edit `database/migrations/2026_06_21_000002_fix_transaction_import_columns.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction_imports', function (Blueprint $table) {
            $table->renameColumn('user_id', 'imported_by');
            $table->renameColumn('errors', 'error_details');
            $table->renameColumn('started_at', 'imported_at');
            $table->string('file_hash')->nullable()->after('original_filename');
            $table->unsignedBigInteger('file_size')->nullable()->after('file_hash');
            $table->unsignedInteger('processed_rows')->default(0)->after('total_rows');
        });
    }

    public function down(): void
    {
        Schema::table('transaction_imports', function (Blueprint $table) {
            $table->dropColumn(['file_hash', 'file_size', 'processed_rows']);
            $table->renameColumn('imported_at', 'started_at');
            $table->renameColumn('error_details', 'errors');
            $table->renameColumn('imported_by', 'user_id');
        });
    }
};
```

- [ ] **Step 3: Update the model's relationship**

Edit `app/Models/TransactionImport.php` — the `user()` relationship already uses `'imported_by'` foreign key (line 46), which matches after rename. The `getErrors()` method already references `error_details` (line 78). No model changes needed.

- [ ] **Step 4: Run migration and verify**

```bash
php artisan migrate --no-interaction
```

Expected: Migration runs successfully.

- [ ] **Step 5: Run tests**

```bash
php artisan test --compact --filter=TransactionImport
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_06_21_000002_fix_transaction_import_columns.php
git commit -m "fix: rename transaction_imports columns and add missing fields to match model"
```

---

### Task 3: Add $hidden to SystemLog for sensitive fields

**Files:**
- Modify: `app/Models/SystemLog.php`

**Context:** `SystemLog` has no `$hidden` array. Sensitive fields like `session_id`, `ip_address`, `user_agent`, `previous_hash`, and `entry_hash` are exposed in JSON/array serialization. This is a security gap — audit logs should not leak session tokens or IP addresses via API responses or logging.

**Interfaces:**
- Consumes: None
- Produces: SystemLog with sensitive fields hidden from serialization

- [ ] **Step 1: Read current file**

Read `app/Models/SystemLog.php` — confirmed: no `$hidden` property defined.

- [ ] **Step 2: Add $hidden array**

Edit `app/Models/SystemLog.php` — add after `$casts`:

```php
protected $hidden = [
    'session_id',
    'ip_address',
    'user_agent',
    'previous_hash',
    'entry_hash',
];
```

- [ ] **Step 3: Run tests**

```bash
php artisan test --compact --filter=SystemLog
```

Expected: PASS.

- [ ] **Step 4: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 5: Commit**

```bash
git add app/Models/SystemLog.php
git commit -m "fix: hide sensitive fields in SystemLog serialization"
```

---

### Task 4: Fix HasStatus trait mergeFillable side effect

**Files:**
- Modify: `app/Models/Traits/HasStatus.php`

**Context:** The `HasStatus` trait's `initializeHasStatus()` method calls `$this->mergeFillable([$this->statusColumn])`, which silently makes `status` mass-assignable on ALL consuming models. This bypasses explicit `$fillable` exclusions — e.g., `Transaction` manages `status` via a state machine and should NOT have `status` in fillable (it should only be set via `update()` with explicit assignment, not `fill()`).

The trait is used by `ComplianceModel` (base for all compliance models) and `TransactionModel` (base for all transaction models). Making status fillable on transactions could allow state machine bypass via `Transaction::create([... 'status' => 'Completed ...])`.

**Interfaces:**
- Consumes: None
- Produces: HasStatus trait without automatic mergeFillable side effect

- [ ] **Step 1: Read current trait**

Read `app/Models/Traits/HasStatus.php` — confirmed: `initializeHasStatus()` calls `mergeFillable([$this->statusColumn])`.

- [ ] **Step 2: Remove mergeFillable from the trait**

Edit `app/Models/Traits/HasStatus.php` — remove the `initializeHasStatus()` method entirely:

```php
trait HasStatus
{
    protected string $statusColumn = 'status';

    /** @return array<int|string|BackedEnum> */
    protected function activeStatusValues(): array
    {
        return [];
    }

    // ... rest unchanged
}
```

- [ ] **Step 3: Add status to $fillable on models that need it**

Models that currently rely on the trait's mergeFillable to make status fillable need it added to their explicit `$fillable`. Check which models use HasStatus:

```bash
grep -rn 'use HasStatus' app/Models/
```

For each model that uses HasStatus and needs status in fillable, add `'status'` to its `$fillable` array. Models to check:
- `ComplianceCase` — already has `status` in `$casts` as enum, but NOT in `$fillable`. Needs `'status'` added if mass-assignment is used.
- `ComplianceFinding` — check if needs status fillable
- `Alert` — check if needs status fillable
- `Transaction` — does NOT need status in fillable (state machine manages it)
- Other compliance models — check each

For each compliance model that needs status fillable, add `'status'` to the `$fillable` array explicitly.

- [ ] **Step 4: Run full test suite**

```bash
php artisan test --compact
```

Expected: All tests pass. If any fail, it means a model was relying on the trait's mergeFillable — add `'status'` to that model's explicit `$fillable`.

- [ ] **Step 5: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 6: Commit**

```bash
git add app/Models/Traits/HasStatus.php app/Models/Compliance/*.php
git commit -m "fix: remove mergeFillable side effect from HasStatus trait"
```

---

### Task 5: Verify and run full test suite

**Files:**
- None (verification only)

**Context:** After all fixes, run the full test suite to ensure no regressions. This task is the final gate.

- [ ] **Step 1: Run full test suite**

```bash
php artisan test --compact
```

Expected: All tests pass (981+ tests, 2549+ assertions).

- [ ] **Step 2: Run Pint on all modified files**

```bash
vendor/bin/pint --dirty --format agent
```

Expected: No changes needed.

- [ ] **Step 3: Verify migrations rollback cleanly**

```bash
php artisan migrate:rollback --no-interaction
php artisan migrate --no-interaction
```

Expected: Both commands complete without errors.

- [ ] **Step 4: Final commit if Pint made changes**

```bash
git add -A
git commit -m "style: apply Pint formatting after model audit fixes"
```

(Only if Pint made changes. Skip if clean.)

---

## Deferred Items (Not in Scope)

These issues were identified but are deferred as architectural debt, not bugs:

| Issue | Reason Deferred |
|---|---|
| Service dependencies via `app()` in 8 models (17 calls) | Architectural debt — requires interface extraction + constructor injection refactor. Not a bug. |
| Missing return types on 50+ methods | Type safety improvement — no runtime impact. Can be done incrementally. |
| Missing Builder types on 40+ scopes | Type safety improvement — no runtime impact. |
| 6 orphaned tables | Requires domain analysis to determine if tables are truly unused. |
| Naming inconsistencies (8) | Cosmetic — no functional impact. |
| CurrencyPosition legacy aliases | Intentional — accessors/mutators bridge old/new names. Removing would break callers. |
