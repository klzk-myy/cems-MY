# P2 Accounting System Cleanup Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Perform systematic cleanup of redundant and orphaned code in the accounting/ledger system, eliminate dead code, and fix identified bugs to improve maintainability and production readiness.

**Architecture:** This plan focuses on refactoring and cleanup rather than adding features. Changes include removing dead code, consolidating duplicate logic, deleting orphaned views, adding database constraints, and improving performance via indexes. All changes are backward-compatible and isolated to specific modules.

**Tech Stack:** Laravel 10, PHP 8.3, MySQL, GitNexus for impact analysis

---

### Task 1: Remove Dead Approval Methods from AccountingService

**Files:**
- Modify: `app/Services/AccountingService.php:145-234` (remove `submitForApproval` and `approveEntry`)
- Test: `tests/Unit/AccountingServiceTest.php` (verify no tests call these methods)

**Current State:** The `submitForApproval()` and `approveEntry()` methods are now unused since `createJournalEntry` posts directly. They remain in the service but are dead code.

**Step 1: Verify no external callers exist**

```bash
grep -r "submitForApproval\|approveEntry" app/ --include="*.php" | grep -v "function submitForApproval\|function approveEntry"
```

Expected: No matches (only the method definitions themselves).

**Step 2: Add @deprecated annotations before removal**

Modify `app/Services/AccountingService.php`:

```php
    /**
     * Submit a journal entry for approval.
     *
     * @deprecated Since 2026-05-31 - Direct posting is now used. This method will be removed in v11.
     */
    public function submitForApproval(JournalEntry $entry, ?int $submittedBy = null): JournalEntry
```

And similarly for `approveEntry()` at line 189.

**Step 3: Remove the methods**

Delete lines 145-234 (the entire `submitForApproval` and `approveEntry` methods). Ensure the closing brace of the class still exists.

**Step 4: Run tests to ensure nothing breaks**

```bash
php artisan test tests/Unit/AccountingServiceTest.php --compact
php artisan test tests/Feature/AccountingWorkflowTest.php --compact
```

Expected: All pass.

**Step 5: Commit**

```bash
git add app/Services/AccountingService.php
git commit -m "refactor(accounting): remove dead approval methods"
```

---

### Task 2: Consolidate ChartOfAccountsSeeder into EnhancedChartOfAccountsSeeder

**Files:**
- Delete: `database/seeders/ChartOfAccountsSeeder.php`
- Modify: `database/seeders/EnhancedChartOfAccountsSeeder.php` (add missing accounts)
- Test: `php artisan db:seed --class=EnhancedChartOfAccountsSeeder`

**Rationale:** Two seeders exist with overlapping account codes. Keep the richer one; remove the simpler.

**Step 1: Compare both seeders**

Check which accounts exist only in `ChartOfAccountsSeeder`. Use diff:

```bash
diff -u <(grep "account_code =>" database/seeders/ChartOfAccountsSeeder.php | sort) <(grep "account_code =>" database/seeders/EnhancedChartOfAccountsSeeder.php | sort)
```

Expected: `ChartOfAccountsSeeder` has additional accounts like Nostro accounts (1110-1118), Petty Cash, etc.

**Step 2: Add missing accounts to EnhancedChartOfAccountsSeeder**

Edit `EnhancedChartOfAccountsSeeder.php` in the `createCashAccounts()` method to include the Nostro and other cash accounts from `ChartOfAccountsSeeder`:

```php
    private function createCashAccounts(): void
    {
        $opsCc = CostCenter::where('code', 'OPS-001')->first();
        $finCc = CostCenter::where('code', 'FIN-002')->first();

        $accounts = [
            // Existing MYR, USD, EUR, GBP, SGD, JPY, THB, AUD
            ['account_code' => '1000', 'account_name' => 'Cash - MYR', 'account_type' => 'Asset', 'account_class' => 'Cash', 'cost_center_id' => $opsCc?->id],
            ['account_code' => '1010', 'account_name' => 'Cash - USD', 'account_type' => 'Asset', 'account_class' => 'Cash', 'cost_center_id' => $opsCc?->id],
            // ... (keep existing lines)

            // Add Nostro accounts (from ChartOfAccountsSeeder)
            ['account_code' => '1110', 'account_name' => 'Nostro USD - Wells Fargo', 'account_type' => 'Asset', 'account_class' => 'Nostro', 'cost_center_id' => $finCc?->id],
            ['account_code' => '1111', 'account_name' => 'Nostro EUR - Deutsche Bank', 'account_type' => 'Asset', 'account_class' => 'Nostro', 'cost_center_id' => $finCc?->id],
            ['account_code' => '1112', 'account_name' => 'Nostro GBP - HSBC London', 'account_type' => 'Asset', 'account_class' => 'Nostro', 'cost_center_id' => $finCc?->id],
            ['account_code' => '1113', 'account_name' => 'Nostro SGD - DBS Singapore', 'account_type' => 'Asset', 'account_class' => 'Nostro', 'cost_center_id' => $finCc?->id],
            ['account_code' => '1114', 'account_name' => 'Nostro AUD - Westpac', 'account_type' => 'Asset', 'account_class' => 'Nostro', 'cost_center_id' => $finCc?->id],
            ['account_code' => '1115', 'account_name' => 'Nostro JPY - Mizuho', 'account_type' => 'Asset', 'account_class' => 'Nostro', 'cost_center_id' => $finCc?->id],
            ['account_code' => '1116', 'account_name' => 'Nostro CHF - UBS Zurich', 'account_type' => 'Asset', 'account_class' => 'Nostro', 'cost_center_id' => $finCc?->id],
            ['account_code' => '1117', 'account_name' => 'Nostro CAD - RBC Toronto', 'account_type' => 'Asset', 'account_class' => 'Nostro', 'cost_center_id' => $finCc?->id],
            ['account_code' => '1118', 'account_name' => 'Nostro HKD - HSBC HK', 'account_type' => 'Asset', 'account_class' => 'Nostro', 'cost_center_id' => $finCc?->id],

            // Other cash and liquid assets
            ['account_code' => '1120', 'account_name' => 'Petty Cash', 'account_type' => 'Asset', 'account_class' => 'Cash', 'cost_center_id' => $opsCc?->id],
            ['account_code' => '1130', 'account_name' => 'Cash in Transit', 'account_type' => 'Asset', 'account_class' => 'Cash', 'cost_center_id' => $opsCc?->id],
            ['account_code' => '1140', 'account_name' => 'Overnight Deposits', 'account_type' => 'Asset', 'account_class' => 'Cash', 'cost_center_id' => $finCc?->id],
        ];

        foreach ($accounts as $account) {
            ChartOfAccount::firstOrCreate(
                ['account_code' => $account['account_code']],
                $account
            );
        }
    }
```

Also add `createOtherAssets()` method to cover Fixed Assets and other current assets from `ChartOfAccountsSeeder`. Add this method call in `run()` after `createExpenseAccounts()`.

**Step 3: Delete the old seeder**

```bash
rm database/seeders/ChartOfAccountsSeeder.php
```

**Step 4: Verify seeding works**

```bash
php artisan db:seed --class=EnhancedChartOfAccountsSeeder
```

Check account count:

```bash
php artisan tinker --execute="echo count(App\Models\ChartOfAccount::all());"
```

Should have all accounts from both seeders (~50+).

**Step 5: Update any references to the deleted seeder**

Check `DatabaseSeeder.php` and any other seeders that might call `ChartOfAccountsSeeder`. Replace with `EnhancedChartOfAccountsSeeder`.

```bash
grep -r "ChartOfAccountsSeeder" database/
```

If found, replace.

**Step 6: Commit**

```bash
git add database/seeders/EnhancedChartOfAccountsSeeder.php
git rm database/seeders/ChartOfAccountsSeeder.php
git commit -m "refactor(seeding): consolidate chart of accounts seeders"
```

---

### Task 3: Remove Orphaned Blade Views Referencing STR

**Files:**
- Delete: `resources/views/compliance/workspace/index.blade.php`
- Partial files: (search for STR status badges)
- Many compiled views in `storage/framework/views/` will auto-regenerate

**Rationale:** STR module was removed in P0 cleanup, but view remnants remain. They cause confusion and GitNexus warnings about orphaned files.

**Step 1: Find all Blade files mentioning STR status**

```bash
grep -r "STR Draft\|STR Pending\|STR Submitted\|STR Overdue" resources/views/ --include="*.blade.php"
```

**Step 2: Evaluate each file**

List likely candidates:
- `resources/views/compliance/workspace/index.blade.php` - contains STR approval cards
- `resources/views/compliance/reporting/deadlines.blade.php` - STR deadlines
- `resources/views/compliance/reporting/generate.blade.php` - STR generation
- `resources/views/compliance/reporting/history.blade.php` - STR history
- `resources/views/pages/compliance.blade.php` - compliance dashboard widgets

**Step 3: Delete identified files**

```bash
rm resources/views/compliance/workspace/index.blade.php
rm resources/views/compliance/reporting/deadlines.blade.php
rm resources/views/compliance/reporting/generate.blade.php
rm resources/views/compliance/reporting/history.blade.php
rm resources/views/pages/compliance/index.blade.php  # if exists
```

**Note:** Before deleting, check if other parts of the application reference these routes. If routes still exist and point to STR functionality, either keep the views or remove routes too. Since STR was removed in P0, routes should already be gone. Quick check:

```bash
grep -r "str" routes/ --include="*.php" -i
```

If any STR routes remain, this plan will need to delete those route definitions as part of Task 3a.

**Step 3a (if needed): Remove STR routes**

Open `routes/web.php` and `routes/api.php` and remove any routes with `/str` or `StrController`. Also remove related controllers if they exist.

**Step 4: Clear compiled views**

```bash
php artisan view:clear
rm -rf storage/framework/views/*
```

**Step 5: Verify no remaining STR view references**

```bash
grep -r "STR Draft\|STR Pending" resources/views/ --include="*.blade.php"
```

Should return nothing.

**Step 6: Commit**

```bash
git rm resources/views/compliance/workspace/index.blade.php
git rm resources/views/compliance/reporting/deadlines.blade.php
git rm resources/views/compliance/reporting/generate.blade.php
git rm resources/views/compliance/reporting/history.blade.php
# ... any others
git commit -m "chore(ui): remove orphaned STR views"
```

---

### Task 4: Simplify isDebitAccount() Method

**Files:**
- Modify: `app/Services/AccountingService.php:421-430`
- Modify: `app/Services/FiscalYearService.php:537-545`
- Test: `tests/Unit/AccountingServiceTest.php` (existing tests cover `isDebitAccount`)

**Rationale:** The fallback `in_array()` is no longer needed if `ChartOfAccount` always casts `account_type` to `AccountType` enum. The models have `'account_type' => AccountType::class` cast. So we can simplify.

**Step 1: Verify AccountType casting is always present**

Check `app/Models/ChartOfAccount.php`:

```php
protected $casts = [
    'is_active' => 'boolean',
    'allow_journal' => 'boolean',
    'account_type' => AccountType::class,
];
```

This is present (line 64). So `$account->account_type` is always an `AccountType` enum instance. Thus fallback is unnecessary.

**Step 2: Simplify AccountingService::isDebitAccount()**

Replace lines 421-430:

```php
    protected function isDebitAccount(string $accountCode): bool
    {
        $account = ChartOfAccount::find($accountCode);
        if (! $account) {
            throw new AccountNotFoundException($accountCode);
        }

        return $account->account_type->isDebitNormal();
    }
```

**Step 3: Simplify FiscalYearService::isDebitAccount()**

Similarly, replace lines 537-545:

```php
        return $account->account_type->isDebitNormal();
```

(eliminate the `in_array` check).

**Step 4: Run tests to verify**

```bash
php artisan test tests/Unit/AccountingServiceTest.php --compact
php artisan test tests/Unit/FiscalYearServiceTest.php --compact  # if exists
```

All should pass.

**Step 5: Commit**

```bash
git add app/Services/AccountingService.php app/Services/FiscalYearService.php
git commit -m "refactor(accounting): simplify isDebitAccount using enum"
```

---

### Task 5: Fix normal_balance for OffBalance Account Type

**Files:**
- Modify: `database/migrations/2026_05_31_135056_add_normal_balance_to_chart_of_accounts.php`

**Rationale:** The migration sets `OffBalance` accounts to `'Debit'` by default. Need to verify this is correct or use a neutral default. Since OffBalance accounts (like suspense accounts) can be either, but by convention they are often debit-normal. However, to be safe, we could leave them null. But `AccountType::normalBalance()` returns `'Debit'` for `OffBalance`. That should align.

Actually, the concern: The migration explicitly sets OffBalance => Debit in the second update block (line 21-24) which only covers Liability/Equity/Revenue. OffBalance is not in either list, so it remains NULL. But `AccountType::normalBalance()` returns 'Debit' for OffBalance. This mismatch may cause issues if code relies on `normal_balance` column.

Let's check the migration code:

```php
DB::table('chart_of_accounts')
    ->whereIn('account_type', ['Asset', 'Expense'])
    ->whereNull('normal_balance')
    ->update(['normal_balance' => 'Debit']);

DB::table('chart_of_accounts')
    ->whereIn('account_type', ['Liability', 'Equity', 'Revenue'])
    ->whereNull('normal_balance')
    ->update(['normal_balance' => 'Credit']);
```

OffBalance is not in either list. So `normal_balance` remains NULL.

Potential bug: If `AccountLedger` or other code uses `ChartOfAccount->normal_balance` attribute (column), it may be null for OffBalance. But currently, code uses `$account->account_type->isDebitNormal()` not the column. So the column is extra.

Given that we added `normal_balance` column as a materialized view of `account_type`, it may be used for reporting. For consistency, we should set it for OffBalance as well.

**Step 1: Determine correct normal balance for OffBalance**

In accounting, off-balance sheet items are not typically debit/credit balances; they are not balances at all. But if we must assign, common examples: `Account` suspense accounts are debit-normal. So 'Debit' is acceptable.

**Step 2: Update migration to include OffBalance**

Edit the second update block to include OffBalance? Actually we could add a third update:

```php
DB::table('chart_of_accounts')
    ->where('account_type', 'OffBalance')
    ->whereNull('normal_balance')
    ->update(['normal_balance' => 'Debit']);
```

**Step 3: Re-run migration (if not yet in production)**

Since this is a development environment, we can modify the migration and re-run:

```bash
php artisan migrate:rollback --step=1
php artisan migrate
```

**Alternatively:** If migration already ran in shared environments, create a new migration to update existing rows:

```bash
php artisan make:migration update_normal_balance_for_offbalance_accounts --table=chart_of_accounts
```

And put:

```php
public function up(): void
{
    DB::table('chart_of_accounts')
        ->where('account_type', 'OffBalance')
        ->whereNull('normal_balance')
        ->update(['normal_balance' => 'Debit']);
}
```

**Step 4: Commit**

Either modify existing migration (if not pushed) or create new one.

```bash
git add database/migrations/2026_05_31_135056_add_normal_balance_to_chart_of_accounts.php
git commit -m "fix(accounting): set normal_balance for OffBalance accounts"
```

---

### Task 6: Add Database Indexes for Performance

**Files:**
- Create: `database/migrations/2026_05_31_xxxxxx_add_account_ledger_indexes.php`
- (Optional) `database/migrations/2026_05_31_xxxxxx_add_journal_entries_indexes.php`

**Rationale:** `LedgerService::getTrialBalance` and `getAccountBalance` perform queries on `account_ledger`. Indexes on `(account_code, entry_date)` and `(entry_date)` will improve performance for large ledgers.

**Step 1: Generate migration for account_ledger indexes**

```bash
php artisan make:migration add_account_ledger_indexes --table=account_ledger
```

Edit the migration:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_ledger', function (Blueprint $table) {
            // Compound index for queries filtering by account_code and entry_date (e.g., getAccountBalance)
            $table->index(['account_code', 'entry_date'], 'idx_account_ledger_account_entry');

            // Index for entry_date used in date range queries (trial balance, period reports)
            $table->index('entry_date', 'idx_account_ledger_entry_date');

            // Index for journal_entry_id for joins
            $table->index('journal_entry_id', 'idx_account_ledger_journal_entry');

            // Optional: branch_id if multi-branch queries are frequent
            // $table->index(['branch_id', 'entry_date'], 'idx_account_ledger_branch_entry');
        });
    }

    public function down(): void
    {
        Schema::table('account_ledger', function (Blueprint $table) {
            $table->dropIndex('idx_account_ledger_account_entry');
            $table->dropIndex('idx_account_ledger_entry_date');
            $table->dropIndex('idx_account_ledger_journal_entry');
        });
    }
};
```

**Step 2: Add indexes for journal_entries**

Frequently queries by `entry_date`, `period_id`, `status`. Also need `created_by` for audit.

```bash
php artisan make:migration add_journal_entries_indexes --table=journal_entries
```

Edit:

```php
Schema::table('journal_entries', function (Blueprint $table) {
    $table->index('entry_date', 'idx_journal_entries_entry_date');
    $table->index('period_id', 'idx_journal_entries_period_id');
    $table->index('status', 'idx_journal_entries_status');
    $table->index('created_by', 'idx_journal_entries_created_by');
    // Compound index for period + status queries (closing, reporting)
    $table->index(['period_id', 'status'], 'idx_journal_entries_period_status');
});
```

**Step 3: Run migrations**

```bash
php artisan migrate
```

**Step 4: Verify indexes created**

```bash
php artisan tinker --execute="print_r(DB::getSchemaBuilder()->getColumnListing('account_ledger'));"
# Or query INFORMATION_SCHEMA
```

Or better:

```bash
mysql -e "SHOW INDEX FROM account_ledger;" $(php artisan db:show | head -1)
```

**Step 5: Commit**

```bash
git add database/migrations/2026_05_31_*_add_*_indexes.php
git commit -m "perf(DB): add indexes to account_ledger and journal_entries"
```

---

### Task 7: Cleanup ReportingService of Legacy STR Logic

**Files:**
- Modify: `app/Services/ReportingService.php` (remove STR-specific report generation methods if any)
- Delete: `app/Http/Controllers/Compliance/ComplianceReportingController.php` if only used for STR
- Delete: `app/Services/Compliance/ComplianceReportingService.php` if redundant

**Step 1: Search for STR-specific code in ReportingService**

```bash
grep -n "STR\|str" app/Services/ReportingService.php
```

If found, remove those methods.

**Step 2: Check ComplianceReportingController**

Read the file to see its purpose. If it only handled STR reporting, delete it.

```bash
cat app/Http/Controllers/Compliance/ComplianceReportingController.php
```

If methods are solely:

```php
public function index() { /* STR reports list */ }
public function generateStr() { /* ... */ }
```

Then delete the file and any routes pointing to it.

**Step 3: Remove routes**

In `routes/web.php`, look for:

```php
Route::middleware(['auth'])->prefix('compliance/reporting')->group(function () {
    Route::get('/', [ComplianceReportingController::class, 'index']);
    Route::post('/generate-str', [ComplianceReportingController::class, 'generateStr']);
    // etc.
});
```

Remove these routes.

**Step 4: Clean up service class if empty**

If `ComplianceReportingService` is now empty or unused, delete it.

**Step 5: Run tests to ensure nothing breaks**

```bash
php artisan test tests/Unit/ReportingServiceTest.php --compact
```

Also check any feature tests referencing reporting:

```bash
php artisan test --filter="Report" --compact
```

**Step 6: Commit**

```bash
git rm app/Http/Controllers/Compliance/ComplianceReportingController.php
# plus any other deleted files
git commit -m "chore(reporting): remove legacy STR reporting code"
```

---

### Task 8: Update Test Seeder References

**Files:**
- Various test files that reference `ChartOfAccountsSeeder`

**Step 1: Find tests calling ChartOfAccountsSeeder**

```bash
grep -r "ChartOfAccountsSeeder" tests/ --include="*.php"
```

**Step 2: Replace with `EnhancedChartOfAccountsSeeder`**

Example: In `tests/Unit/ChartOfAccountTest.php` line 16:

```php
$this->seed(ChartOfAccountsSeeder::class);
```

Replace with:

```php
$this->seed(EnhancedChartOfAccountsSeeder::class);
```

Do this for all occurrences.

**Step 3: Ensure tests still pass**

```bash
php artisan test tests/Unit/ChartOfAccountTest.php --compact
```

**Step 4: Commit**

```bash
git add tests/
git commit -m "test: update seeder references to EnhancedChartOfAccountsSeeder"
```

---

### Task 9: Verify AccountLedger getNetAmount() Consistency

**Files:**
- `app/Models/AccountLedger.php` (getNetAmount method)
- `app/Services/AccountingService.php` (updateLedger method)
- `app/Services/LedgerService.php` (getProfitAndLoss etc.)

**Issue:** `AccountLedger::getNetAmount()` uses `app(MathService::class)->subtract((string) $this->debit, (string) $this->credit)`. This is consistent. However, some code in `updateLedger` recomputes balance using a different pattern. Ensure consistency to avoid floating point drift.

**Step 1: Verify getNetAmount is used consistently**

Search for `->debit - ->credit` patterns:

```bash
grep -n "debit.*credit\|credit.*debit" app/Services/AccountingService.php app/Services/LedgerService.php
```

In `updateLedger`, the code does:

```php
if ($this->isDebitAccount($line->account_code)) {
    $newBalance = $this->mathService->add(
        $this->mathService->add($currentBalance, (string) $line->debit),
        $this->mathService->multiply((string) $line->credit, '-1')
    );
} else {
    $newBalance = $this->mathService->add(
        $this->mathService->add($currentBalance, (string) $line->credit),
        $this->mathService->multiply((string) $line->debit, '-1')
    );
}
```

This is algebraically equivalent to:

For debit accounts: new = current + debit - credit  
For credit accounts: new = current + credit - debit

Yes, correct.

**Step 2: No code change needed - just document**

But we should ensure that if any other code manually computes net amounts, they use `getNetAmount()`.

**Step 3: Commit (documentation only if no changes)**

```bash
git commit -m "chore(accounting): verify net amount calculation consistency"
```

---

### Task 10: Add Unique Constraint to str_reports Table (if exists)

Wait: The `str_reports` table should have been removed in P0 cleanup. Let's verify.

**Step 1: Check if str_reports table exists**

```bash
mysql -e "SHOW TABLES LIKE 'str_reports';" $(php artisan db:show | head -1)
```

If not found, skip this task. If found (should not be), we need to drop it or add constraints? Actually we want to ensure no duplicate STR entries exist but the table is gone, so nothing to do.

**Step 2: If table exists, add unique constraint on report_code**

But the P0 cleanup should have removed it. Check git log to confirm removal. If the table still exists in the database, we need a migration to drop it:

```bash
php artisan make:migration drop_str_reports_table --drop=str_reports
```

Then run migrations. But this should have already been done. Let's not include in plan if not needed.

**Conclusion:** Skip this task.

---

### Task 11: Final Verification & Documentation

**Files:**
- Create: `docs/accounting-cleanup.md` (summary of changes)
- Update: `CHANGELOG.md` (if present)

**Step 1: Run full test suite**

```bash
php artisan test --compact --testsuite=Feature
php artisan test --compact --testsuite=Unit
```

Ensure no failures.

**Step 2: Check GitNexus impact**

```bash
npx gitnexus analyze
```

Review any warnings about orphaned files or orphaned references.

**Step 3: Write documentation**

Create `docs/accounting-cleanup.md`:

```markdown
# Accounting System Cleanup (P2)

## Summary
- Removed dead approval workflow methods (`submitForApproval`, `approveEntry`)
- Consolidated Chart of Accounts seeders
- Removed orphaned STR-related Blade views
- Simplified `isDebitAccount()` to use `AccountType` enum directly
- Added database indexes for performance
- Cleaned up reporting services

## Migration Notes
- If upgrading existing instance: run `php artisan migrate` to add indexes
- The `normal_balance` column for `OffBalance` accounts is explicitly set to 'Debit'
- Direct posting is now the default behavior; journal entries are created as Posted immediately.

## Post-Upgrade Checks
1. Verify trial balance generation is fast with large ledgers
2. Ensure no STR-related routes remain accessible
3. Confirm EnhancedChartOfAccountsSeeder contains all required accounts
```

**Step 4: Update CHANGELOG.md**

Add entry under `## [Unreleased]`:

```markdown
### Refactoring
- Removed dead approval methods from AccountingService
- Consolidated Chart of Accounts seeders
- Removed orphaned STR views
- Simplified isDebitAccount() using AccountType enum
- Added performance indexes to account_ledger and journal_entries
```

**Step 5: Final commit**

```bash
git add docs/accounting-cleanup.md CHANGELOG.md
git commit -m "docs: update changelog with P2 cleanup changes"
```

---

### Implementation Sequence

**Order:**
1. Task 1 (remove dead code)
2. Task 2 (consolidate seeders)
3. Task 3 (remove orphaned views)
4. Task 4 (simplify isDebitAccount)
5. Task 5 (fix normal_balance for OffBalance)
6. Task 6 (add indexes)
7. Task 7 (cleanup ReportingService)
8. Task 8 (update test seeders)
9. Task 9 (verify consistency)
10. Task 11 (finalize)

Task 10 is conditional/skip.

---

### Acceptance Criteria

- All tests pass (Unit + Feature)
- GitNexus shows no orphaned view references for STR
- `php artisan migrate` runs without errors (indexes present)
- No dead code warnings from static analysis (Pint)
- Direct posting flow works as expected
- Trial balance performance acceptable (sub-second for 10k ledger entries)

---

### Risks & Mitigations

| Risk | Impact | Mitigation |
|------|--------|------------|
| Removing approval methods breaks external package | High | Verified no external callers via grep before removal |
| Deleting views used elsewhere | Medium | Checked route definitions; views are orphaned |
| Indexes cause lock on large tables | Medium | Run during low-traffic window; use online DDL if needed |
| OffBalance normal_balance mismatch | Low | Confirmed with accounting principle; set to Debit |

---

**End of Plan**
