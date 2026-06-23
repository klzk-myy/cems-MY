# Implementation Plan: Codebase Cleanup & Security Fixes

**Date**: May 4, 2026
**Scope**: Original cleanup + newly discovered security/performance issues
**Status**: Draft - Awaiting Approval

---

## Part 1: QueryLogServiceProvider Refactor

### Problem
- `shouldMonitor()` has side effects (mutates `$shutdownLoggingSafe` and `$isTesting`)
- Inconsistent use of `app()->environment()` vs cached flags
- Shutdown function pattern fragile but functional

### Solution
Refactor to separate state determination from query logic:

```php
class QueryLogServiceProvider extends ServiceProvider
{
    private bool $monitoringEnabled = false;
    private bool $shutdownLoggingEnabled = false;
    private bool $isTestingEnvironment = false;

    public function boot(): void
    {
        $this->determineEnvironment();

        if (!$this->shouldMonitor()) {
            return;
        }

        $this->configureThresholds();
        $this->cacheRequestData();
        $this->registerQueryListener();
        $this->registerShutdownHandler();
    }

    private function determineEnvironment(): void
    {
        $this->isTestingEnvironment = $this->app->environment('testing');

        if ($this->isTestingEnvironment) {
            $this->monitoringEnabled = true;
            $this->shutdownLoggingEnabled = false;
            return;
        }

        if ($this->app->runningInConsole()) {
            $this->monitoringEnabled = config('database.query_monitoring_console', false);
            $this->shutdownLoggingEnabled = $this->monitoringEnabled;
            return;
        }

        $this->monitoringEnabled = config('app.debug') || config('database.query_monitoring_enabled', false);
        $this->shutdownLoggingEnabled = $this->monitoringEnabled;
    }

    private function shouldMonitor(): bool
    {
        return $this->monitoringEnabled;
    }

    private function shouldLogToDatabase(): bool
    {
        return !$this->isTestingEnvironment;
    }

    private function registerShutdownHandler(): void
    {
        if (!$this->shutdownLoggingEnabled || !function_exists('register_shutdown_function')) {
            return;
        }
        register_shutdown_function([$this, 'logRequestSummary']);
    }
}
```

### Files Affected
- `app/Providers/QueryLogServiceProvider.php`

---

## Part 2: firstOrFail() → first() + Null Check

### Problem
9 occurrences of `firstOrFail()` across 5 files. If referenced records are deleted, throws `ModelNotFoundException` with no graceful handling.

### Files & Changes
| File | Line(s) | Change |
|------|---------|--------|
| `app/Http/Controllers/CounterController.php` | 115, 136, 215, 239 | Return 404 with message |
| `app/Http/Controllers/Api/V1/Compliance/RiskController.php` | 25, 87, 102 | Return 404 JSON |
| `app/Services/CustomerRiskScoringService.php` | 188 | Throw domain exception |
| `app/Services/FiscalYearService.php` | 145 | Throw domain exception |

---

## Part 3: Security Fixes

### SEC-1: XSS Vulnerability in Blade Template (MEDIUM)
**File:** `resources/views/str/edit.blade.php:34`
**Issue:** User input in HTML attribute without escaping
**Fix:**
```php
// Before
value="STR edited by {{ auth()->user()->username }}"

// After
value="{{ e(auth()->user()->username) }}"
```

### SEC-2: SQL Injection Risk in Customer Search (MEDIUM)
**File:** `app/Services/CustomerService.php:204-208`
**Issue:** Special characters `%` and `_` not escaped in LIKE query
**Fix:**
```php
// Before
$customers = Customer::where('full_name', 'like', "%{$query}%")

// After
$escapedQuery = str_replace(['%', '_'], ['\\%', '\\_'], $query);
$customers = Customer::where('full_name', 'like', "%{$escapedQuery}%")
```

### SEC-3: Sanctions Webhook Token Validation (HIGH)
**File:** `app/Http/Controllers/Api/SanctionsWebhookController.php`
**Issue:** Webhook endpoint doesn't validate authentication token
**Fix:** Add token validation middleware or check

### SEC-4: Missing Authorization on Customer Show (MEDIUM)
**File:** `app/Http/Controllers/Api/V1/CustomerController.php:79-96`
**Issue:** Any authenticated user can view any customer's details
**Fix:** Add branch-based authorization check

### SEC-5: Test Mode Exposure in Production (HIGH)
**File:** `app/Services/StrReportService.php:440-448`
**Issue:** Test mode may be active in production
**Fix:** Add production guard for test mode

---

## Part 4: Performance Fixes

### PERF-1: N+1 Query in CustomerController::show() (MEDIUM)
**File:** `app/Http/Controllers/Api/V1/CustomerController.php:86-89`
**Issue:** 4 separate queries for transaction statistics
**Fix:** Use single aggregated query with `selectRaw()`

### PERF-2: Redundant Sanctions Screening (MEDIUM)
**File:** `app/Services/CustomerService.php:220-221`
**Issue:** Screens each customer individually (up to 10 DB queries)
**Fix:** Consider caching or batching

---

## Order of Implementation

1. QueryLogServiceProvider refactor (Part 1)
2. XSS fix in Blade template (SEC-1)
3. SQL injection fix (SEC-2)
4. Customer show authorization (SEC-4)
5. CounterController firstOrFail() fixes (Part 2)
6. RiskController firstOrFail() fixes (Part 2)
7. CustomerRiskScoringService firstOrFail() fix (Part 2)
8. FiscalYearService firstOrFail() fix (Part 2)
9. N+1 query fix (PERF-1)
10. StrReportService test mode guard (SEC-5)
11. SanctionsWebhookController token validation (SEC-3)
12. Full test suite verification

---

## Testing Plan

```bash
# Quick verification
php artisan test --filter=MathServiceTest --compact

# Full suite after all changes
php artisan test --compact

# Test specific fixes
php artisan test --filter=CounterControllerTest --compact
php artisan test --filter=CustomerControllerTest --compact
```

---

## Estimated Changes
- Lines changed: ~200-300
- Files affected: 12-15
- Test files to verify: 3+