# Model Audit Fixes — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix all model-layer issues identified by comprehensive audit: schema mismatches, missing `$hidden`, dead code, inconsistent casting, and trait side effects.

**Architecture:** Fix models in priority order — critical schema mismatches first, then security, then code quality. Each task is self-contained and independently testable.

**Tech Stack:** Laravel 10, PHP 8.3.30, Eloquent ORM, PHPUnit

## Global Constraints

- PHP 8.3.30, Laravel 10
- Run `vendor/bin/pint --dirty --format agent` after each file edit
- Run `php artisan test --compact` to verify no regressions
- Do NOT add any comments to code
- Follow existing project patterns (BaseModel hierarchy, trait-based composition)

---

## Batch 1: Critical Schema Mismatches (T1-T5)

These cause silent runtime failures — writes go to wrong columns or silently drop data.

### Task 1: Fix EddDocumentRequest — `status` not mass-assignable

**Files:**
- Modify: `app/Models/Compliance/EddDocumentRequest.php:20-28`

**Problem:** `status` is in `$casts` (line 31) but NOT in `$fillable` (lines 20-28). Methods `markReceived()`, `verify()`, `reject()` use `$this->update(['status' => ...])` which silently drops the `status` key.

- [ ] **Step 1: Add `status` to `$fillable`**

```php
protected $fillable = [
    'edd_record_id',
    'document_type',
    'file_path',
    'status',
    'rejection_reason',
    'uploaded_at',
    'verified_at',
    'verified_by',
];
```

- [ ] **Step 2: Run tests**

Run: `php artisan test --compact --filter=EddDocument`
Expected: PASS

- [ ] **Step 3: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 4: Commit**

```bash
git add app/Models/Compliance/EddDocumentRequest.php
git commit -m "fix: add status to EddDocumentRequest fillable to fix silent update failures"
```

---

### Task 2: Fix ComplianceCase — phantom relationship and missing enum cast

**Files:**
- Modify: `app/Models/Compliance/ComplianceCase.php:46-55, 222-226`

**Problem 1:** `subject()` relationship (line 222-226) references `linked_type`/`linked_id` columns that don't exist on `compliance_cases` — they exist on `compliance_case_links`. This will throw a runtime error.

**Problem 2:** `resolution` is in `$fillable` and assigned as `$resolution->value` (line 144) but NOT in `$casts`. The `CaseResolution` enum import exists but is unused in `$casts`.

- [ ] **Step 1: Remove the phantom `subject()` relationship**

Delete lines 220-226 (the `subject()` method and its docblock). The relationship references columns that don't exist on this table. If subject access is needed, it should go through the `links()` relationship.

- [ ] **Step 2: Add `resolution` to `$casts`**

```php
protected $casts = [
    'case_type' => ComplianceCaseType::class,
    'status' => ComplianceCaseStatus::class,
    'severity' => FindingSeverity::class,
    'priority' => ComplianceCasePriority::class,
    'resolution' => CaseResolution::class,
    'sla_deadline' => 'datetime',
    'escalated_at' => 'datetime',
    'resolved_at' => 'datetime',
    'metadata' => 'array',
];
```

- [ ] **Step 3: Run tests**

Run: `php artisan test --compact --filter=ComplianceCase`
Expected: PASS

- [ ] **Step 4: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 5: Commit**

```bash
git add app/Models/Compliance/ComplianceCase.php
git commit -m "fix: remove phantom subject() relationship and add resolution enum cast to ComplianceCase"
```

---

### Task 3: Fix CustomerRiskHistory — field name mismatch with migration

**Files:**
- Modify: `app/Models/CustomerRiskHistory.php`

**Problem:** Migration creates `previous_score`, `previous_rating`, `changed_by`, `changed_at` but model uses `old_score`, `old_rating`, `assessed_by`. All writes silently fail.

**Migration columns** (from `2026_04_10_000006_create_customer_tables.php`):
- `previous_rating` (string, nullable)
- `new_rating` (string, nullable)
- `previous_score` (integer, nullable)
- `new_score` (integer, nullable)
- `change_reason` (text, nullable)
- `changed_by` (foreignId → users, nullable)
- `changed_at` (timestamp, useCurrent)

- [ ] **Step 1: Update `$fillable` to match migration**

```php
protected $fillable = [
    'customer_id',
    'previous_score',
    'new_score',
    'previous_rating',
    'new_rating',
    'change_reason',
    'changed_by',
    'changed_at',
];
```

- [ ] **Step 2: Update `$casts` to match migration**

```php
protected $casts = [
    'previous_score' => 'integer',
    'new_score' => 'integer',
    'previous_rating' => RiskRating::class,
    'new_rating' => RiskRating::class,
    'changed_at' => 'datetime',
];
```

- [ ] **Step 3: Update relationship foreign key**

```php
public function assessor(): BelongsTo
{
    return $this->belongsTo(User::class, 'changed_by');
}
```

- [ ] **Step 4: Run tests**

Run: `php artisan test --compact --filter=CustomerRisk`
Expected: PASS

- [ ] **Step 5: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 6: Commit**

```bash
git add app/Models/CustomerRiskHistory.php
git commit -m "fix: align CustomerRiskHistory fillable/casts/relationships with migration schema"
```

---

### Task 4: Fix TransactionImport — field name mismatch with migration

**Files:**
- Modify: `app/Models/TransactionImport.php`

**Problem:** Migration creates `imported_by`, `error_details`, `imported_at`, `file_hash`, `file_size`, `processed_rows` but model uses `user_id`, `errors`, `started_at`. Multiple fields are mismatched.

**Migration columns** (from `2026_04_10_000009_create_auth_tables.php`):
- `filename` (string)
- `original_filename` (string)
- `file_hash` (string, 64)
- `file_size` (unsignedBigInteger)
- `status` (string)
- `total_rows` (unsignedInteger)
- `processed_rows` (unsignedInteger, default 0)
- `success_count` (unsignedInteger)
- `error_count` (unsignedInteger)
- `error_details` (json, nullable)
- `imported_by` (foreignId → users, nullable)
- `imported_at` (timestamp, nullable)
- `completed_at` (timestamp, nullable)

- [ ] **Step 1: Update `$fillable` to match migration**

```php
protected $fillable = [
    'filename',
    'original_filename',
    'file_hash',
    'file_size',
    'status',
    'total_rows',
    'processed_rows',
    'success_count',
    'error_count',
    'error_details',
    'imported_by',
    'imported_at',
    'completed_at',
];
```

- [ ] **Step 2: Update `$casts` to match migration**

```php
protected $casts = [
    'file_size' => 'integer',
    'total_rows' => 'integer',
    'processed_rows' => 'integer',
    'success_count' => 'integer',
    'error_count' => 'integer',
    'error_details' => 'array',
    'imported_at' => 'datetime',
    'completed_at' => 'datetime',
    'status' => TransactionImportStatus::class,
];
```

- [ ] **Step 3: Update relationship foreign key**

Change `user()` to reference `imported_by`:

```php
public function user(): BelongsTo
{
    return $this->belongsTo(User::class, 'imported_by');
}
```

- [ ] **Step 4: Update callers that reference old field names**

Check and update `app/Http/Controllers/TransactionBatchController.php`:
- Line 39: `where('user_id', auth()->id())` → `where('imported_by', auth()->id())`
- Line 80: `'user_id' => auth()->id()` → `'imported_by' => auth()->id()`
- Line 96: `'status' => 'failed'` → use enum: `'status' => TransactionImportStatus::Failed->value`

Also check `app/Services/Transaction/TransactionImportService.php` for references to `errors`, `started_at`, `user_id`.

- [ ] **Step 5: Run tests**

Run: `php artisan test --compact --filter=TransactionImport`
Expected: PASS

- [ ] **Step 6: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 7: Commit**

```bash
git add app/Models/TransactionImport.php app/Http/Controllers/TransactionBatchController.php
git commit -m "fix: align TransactionImport fillable/casts with migration schema"
```

---

### Task 5: Fix CurrencyPosition — field name mismatch with migration

**Files:**
- Modify: `app/Models/CurrencyPosition.php`

**Problem:** Migration creates `quantity`, `average_cost`, `current_rate`, `unrealized_gain_loss`, `last_revalued_at`, `branch_id` but model uses `balance`, `avg_cost_rate`, `last_valuation_rate`, `unrealized_pnl`, `last_valuation_at`, `till_id`. Every attribute access fails.

**Migration columns** (from `2026_04_10_000007_create_position_and_str_tables.php`):
- `currency_code` (string, 3)
- `branch_id` (string, 50, default 'HQ')
- `quantity` (decimal 18,4)
- `average_cost` (decimal 18,6)
- `total_cost` (decimal 18,4)
- `current_rate` (decimal 18,6)
- `current_value` (decimal 18,4)
- `unrealized_gain_loss` (decimal 18,4)
- `last_revalued_at` (timestamp, nullable)

Note: No `till_id` column exists. The model and services reference `till_id` but the migration uses `branch_id`.

- [ ] **Step 1: Update `$fillable` to match migration**

```php
protected $fillable = [
    'currency_code',
    'branch_id',
    'quantity',
    'average_cost',
    'total_cost',
    'current_rate',
    'current_value',
    'unrealized_gain_loss',
    'last_revalued_at',
];
```

- [ ] **Step 2: Update `$casts` to match migration**

```php
protected $casts = [
    'quantity' => MoneyCast::class,
    'average_cost' => MoneyCast::class.':6',
    'total_cost' => MoneyCast::class,
    'current_rate' => MoneyCast::class.':6',
    'current_value' => MoneyCast::class,
    'unrealized_gain_loss' => MoneyCast::class,
    'last_revalued_at' => 'datetime',
];
```

- [ ] **Step 3: Update computed attribute accessors to use migration column names**

```php
public function getMarketValueAttribute(): string
{
    $rate = $this->current_rate;

    if (! $rate || $this->mathService->compare($rate, '0') === 0) {
        $rate = $this->average_cost;
    }

    if (! $rate || $this->mathService->compare($rate, '0') === 0) {
        return '0';
    }

    return $this->mathService->multiply($this->quantity, $rate);
}
```

- [ ] **Step 4: Update `CurrencyPositionService` to use migration column names**

In `app/Services/Accounting/CurrencyPositionService.php`, update all references:
- `balance` → `quantity`
- `avg_cost_rate` → `average_cost`
- `last_valuation_rate` → `current_rate`
- `unrealized_pnl` → `unrealized_gain_loss`
- `last_valuation_at` → `last_revalued_at`
- `till_id` → `branch_id`

- [ ] **Step 5: Update other services referencing old column names**

Check and update:
- `app/Services/Accounting/RevaluationService.php`
- `app/Services/Transaction/TransactionReversalService.php`
- `app/Services/Transaction/TransactionService.php`
- `app/Services/Reporting/ReportingService.php`
- `app/Http/Controllers/RevaluationController.php`
- `app/Http/Controllers/Report/AnalyticsController.php`

- [ ] **Step 6: Run tests**

Run: `php artisan test --compact --filter=CurrencyPosition`
Expected: PASS

- [ ] **Step 7: Run full test suite**

Run: `php artisan test --compact`
Expected: PASS

- [ ] **Step 8: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 9: Commit**

```bash
git add app/Models/CurrencyPosition.php app/Services/Accounting/CurrencyPositionService.php app/Services/Accounting/RevaluationService.php app/Services/Transaction/TransactionReversalService.php app/Services/Transaction/TransactionService.php app/Services/Reporting/ReportingService.php app/Http/Controllers/RevaluationController.php app/Http/Controllers/Report/AnalyticsController.php
git commit -m "fix: align CurrencyPosition and all dependent services with migration schema"
```

---

## Batch 2: Security — Missing `$hidden` (T6-T8)

These expose sensitive data when models are serialized (API responses, Blade views).

### Task 6: Fix User model — password hash leak and dead config

**Files:**
- Modify: `app/Models/User.php:45-70, 229-238`

**Problem 1:** `password` accessor (line 98) returns `password_hash` — when model is serialized via `toArray()`, `$user->password` leaks the hash. `password` is in `$fillable` but not in `$hidden`.

**Problem 2:** Both `$fillable` and `$guarded` are set (lines 45-60). `$guarded` is dead config since none of its fields are in `$fillable`.

**Problem 3:** `isMfaSessionExpired()` (line 229-238) always returns `false` — dead method.

- [ ] **Step 1: Add `password` to `$hidden`**

```php
protected $hidden = [
    'password',
    'password_hash',
    'mfa_secret',
];
```

- [ ] **Step 2: Remove dead `$guarded`**

Delete lines 55-60 (the `$guarded` property).

- [ ] **Step 3: Remove dead `isMfaSessionExpired()` method**

Delete lines 226-238 (the method and its docblock).

- [ ] **Step 4: Run tests**

Run: `php artisan test --compact --filter=User`
Expected: PASS

- [ ] **Step 5: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 6: Commit**

```bash
git add app/Models/User.php
git commit -m "fix: hide password hash in User serialization, remove dead guarded config and stub method"
```

---

### Task 7: Fix MfaRecoveryCode — code_hash not hidden

**Files:**
- Modify: `app/Models/MfaRecoveryCode.php`

- [ ] **Step 1: Read the file and add `code_hash` to `$hidden`**

```php
protected $hidden = [
    'code_hash',
];
```

- [ ] **Step 2: Run tests**

Run: `php artisan test --compact --filter=Mfa`
Expected: PASS

- [ ] **Step 3: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 4: Commit**

```bash
git add app/Models/MfaRecoveryCode.php
git commit -m "fix: hide code_hash in MfaRecoveryCode serialization"
```

---

### Task 8: Fix Customer and CustomerRelation — encrypted IDs not hidden

**Files:**
- Modify: `app/Models/Customer.php`
- Modify: `app/Models/CustomerRelation.php`
- Modify: `app/Models/DeviceComputations.php`

- [ ] **Step 1: Add `id_number_encrypted` to Customer `$hidden`**

```php
protected $hidden = [
    'id_number_encrypted',
];
```

- [ ] **Step 2: Add `id_number_encrypted` to CustomerRelation `$hidden`**

```php
protected $hidden = [
    'id_number_encrypted',
];
```

- [ ] **Step 3: Add `device_fingerprint` to DeviceComputations `$hidden`**

```php
protected $hidden = [
    'device_fingerprint',
];
```

- [ ] **Step 4: Run tests**

Run: `php artisan test --compact --filter="Customer|Device"`
Expected: PASS

- [ ] **Step 5: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 6: Commit**

```bash
git add app/Models/Customer.php app/Models/CustomerRelation.php app/Models/DeviceComputations.php
git commit -m "fix: hide sensitive fields in Customer, CustomerRelation, and DeviceComputations"
```

---

## Batch 3: Dead Code & Unused Imports (T9-T11)

### Task 9: Remove unused `Model` imports from 10 files

**Files:**
- Modify: `app/Models/AmlRule.php:10`
- Modify: `app/Models/AccountingPeriod.php:11`
- Modify: `app/Models/ChartOfAccount.php:8`
- Modify: `app/Models/FiscalYear.php:8`
- Modify: `app/Models/Department.php:8`
- Modify: `app/Models/CostCenter.php:7`
- Modify: `app/Models/PepApprovalRequest.php:9`
- Modify: `app/Models/BackupLog.php:9`
- Modify: `app/Models/TransactionError.php:7`
- Modify: `app/Models/UserNotificationPreference.php:7`

**Problem:** Each file imports `use Illuminate\Database\Eloquent\Model;` but extends `BaseModel`, not `Model`. The import is unused.

- [ ] **Step 1: Remove the unused import from each file**

Delete `use Illuminate\Database\Eloquent\Model;` from all 10 files.

- [ ] **Step 2: Run tests**

Run: `php artisan test --compact`
Expected: PASS

- [ ] **Step 3: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 4: Commit**

```bash
git add app/Models/AmlRule.php app/Models/AccountingPeriod.php app/Models/ChartOfAccount.php app/Models/FiscalYear.php app/Models/Department.php app/Models/CostCenter.php app/Models/PepApprovalRequest.php app/Models/BackupLog.php app/Models/TransactionError.php app/Models/UserNotificationPreference.php
git commit -m "fix: remove unused Model imports from 10 model files"
```

---

### Task 10: Remove dead code — no-op accessors, deprecated $dates, redundant $with

**Files:**
- Modify: `app/Models/SanctionEntry.php:16, 61-64`
- Modify: `app/Models/BackupLog.php:50-54`
- Modify: `app/Models/Transaction.php:54`
- Modify: `app/Models/JournalEntry.php:58`
- Modify: `app/Models/Customer.php:52`
- Modify: `app/Models/CurrencyPosition.php:130-133`

- [ ] **Step 1: Remove no-op `getDetailsAttribute()` from SanctionEntry**

Delete lines 61-64 (the accessor method).

- [ ] **Step 2: Remove empty `$with = []` from SanctionEntry**

Delete line 16 (`protected $with = [];`).

- [ ] **Step 3: Remove deprecated `$dates` from BackupLog**

Delete lines 50-54 (the `$dates` property). The same columns are already in `$casts` as `'datetime'`.

- [ ] **Step 4: Remove redundant `$with = []` from Transaction, JournalEntry, Customer**

Delete `protected $with = [];` from each file (it's the default).

- [ ] **Step 5: Run tests**

Run: `php artisan test --compact`
Expected: PASS

- [ ] **Step 6: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 7: Commit**

```bash
git add app/Models/SanctionEntry.php app/Models/BackupLog.php app/Models/Transaction.php app/Models/JournalEntry.php app/Models/Customer.php
git commit -m "fix: remove dead code — no-op accessors, deprecated $dates, redundant $with declarations"
```

---

### Task 11: Remove unused HasReferenceNumber trait

**Files:**
- Delete: `app/Models/Traits/HasReferenceNumber.php`

**Problem:** No model in the codebase uses this trait. `ComplianceCase` reimplements the same logic inline.

- [ ] **Step 1: Verify no references exist**

Run: `grep -r "HasReferenceNumber" app/`
Expected: Only the trait file itself.

- [ ] **Step 2: Delete the trait file**

```bash
rm app/Models/Traits/HasReferenceNumber.php
```

- [ ] **Step 3: Run tests**

Run: `php artisan test --compact`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add -A app/Models/Traits/HasReferenceNumber.php
git commit -m "fix: remove unused HasReferenceNumber trait"
```

---

## Batch 4: Inconsistent Casting (T12-T14)

### Task 12: Standardize monetary casting to MoneyCast

**Files:**
- Modify: `app/Models/BankReconciliation.php:34-35`
- Modify: `app/Models/Budget.php:21-23`
- Modify: `app/Models/StockTransferItem.php:25-31`
- Modify: `app/Models/RevaluationEntry.php:18-25`
- Modify: `app/Models/SanctionsAnalysis.php:22-25`
- Modify: `app/Models/CounterHandover.php:29`
- Modify: `app/Models/BranchPool.php:20-23`
- Modify: `app/Models/StockReservation.php:23-27`

**Problem:** These models use `'decimal:N'` for monetary columns while other models use `MoneyCast::class`. Inconsistent.

- [ ] **Step 1: Update each model's `$casts` to use `MoneyCast::class`**

For each model, replace `'column_name' => 'decimal:N'` with `'column_name' => MoneyCast::class` (or `MoneyCast::class.':N'` for rate columns with 6 decimals).

Add `use App\Casts\MoneyCast;` import to each file.

- [ ] **Step 2: Run tests**

Run: `php artisan test --compact`
Expected: PASS

- [ ] **Step 3: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 4: Commit**

```bash
git add app/Models/BankReconciliation.php app/Models/Budget.php app/Models/StockTransferItem.php app/Models/RevaluationEntry.php app/Models/SanctionsAnalysis.php app/Models/CounterHandover.php app/Models/BranchPool.php app/Models/StockReservation.php
git commit -m "fix: standardize monetary casting to MoneyCast across 8 models"
```

---

### Task 13: Add missing JSON casts to EnhancedDiligenceRecord

**Files:**
- Modify: `app/Models/EnhancedDiligenceRecord.php`

**Problem:** Migration creates `responses` (JSON) and `documents_received` (JSON) columns but model has no casts for them.

- [ ] **Step 1: Add missing casts**

Add to `$casts`:
```php
'responses' => 'array',
'documents_received' => 'array',
```

- [ ] **Step 2: Run tests**

Run: `php artisan test --compact --filter=EnhancedDiligence`
Expected: PASS

- [ ] **Step 3: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 4: Commit**

```bash
git add app/Models/EnhancedDiligenceRecord.php
git commit -m "fix: add missing JSON casts for responses and documents_received in EnhancedDiligenceRecord"
```

---

### Task 14: Fix Transaction — missing boolean cast for is_refund

**Files:**
- Modify: `app/Models/Transaction.php:65-97`

**Problem:** `is_refund` is in `$fillable` (line 88) but not in `$casts`. Stored/retrieved as raw string instead of boolean.

- [ ] **Step 1: Add `is_refund` to `$casts`**

```php
'is_refund' => 'boolean',
```

- [ ] **Step 2: Run tests**

Run: `php artisan test --compact --filter=Transaction`
Expected: PASS

- [ ] **Step 3: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 4: Commit**

```bash
git add app/Models/Transaction.php
git commit -m "fix: add boolean cast for is_refund in Transaction model"
```

---

## Batch 5: Trait & Base Class Cleanup (T15-T16)

### Task 15: Remove deprecated string constants alongside enums

**Files:**
- Modify: `app/Models/SystemAlert.php:33-37`
- Modify: `app/Models/SystemHealthCheck.php:36-40`
- Modify: `app/Models/BackupLog.php:59-82`

**Problem:** These models define raw string constants (`LEVEL_INFO`, `STATUS_OK`, `STATUS_PENDING`, etc.) that duplicate enum values. The enums are already cast. The constants create confusion about which to use.

- [ ] **Step 1: Remove string constants from SystemAlert**

Delete `LEVEL_INFO`, `LEVEL_WARNING`, `LEVEL_CRITICAL` constants. Update `getStatusColorClass()` to use the enum instead.

- [ ] **Step 2: Remove string constants from SystemHealthCheck**

Delete `STATUS_OK`, `STATUS_WARNING`, `STATUS_CRITICAL` constants. Update any methods using them to use the enum.

- [ ] **Step 3: Remove string constants from BackupLog**

Delete `STATUS_PENDING`, `STATUS_RUNNING`, `STATUS_COMPLETED`, `STATUS_FAILED`, `STATUS_VERIFIED`, `STATUS_VERIFICATION_FAILED`, `TYPE_*`, `DISK_*` constants. Update any methods using them to use the enum.

- [ ] **Step 4: Run tests**

Run: `php artisan test --compact --filter="SystemAlert|SystemHealthCheck|BackupLog"`
Expected: PASS

- [ ] **Step 5: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 6: Commit**

```bash
git add app/Models/SystemAlert.php app/Models/SystemHealthCheck.php app/Models/BackupLog.php
git commit -m "fix: remove redundant string constants alongside enums in SystemAlert, SystemHealthCheck, BackupLog"
```

---

### Task 16: Remove SanctionEntry setEntityTypeAttribute ucfirst misuse

**Files:**
- Modify: `app/Models/SanctionEntry.php:66-78`

**Problem:** `setEntityTypeAttribute` applies `ucfirst()` which capitalizes the first letter (e.g., `'organization'` → `'Organization'`). If the enum value is lowercase, this breaks comparisons. `setDetailsAttribute` also silently nullifies non-string data.

- [ ] **Step 1: Fix `setEntityTypeAttribute` to store raw value**

```php
public function setEntityTypeAttribute($value): void
{
    $this->attributes['entity_type'] = $value;
}
```

- [ ] **Step 2: Fix `setDetailsAttribute` to handle arrays**

```php
public function setDetailsAttribute($value): void
{
    $this->attributes['details'] = is_string($value) ? $value : (is_array($value) ? json_encode($value) : null);
}
```

- [ ] **Step 3: Run tests**

Run: `php artisan test --compact --filter=SanctionEntry`
Expected: PASS

- [ ] **Step 4: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 5: Commit**

```bash
git add app/Models/SanctionEntry.php
git commit -m "fix: fix SanctionEntry mutators — remove ucfirst misuse and handle array details"
```

---

## Batch 6: Code Quality (T17-T19)

### Task 17: Add return types to relationship methods (batch 1 — core models)

**Files:**
- Modify: `app/Models/Transaction.php:146-196`
- Modify: `app/Models/Customer.php:120-223`
- Modify: `app/Models/User.php:118-131`
- Modify: `app/Models/Counter.php:40-45`
- Modify: `app/Models/CounterSession.php:36-61`
- Modify: `app/Models/TillBalance.php:47-57`
- Modify: `app/Models/CounterHandover.php:39-54`

**Problem:** ~30 relationship methods lack native PHP return type declarations.

- [ ] **Step 1: Add `BelongsTo`, `HasMany`, `HasOne` return types to each relationship method**

Import the relation classes and add return types. Example:

```php
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

public function flags(): HasMany
{
    return $this->hasMany(FlaggedTransaction::class);
}

public function canceller(): BelongsTo
{
    return $this->belongsTo(User::class, 'cancelled_by');
}
```

- [ ] **Step 2: Run tests**

Run: `php artisan test --compact`
Expected: PASS

- [ ] **Step 3: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 4: Commit**

```bash
git add app/Models/Transaction.php app/Models/Customer.php app/Models/User.php app/Models/Counter.php app/Models/CounterSession.php app/Models/TillBalance.php app/Models/CounterHandover.php
git commit -m "fix: add return types to relationship methods in core models"
```

---

### Task 18: Add return types to relationship methods (batch 2 — compliance & financial models)

**Files:**
- Modify: `app/Models/FlaggedTransaction.php:33-48`
- Modify: `app/Models/SystemAlert.php:119`
- Modify: `app/Models/SystemLog.php:94`
- Modify: `app/Models/ThresholdAudit.php:25`
- Modify: `app/Models/CustomerRiskHistory.php:28-33`
- Modify: `app/Models/RevaluationEntry.php:27-32`
- Modify: `app/Models/SanctionList.php:63-68`
- Modify: `app/Models/ExchangeRate.php:26-31`
- Modify: `app/Models/ExchangeRateHistory.php:25-35`
- Modify: `app/Models/CurrencyPosition.php:41-44`
- Modify: `app/Models/TransactionImport.php:35`

- [ ] **Step 1: Add `BelongsTo` return types to each relationship method**

- [ ] **Step 2: Run tests**

Run: `php artisan test --compact`
Expected: PASS

- [ ] **Step 3: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 4: Commit**

```bash
git add app/Models/FlaggedTransaction.php app/Models/SystemAlert.php app/Models/SystemLog.php app/Models/ThresholdAudit.php app/Models/CustomerRiskHistory.php app/Models/RevaluationEntry.php app/Models/SanctionList.php app/Models/ExchangeRate.php app/Models/ExchangeRateHistory.php app/Models/CurrencyPosition.php app/Models/TransactionImport.php
git commit -m "fix: add return types to relationship methods in compliance and financial models"
```

---

### Task 19: Add Builder type hints to scope parameters (batch)

**Files:**
- Modify: `app/Models/Compliance/ComplianceCase.php:261-281`
- Modify: `app/Models/Compliance/ComplianceFinding.php:126-150`
- Modify: `app/Models/Compliance/EddQuestionnaireTemplate.php:25`
- Modify: `app/Models/SanctionList.php:73-78`
- Modify: `app/Models/EddTemplate.php:41-46`
- Modify: `app/Models/Alert.php:58-73`
- Modify: `app/Models/RiskScoreSnapshot.php:48-53`
- Modify: `app/Models/ThresholdAudit.php:30-35`
- Modify: `app/Models/CustomerDocument.php:109-142`
- Modify: `app/Models/AmlRule.php:92-104`
- Modify: `app/Models/TransactionImport.php:43-53`
- Modify: `app/Models/StockTransfer.php:74-87`
- Modify: `app/Models/Branch.php:159-192`

**Problem:** 40+ scope methods have untyped `$query` parameters.

- [ ] **Step 1: Add `Builder` type hints to all scope parameters**

Add `use Illuminate\Database\Eloquent\Builder;` import and type the `$query` parameter:

```php
public function scopeUnderReview(Builder $query): Builder
{
    return $query->where('status', ComplianceCaseStatus::UnderReview->value);
}
```

- [ ] **Step 2: Run tests**

Run: `php artisan test --compact`
Expected: PASS

- [ ] **Step 3: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 4: Commit**

```bash
git add app/Models/Compliance/ComplianceCase.php app/Models/Compliance/ComplianceFinding.php app/Models/Compliance/EddQuestionnaireTemplate.php app/Models/SanctionList.php app/Models/EddTemplate.php app/Models/Alert.php app/Models/RiskScoreSnapshot.php app/Models/ThresholdAudit.php app/Models/CustomerDocument.php app/Models/AmlRule.php app/Models/TransactionImport.php app/Models/StockTransfer.php app/Models/Branch.php
git commit -m "fix: add Builder type hints to scope parameters across 13 models"
```

---

## Execution Summary

| Batch | Tasks | Focus | Risk |
|-------|-------|-------|------|
| 1 | T1-T5 | Critical schema mismatches | HIGH — fixes runtime bugs |
| 2 | T6-T8 | Security — missing `$hidden` | MEDIUM — data exposure |
| 3 | T9-T11 | Dead code & unused imports | LOW — cleanup |
| 4 | T12-T14 | Inconsistent casting | LOW — consistency |
| 5 | T15-T16 | Trait & base class cleanup | LOW — cleanup |
| 6 | T17-T19 | Code quality — type hints | LOW — developer experience |

**Total: 19 tasks across 6 batches**
