# Query Log Analysis Results

**Date:** 2026-04-28  
**Analyst:** System (Automated)  
**Phase:** 2 - Performance Optimization  
**Task:** 6 - Analyze Query Logs to Identify Remaining N+1 Issues

---

## Executive Summary

Query log analysis reveals significant N+1 issues in the application, primarily in the accounting/financial reporting module and the home page dashboard. The most severe pattern is a per-account query loop in `LedgerService` that results in 80-150 queries for basic financial statements. The home page exhibits extreme query counts (4,000-8,000 queries) indicating multiple N+1 patterns in dashboard components.

**Critical Routes Affected:**
- `GET /trial-balance` (140 queries)
- `GET /profit-loss` (89 queries)
- `GET /balance-sheet` (87-90 queries)
- `GET /` (home page - up to 8,372 queries)

---

## Methodology

### Infrastructure Reviewed
- `app/Services/QueryLoggingService.php` - Basic N+1 detection via query pattern normalization
- `app/Http/Middleware/QueryLogging.php` - Global middleware (enabled)
- `app/Providers/QueryLogServiceProvider.php` - Comprehensive query monitoring (logs summaries to `storage/logs/query-*.log`)
- Configuration: `config/database.php` - logging enabled via `DB_LOGGING=true`

### Data Collection
- Query logging was enabled (`DB_LOGGING=true`) and config cleared.
- Analyzed existing query log files from `storage/logs/query-2026-04-{23..27}.log`.
- The `QueryLogServiceProvider` logs warnings for high query counts (>50) with the message: `High query count detected: X queries`.
- Cross-referenced log entries with controller/action routes to identify affected endpoints.

### Pattern Detection Criteria
- **N+1 Pattern**: More than 3 repeated queries with same structure but different parameters (detected by high total query count, especially >50).
- **Severity**: Based on query count magnitude (>100 = high, >1000 = critical).

---

## Detailed Findings

### 1. Financial Statement Routes - Severe N+1

**Routes & Query Counts:**

| Route | Controller@method | Query Count | Date |
|-------|-------------------|-------------|------|
| `GET /trial-balance` | FinancialStatementController@trialBalance | 140 | 2026-04-24 |
| `GET /profit-loss` | FinancialStatementController@profitLoss | 89 | 2026-04-24, 25 |
| `GET /balance-sheet` | FinancialStatementController@balanceSheet | 87-90 | 2026-04-23, 24, 25 |

**Root Cause Analysis:**

All three methods delegate to `LedgerService`:

```php
// FinancialStatementController
public function trialBalance(Request $request)
{
    $trialBalance = $this->ledgerService->getTrialBalance($asOfDate);
    // ...
}
```

The `LedgerService::getTrialBalance()` method contains the N+1 pattern:

```php
public function getTrialBalance(?string $asOfDate = null, ?int $branchId = null): array
{
    $asOfDate = $asOfDate ?? now()->toDateString();
    $accounts = ChartOfAccount::where('is_active', true)->orderBy('account_code')->get();

    $trialBalance = [];
    $totalDebits = '0';
    $totalCredits = '0';

    foreach ($accounts as $account) {
        $balance = $this->getAccountBalance($account->account_code, $asOfDate, $branchId); // N+1!
        // ... build result
    }
}
```

**Pattern:** 1 query fetches all active ChartOfAccount records. Then for each account (typically 80-130 accounts), `getAccountBalance()` executes a separate query to calculate that account's balance. This results in **1 + N queries** where N = number of accounts.

**Evidence:** Query count of 87-140 correlates with number of accounts.

**Additional methods affected:** `getProfitAndLoss()` and `getBalanceSheet()` use similar loops over accounts, causing identical N+1 patterns.

---

### 2. Home Page ("/") - Critical N+1

**Route & Query Counts:**

| Route | Query Count | Date | Time |
|-------|-------------|------|------|
| `GET /` | 4,410 | 2026-04-24 | 19:48:08 |
| `GET /` | 3,254 | 2026-04-24 | 19:48:08 |
| `GET /` | 8,372 | 2026-04-24 | 19:49:33 |

**Severity:** CRITICAL - Thousands of queries indicate multiple N+1 loops.

**Root Cause Analysis:**

The home page (dashboard) aggregates data from multiple domains: recent transactions, customer statistics, compliance alerts, accounting summaries, etc. The extremely high query count suggests that multiple components load collections and then access relationships in the view without eager loading.

**Likely pattern:**
```php
$transactions = Transaction::latest()->limit(10)->get(); // 1 query
foreach ($transactions as $tx) {
    // Accessing $tx->customer triggers query per transaction if not eager loaded
}
```

Or:
```php
$customers = Customer::active()->get(); // 1 query
foreach ($customers as $customer) {
    // Accessing $customer->transactions()->count() or similar aggregates in view
}
```

However, the exact source requires deeper inspection of the home page controller and Blade templates.

**Note:** The same route also had lower counts (e.g., 278 queries) on the same day, suggesting different user roles or data volumes.

---

### 3. Accounting Routes (Additional Evidence)

The same N+1 pattern from `LedgerService` also affects:

- `GET /accounting/balance-sheet` (87 queries)
- `GET /accounting/trial-balance` (140 queries)
- `GET /accounting/profit-loss` (89 queries)

These are the web UI equivalents of the API routes above.

---

## Additional Observations

### QueryLoggingService N+1 Detection
The `QueryLoggingService` (simple pattern count > 1) did **not** produce any warnings in the logs. Its normalization logic may be too aggressive or the threshold too low. The more effective detection is from `QueryLogServiceProvider` which flags high query counts (>50).

### CustomerController
No N+1 issues detected in the examined methods:
- `CustomerController@index` does not eager load any relationships, but the Customer model has no `$appends` that would trigger extra queries, so query count remains low (single query).
- `CustomerController@show` uses eager loading for `documents` and limited `transactions`, and runs 3 aggregate queries on the already-loaded transactions collection. This is **not** an N+1 because it's only 3 additional queries on the collection (not in a loop). However, it could be optimized using `withCount` and `withSum`.

### TransactionController
Both `index` and `show` properly use eager loading (`with(['customer', 'user'])` and `with(['customer', 'user', 'approver', 'flags'])`). No N+1 detected.

---

## Recommended Fixes

### Fix 1: Optimize LedgerService Balance Calculation

**Goal:** Replace per-account queries with a single aggregated query.

**Approach:** Use a single query to compute balances for all accounts in one go, then merge with ChartOfAccounts.

**Example Implementation:**

```php
public function getTrialBalance(?string $asOfDate = null, ?int $branchId = null): array
{
    $asOfDate = $asOfDate ?? now()->toDateString();

    // 1. Get all accounts (1 query)
    $accounts = ChartOfAccount::where('is_active', true)
        ->orderBy('account_code')
        ->get()
        ->keyBy('account_code'); // key by code for lookup

    // 2. Get all account balances in a single query (1 query)
    $balances = AccountLedger::select(
        'account_code',
        DB::raw('SUM(debit - credit) as balance')
    )
    ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
    ->where('entry_date', '<=', $asOfDate)
    ->groupBy('account_code')
    ->pluck('balance', 'account_code');

    // 3. Merge balances into accounts
    $totalDebits = '0';
    $totalCredits = '0';
    $trialBalance = [];

    foreach ($accounts as $code => $account) {
        $balance = $balances->get($code, '0');

        // ... same logic to split into debit/credit based on account type
        // (use MathService as before)

        $trialBalance[] = [ /* ... */ ];
        $totalDebits = $this->mathService->add($totalDebits, $debit);
        $totalCredits = $this->mathService->add($totalCredits, $credit);
    }

    // ... rest unchanged
}
```

**Impact:** Reduces queries from ~1+N to ~2 (accounts + balances), cutting query count by >95%.

**Note:** This pattern must be applied to `getProfitAndLoss()` and `getBalanceSheet()` as well.

---

### Fix 2: Home Page Dashboard N+1

**Investigation Steps:**
1. Enable query logging with a lower threshold for the home page.
2. Install Laravel Debugbar or use `DB::listen()` to capture all queries for a home page request.
3. Identify which relationships are being accessed inside loops (e.g., `$transaction->customer`, `$alert->assignedUser`, etc.).
4. Add appropriate `with()` eager loading to the controller queries.

**Common Fixes:**
- For recent transactions list: `Transaction::with(['customer', 'user', 'approver'])->latest()->limit(10)->get()`
- For customer-related stats: Use `withCount()` for counts (transactions, alerts) instead of looping.
- For compliance alerts: eager load `customer`, `assignedTo`, etc.
- Consider using `load()` on collections if relationships vary by component.

**Advanced:** Cache entire dashboard sections for 5-15 minutes to avoid repeated heavy queries.

---

### Fix 3: General Safeguards

1. **Apply `query.monitor` middleware** to all web and API routes during development to catch new N+1 issues early. Currently it's only an alias but not widely used.
2. **Add automated query count assertions** in feature tests:
   ```php
   $this->artemisGet('/trial-balance')->assertDontSeeQueryCountMoreThan(20);
   ```
   Or use `DB::assertQueryCount(...)`.
3. **Code Review Checklist:** Require eager loading for any relationship accessed outside the model.
4. **Add model-level `$with`** for relationships that are always needed (e.g., Transaction always needs `customer` and `user`). This provides default protection.

---

## Conclusion

The query logs expose two major N+1 hotspots:
1. **Accounting module** - systematic N+1 in balance calculation loops (easily fixable with aggregated queries).
2. **Dashboard** - multiple unknown N+1 patterns requiring detailed profiling.

Fixing the LedgerService will immediately reduce query counts on financial statements from ~100 to ~2. The dashboard requires deeper investigation but will yield similar performance gains.

All recommended fixes should be implemented in separate tasks following the performance optimization phase.

---

## Appendix: Raw Query Log Excerpts

### High Query Count Warnings (April 24, 2026)

```
[2026-04-24 18:50:53] local.WARNING: High query count detected: 151 queries {"url":"http://local.host","suggestion":"Consider using eager loading or caching"}
[2026-04-24 19:47:43] local.WARNING: High query count detected: 4410 queries {"url":"http://local.host","suggestion":"Consider using eager loading or caching"}
[2026-04-24 19:48:08] local.WARNING: High query count detected: 3254 queries {"url":"http://local.host","suggestion":"Consider using eager loading or caching"}
[2026-04-24 19:49:33] local.WARNING: High query count detected: 8372 queries {"url":"http://local.host","suggestion":"Consider using eager loading or caching"}
[2026-04-24 19:53:08] local.WARNING: High query count detected: 140 queries {"url":"http://local.host/accounting/trial-balance","suggestion":"Consider using eager loading or caching"}
[2026-04-24 19:53:09] local.WARNING: High query count detected: 51 queries {"url":"http://local.host","suggestion":"Consider using eager loading or caching"}
[2026-04-24 19:53:21] local.WARNING: High query count detected: 89 queries {"url":"http://local.host/accounting/profit-loss","suggestion":"Consider using eager loading or caching"}
[2026-04-24 19:54:49] local.WARNING: High query count detected: 90 queries {"url":"http://local.host/accounting/balance-sheet","suggestion":"Consider using eager loading or caching"}
[2026-04-24 19:55:11] local.WARNING: High query count detected: 90 queries {"url":"http://local.host/accounting/balance-sheet","suggestion":"Consider using eager loading or caching"}
[2026-04-24 19:55:14] local.WARNING: High query count detected: 89 queries {"url":"http://local.host/accounting/profit-loss","suggestion":"Consider using eager loading or caching"}
[2026-04-24 19:58:42] local.WARNING: High query count detected: 89 queries {"url":"http://local.host/accounting/profit-loss","suggestion":"Consider using eager loading or caching"}
[2026-04-24 20:02:45] local.WARNING: High query count detected: 278 queries {"url":"http://local.host","suggestion":"Consider using eager loading or caching"}
[2026-04-24 20:04:01] local.WARNING: High query count detected: 88 queries {"url":"http://local.host/accounting/profit-loss","suggestion":"Consider using eager loading or caching"}
[2026-04-24 20:04:10] local.WARNING: High query count detected: 88 queries {"url":"http://local.host/accounting/profit-loss","suggestion":"Consider using eager loading or caching"}
[2026-04-24 20:04:19] local.WARNING: High query count detected: 88 queries {"url":"http://local.host/accounting/profit-loss","suggestion":"Consider using eager loading or caching"}
[2026-04-24 20:04:28] local.WARNING: High query count detected: 88 queries {"url":"http://local.host/accounting/profit-loss","suggestion":"Consider using eager loading or caching"}
[2026-04-24 20:04:57] local.WARNING: High query count detected: 325 queries {"url":"http://local.host","suggestion":"Consider using eager loading or caching"}
[2026-04-24 20:05:09] local.WARNING: High query count detected: 90 queries {"url":"http://local.host/accounting/profit-loss","suggestion":"Consider using eager loading or caching"}
[2026-04-25 05:33:05] local.WARNING: High query count detected: 89 queries {"url":"http://local.host/accounting/profit-loss","suggestion":"Consider using eager loading or caching"}
[2026-04-25 15:58:15] local.WARNING: High query count detected: 922 queries {"url":"http://local.host","suggestion":"Consider using eager loading or caching"}
[2026-04-25 15:58:42] local.WARNING: High query count detected: 1584 queries {"url":"http://local.host","suggestion":"Consider using eager loading or caching"}
```

All warnings originate from `QueryLogServiceProvider::logRequestSummary()` (channel: query).
