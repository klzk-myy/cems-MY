# Deferred Items Implementation Plan

**Date**: 2026-06-23
**Status**: Ready for execution
**Estimated Effort**: 8 tasks across 3 phases

---

## Overview

Three items were deferred from the original audit as non-critical improvements requiring more design work. This plan addresses them with a structured, test-driven approach.

---

## Phase 1: Extract AmlRule Business Logic (T5.4)

**Goal**: Move evaluation logic from AmlRule model into a dedicated service, improving testability and separation of concerns.

### Problem

`AmlRule::evaluate()` contains 5 protected methods (~120 lines) implementing business rules:
- `evaluateVelocity()` — transaction count/amount in time window
- `evaluateStructuring()` — structuring detection
- `evaluateAmountThreshold()` — single transaction threshold
- `evaluateFrequency()` — rapid transaction detection
- `evaluateGeographic()` — high-risk country matching

These methods also directly query `Transaction::where(...)`, bypassing the service layer.

### Task 1.1: Create `AmlRuleEvaluator` Service

**File**: `app/Services/Compliance/AmlRuleEvaluator.php`

**Responsibilities**:
- Extract all 5 `evaluate*` methods from AmlRule into standalone methods
- Accept `MathService` via constructor injection (not `app()`)
- Accept `Transaction` query builder via dependency injection for testability

**Interface**:
```php
class AmlRuleEvaluator
{
    public function __construct(
        protected MathService $mathService
    ) {}

    public function evaluate(Transaction $transaction, AmlRule $rule): array
    {
        // Delegates to type-specific methods
    }

    protected function evaluateVelocity(Transaction $transaction, array $conditions): bool { ... }
    protected function evaluateStructuring(Transaction $transaction, array $conditions): bool { ... }
    protected function evaluateAmountThreshold(Transaction $transaction, array $conditions): bool { ... }
    protected function evaluateFrequency(Transaction $transaction, array $conditions): bool { ... }
    protected function evaluateGeographic(Transaction $transaction, array $conditions): bool { ... }
}
```

### Task 1.2: Refactor AmlRule Model

**File**: `app/Models/AmlRule.php`

**Changes**:
- Remove `protected MathService $mathService` property and `__construct` override
- Remove all 5 `evaluate*` methods
- Simplify `evaluate()` to delegate to `AmlRuleEvaluator`
- Keep `isApplicableTo()` on the model (it's a query-scope helper, not business logic)

**Before**:
```php
public function evaluate(Transaction $transaction): array
{
    // 50 lines of match + delegation
}

protected function evaluateVelocity(...) { ... } // 25 lines
protected function evaluateStructuring(...) { ... } // 25 lines
// etc.
```

**After**:
```php
public function evaluate(Transaction $transaction): array
{
    return app(AmlRuleEvaluator::class)->evaluate($transaction, $this);
}
```

### Task 1.3: Write Tests for `AmlRuleEvaluator`

**File**: `tests/Unit/Services/AmlRuleEvaluatorTest.php`

**Test cases**:
- `test_velocity_rule_triggers_on_high_frequency`
- `test_velocity_rule_with_cumulative_threshold`
- `test_structuring_rule_detects_split_transactions`
- `test_amount_threshold_triggers_on_large_transaction`
- `test_amount_threshold_ignores_different_currency`
- `test_frequency_rule_triggers_on_rapid_transactions`
- `test_geographic_rule_matches_high_risk_country`
- `test_geographic_rule_ignores_unknown_customer`
- `test_inactive_rule_never_triggers`
- `test_evaluation_error_returns_false_safely`

---

## Phase 2: Add SoftDeletes to Compliance/Financial Models (T5.3)

**Goal**: Enable soft deletes for compliance and financial models that reference transactions, preserving audit trails.

### Problem

Core tables (customers, transactions, users, branches, currencies, counters) already have SoftDeletes. However, compliance models (ComplianceCase, Alert, FlaggedTransaction, SanctionEntry, etc.) lack SoftDeletes, meaning accidental deletion would destroy audit-critical data.

### Task 2.1: Create Migration for Compliance Model SoftDeletes

**File**: `database/migrations/2026_06_23_000001_add_soft_deletes_to_compliance_tables.php`

**Tables to add `deleted_at` + index**:
- `compliance_cases`
- `compliance_findings`
- `compliance_case_documents`
- `compliance_case_notes`
- `compliance_case_links`
- `alerts`
- `flagged_transactions`
- `sanction_entries`
- `sanction_lists`
- `screening_results`
- `risk_score_snapshots`

**Pattern** (same as existing migration):
```php
Schema::table('compliance_cases', function (Blueprint $table) {
    if (!Schema::hasColumn('compliance_cases', 'deleted_at')) {
        $table->softDeletes();
        $table->index('deleted_at');
    }
});
```

### Task 2.2: Add `SoftDeletes` Trait to Compliance Models

**Files** (add `use SoftDeletes;` to each):
- `app/Models/Compliance/ComplianceCase.php`
- `app/Models/Compliance/ComplianceFinding.php`
- `app/Models/Compliance/ComplianceCaseDocument.php`
- `app/Models/Compliance/ComplianceCaseNote.php`
- `app/Models/Compliance/ComplianceCaseLink.php`
- `app/Models/Alert.php`
- `app/Models/FlaggedTransaction.php`
- `app/Models/SanctionEntry.php`
- `app/Models/SanctionList.php`
- `app/Models/ScreeningResult.php`
- `app/Models/RiskScoreSnapshot.php`

### Task 2.3: Write Migration Test

**File**: `tests/Feature/Migrations/ComplianceSoftDeletesMigrationTest.php`

**Test cases**:
- `test_compliance_cases_table_has_deleted_at_column`
- `test_alerts_table_has_deleted_at_column`
- `test_flagged_transactions_table_has_deleted_at_column`
- `test_sanction_entries_table_has_deleted_at_column`
- `test_soft_deleted_records_are_excluded_by_default`

---

## Phase 3: Extract TransactionConfirmationService (T4.3)

**Goal**: Move remaining confirmation logic from controller into service.

### Problem

`TransactionApprovalController::showConfirm()` contains:
- Threshold check (`requiresConfirmation`)
- Token generation (`bin2hex(random_bytes(32))`)
- Confirmation record creation
- Audit logging

These should live in the service layer.

### Task 3.1: Enhance `TransactionConfirmationService`

**File**: `app/Services/Transaction/TransactionConfirmationService.php`

**Add methods**:
```php
public function requestConfirmation(Transaction $transaction, int $userId): array
{
    // 1. Check if confirmation required (threshold check)
    // 2. Find existing pending/confirmed confirmation
    // 3. Create new confirmation if none exists
    // 4. Log audit event
    // Return ['confirmation' => $confirmation, 'created' => bool]
}

public function requiresConfirmation(Transaction $transaction): bool
{
    // Delegate to ThresholdService + MathService
}
```

**Dependencies to inject**:
- `ThresholdService` (for `getStrThreshold()`)
- `MathService` (for `compare()`)
- `AuditService` (already present)

### Task 3.2: Refactor TransactionApprovalController

**File**: `app/Http/Controllers/Transaction/TransactionApprovalController.php`

**Changes**:
- Remove `requiresConfirmation()` method (moved to service)
- Remove threshold/math service injections (no longer needed directly)
- Simplify `showConfirm()` to delegate to service:
  ```php
  public function showConfirm(Transaction $transaction): View|RedirectResponse
  {
      $result = $this->confirmationService->requestConfirmation($transaction, auth()->id());
      
      if (!$result['requires_confirmation']) {
          return redirect()->route('transactions.show', $transaction)
              ->with('error', 'This transaction does not require confirmation.');
      }
      
      $transaction->load(['customer', 'user']);
      return view('transactions.confirm', [
          'transaction' => $transaction,
          'confirmation' => $result['confirmation'],
      ]);
  }
  ```

### Task 3.3: Write Tests for Confirmation Service

**File**: `tests/Unit/Services/TransactionConfirmationServiceTest.php`

**Test cases**:
- `test_request_confirmation_creates_new_confirmation_when_none_exists`
- `test_request_confirmation_returns_existing_pending_confirmation`
- `test_requires_confirmation_returns_true_above_threshold`
- `test_requires_confirmation_returns_false_below_threshold`
- `test_confirm_processes_confirm_action`
- `test_confirm_processes_reject_action`
- `test_confirm_rejects_expired_confirmation`

---

## Execution Order

1. **Phase 1** (AmlRule extraction) — no dependencies
2. **Phase 2** (SoftDeletes) — no dependencies, can run in parallel with Phase 1
3. **Phase 3** (Confirmation extraction) — depends on existing service, no external dependencies

## Risk Assessment

| Phase | Risk | Mitigation |
|-------|------|------------|
| Phase 1 | Breaking existing rule evaluation | Keep `AmlRule::evaluate()` as facade; existing tests cover evaluation |
| Phase 2 | Migration failure on existing data | Use `hasColumn()` guard; nullable column |
| Phase 3 | Confirmation flow regression | Existing confirmation tests; add new tests before refactoring |

## Verification

After all phases:
```bash
php artisan test --compact
vendor/bin/pint --dirty --format agent
```

All existing tests must continue to pass. New tests must cover the extracted logic.
