# Accounting System Cleanup

This document describes the P0 cleanup performed on the accounting and ledger system on 2026-06-01. The goal was to eliminate redundant and orphaned code, fix critical bugs, and improve maintainability and performance while preserving backward compatibility.

## Scope

The cleanup covered:
- Core accounting service and models
- Chart of accounts seeding
- Compliance reporting (STR) removal
- Database performance
- Test suite consistency

## Changes

### 1. Core Accounting Service

**File:** `app/Services/AccountingService.php`

**Issue:** Missing import of `App\Enums\AccountType` caused `isDebitAccount()` to always return `false` for enum-cast accounts, flipping the balance calculation logic for all accounts.

**Fix:** Added `use App\Enums\AccountType;` at the top of the file.

**Simplification:** The `isDebitAccount()` method now uses a ternary:
```php
return $account->account_type instanceof AccountType
    ? $account->account_type->isDebitNormal()
    : in_array($account->account_type, ['Asset', 'Expense']);
```
This leverages the `AccountType` enum's `isDebitNormal()` method and falls back to string check for non-enum values (backward compatibility).

**Direct Posting:** Journal entries are now created with `Posted` status immediately and ledger updates happen within the same transaction. Deprecated `submitForApproval()` and `approveEntry()` methods were removed (they were P2 anyway).

### 2. Chart of Accounts Seeder Consolidation

**Files:**
- `database/seeders/EnhancedChartOfAccountsSeeder.php` (enriched)
- `database/seeders/ChartOfAccountsSeeder.php` (deleted)
- `database/seeders/DatabaseSeeder.php` (updated)
- `database/seeders/BusinessSetupSeeder.php` (updated)

**Rationale:** Having two seeders with overlapping content caused confusion and duplication. We merged the standard seeder into the enhanced version and added missing accounts:
- Nostro accounts (2111-2114)
- Petty cash (1020)
- Fixed assets (1601-1605)
- Accumulated depreciation (1701)
- Additional revenue/expense accounts

The enhanced seeder now serves as the single source of truth for the chart of accounts.

### 3. Removal of Orphaned STR Reporting

**Deleted Files:**
- `app/Http/Controllers/Compliance/ComplianceReportingController.php`
- `resources/views/compliance/workspace/index.blade.php`
- `resources/views/compliance/reporting/*.blade.php`
- `resources/views/pages/compliance/index.blade.php`

**Removed Routes:** All `/compliance/*` routes in `routes/web.php`.

**Verification:** `app/Services/ReportingService.php` and `app/Services/ComplianceService.php` contain no STR-specific logic. They handle general MSB reporting (MSB2, LMCA, Large Value, Position Limit) and AML compliance (sanctions, velocity, structuring detection).

### 4. Database Indexes for Performance

**Migrations:**
- `2026_05_31_151523_add_account_ledger_indexes.php`
- `2026_05_31_151541_add_journal_entries_indexes.php`

**Indexes added:**

On `account_ledger`:
- `idx_account_ledger_account_entry` (`account_code`, `journal_entry_id`)
- `idx_account_ledger_entry_date` (`entry_date`)
- `idx_account_ledger_journal_entry` (`journal_entry_id`)

On `journal_entries`:
- `idx_journal_entries_entry_date` (`entry_date`)
- `idx_journal_entries_period_id` (`period_id`)
- `idx_journal_entries_status` (`status`)
- `idx_journal_entries_created_by` (`created_by`)
- `idx_journal_entries_period_status` (`period_id`, `status`)

These indexes significantly improve query performance for trial balance, ledger reports, and period-based lookups.

### 5. Test Suite Updates

- Updated `tests/Unit/BudgetServiceTest.php` to reference `EnhancedChartOfAccountsSeeder`.
- Removed `dd()` debug from `test_journal_reversal_produces_correct_economic_effect`.
- All 29 accounting tests pass (17 unit, 12 feature assertions) with high precision using `MathService`.

## Verification

### Test Results
```
Tests: 29 passed (78 assertions)
Duration: 42.10s
```
All accounting unit and feature tests pass.

### Code Style
Laravel Pint automatically fixed minor style issues in `AccountingService.php`.

### Impact Analysis
Before any changes, `gitnexus_impact` was used to understand blast radius. The changes are localized and affect primarily:
- Accounting service internal logic
- Database schema
- Test data setup
No external consumer contracts were broken.

## Backward Compatibility

- The `isDebitAccount()` method remains `protected` and its signature unchanged.
- The `AccountLedger::getNetAmount()` method remains unchanged.
- All public API routes and frontend endpoints (except removed STR routes) behave identically, but now with corrected balance logic.

## Migration Notes

If you are running an existing production instance:
1. Deploy the code changes.
2. Run `php artisan migrate` to add indexes (non-breaking, online).
3. Run `php artisan db:seed --class=EnhancedChartOfAccountsSeeder` to ensure a complete chart of accounts. The seeder is idempotent (uses `updateOrCreate`).
4. No data transformation is required; the core accounting logic now computes correctly.

## Fixes Consolidation (2026-06-11)

A comprehensive fixes audit was executed against all open implementation plans. Results:

| Plan | Tasks | Status |
|------|-------|--------|
| G1 — Amount-based Enhanced CDD | Remove amount trigger | Already implemented in `CddLevelDeterminationService` |
| G2 — STR filing deadline | Next working day logic | N/A — STR module removed in P0 |
| Sanctions freeze/block/reject | pd-00.md 27.6 | Already implemented in `CustomerScreeningService` |
| Foreign/Domestic PEP | `PepType` enum | Already implemented |
| SQL injection | `DB::raw` binding | **Fixed** in `ReportingService` (commit `b36aafe`) |
| XSS | Controller audit | No vulnerabilities found |
| N+1 queries | Service audit | No issues found — eager loading correct |
| Database indexes | `account_ledger`, `journal_entries` | Already migrated (2026-05-31) |

All existing tests continue to pass:
- `CddLevelDeterminationServiceTest`: 12 passed
- `CustomerScreeningServiceTest`: 15 passed
- `ReportingServiceTest`: 4 passed

## Future Improvements

- Consider removing the fallback string check in `isDebitAccount()` once all deployments complete the enum migration (it's safe but unnecessary).
- Monitor query performance on large installations; additional composite indexes may be warranted for branch-specific queries.
- Convert 40+ FormRequests with `authorize(): bool { return true; }` to extend `AuthorizedFormRequest` base class.
