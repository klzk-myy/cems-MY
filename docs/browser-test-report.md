# Browser Smoke Test Report — CEMS-MY

**Date:** 2026-06-26  
**Tester account:** admin / [redacted]  
**Environment:** Laravel 11, PHP 8.3, MySQL (production), SQLite (tests), http://local.host  
**Tooling:** Playwright (system Chrome) + curl verification  

## Scope

1. Log in as admin.
2. Crawl all internal links reachable from the dashboard (depth-first, same-origin `GET` links only).
3. Fix every page that returned a non-2xx status or a server error.
4. Add automated smoke tests for the fixed routes.
5. Run the full PHPUnit suite.

## Summary

| Metric | Value |
|--------|-------|
| Pages checked | 46 |
| Unique broken pages found | 7 |
| Pages fixed | 7 |
| Full test suite | **1,198 passed**, 5 skipped, 1 deprecated |
| `APP_DEBUG` | restored to `false` |

## Issues Found & Fixes

### 1. `/reports/msb2` — 500 SQL parameter binding error
**Root cause:** `DB::raw('...', [bindings])` is not valid in Laravel; bindings were ignored and the query threw `Invalid parameter number`.
**Fix:** Replaced conditional aggregates with `selectRaw('...', [bindings])` in `RegulatoryReportController::msb2`.

### 2. `/reports/msb2` — 500 `Undefined variable $thead`
**Root cause:** The empty-state `<x-table>` in the same view did not provide the required `<x-slot:thead>`.
**Fix:** Added an empty `<x-slot:thead></x-slot:thead>` to the second table in `resources/views/reports/msb2/index.blade.php`.

### 3. `/test-results` — 500 undefined statistics keys
**Root cause:** `TestRunnerService::getStatistics()` returned `avg_pass_rate`/`total_runs`, but the Blade view expected `passed`, `failed`, and `pass_rate`.
**Fix:** Added the missing keys (`passed`, `failed`, `pass_rate`) to the returned array. No other consumers relied on the previous key names.

### 4. `/reports/monthly-trends` — 500 SQL + view shape mismatch
**Root cause:**
- `DB::raw` with bindings (same issue as MSB2).
- `MONTH()` is not available in SQLite, breaking tests.
- The controller returned raw query objects, but the view expected arrays with `month`, `count`, `volume`, `avg_value`, `mom_change`, and `change`.
**Fix:**
- Used `selectRaw` with bound parameters.
- Added `monthColumn()` helper that emits `strftime('%m', ...)` for SQLite and `MONTH(...)` for MySQL.
- Built `$monthlyData` and `$trends` arrays to match the view contract.
- Removed the obsolete `calculateTrends()` method.

### 5. `/reports/profitability` — 500 type + view shape mismatch
**Root cause:**
- `getCurrentRates(array)` received a `Collection`.
- `ExchangeRate` has no `is_active` column.
- The view expected `position`, `avg_buy_rate`, `avg_sell_rate`, `realized_pnl`, `unrealized_pnl`, and `total_pnl`, while the controller used different keys.
**Fix:**
- Converted `$currencyCodes` to an array with `->values()->toArray()`.
- Removed `->where('is_active', true)` from both rate lookups.
- Mapped position data to the keys the view expects.

### 6. `/reports/customer-analysis` — 500 `Undefined array key "total"`
**Root cause:** Controller returned raw Eloquent collections, while the view expected `$riskDistribution['total/high/medium/low']` and `$customer['name/customer_code/id_number/avg_value']`.
**Fix:** Transformed `riskDistribution` and per-customer arrays in `AnalyticsController::customerAnalysis` to match the view.

### 7. `/reports/compliance-summary` — 500 `Undefined array key "total"`
**Root cause:** Controller returned a collection of flag-type rows; the view expected `$flaggedStats['total']` and `$flaggedStats['by_type']`.
**Fix:** Transformed the query result into the expected structure in `AnalyticsController::complianceSummary`.

### 8. `/users/1` — 500 missing `check` SVG icon
**Root cause:** Blade Icons package registered the `<x-icon>` component, but no default icon set was configured and no `check.svg` existed.
**Fix:**
- Added `config/blade-icons.php` with a default set pointing to `resources/svg`.
- Added `resources/svg/check.svg` and `resources/svg/x.svg`.

## Not a broken page

- `/transactions/template` returns a CSV download (`Content-Disposition: attachment`). Playwright reports it as a download event, but the endpoint itself returns `200 OK`. It was excluded from the broken-links list.

## Files changed

- `app/Http/Controllers/Report/AnalyticsController.php`
- `app/Http/Controllers/Report/RegulatoryReportController.php`
- `app/Services/System/TestRunnerService.php`
- `resources/views/reports/msb2/index.blade.php`
- `config/blade-icons.php` (new)
- `resources/svg/check.svg` (new)
- `resources/svg/x.svg` (new)
- `tests/Feature/AdminReportSmokeTest.php` (new)

## Verification

### Browser/curl smoke checks (after fixes, `APP_DEBUG=false`)

```
http://local.host/reports/msb2                200
http://local.host/test-results                200
http://local.host/reports/monthly-trends      200
http://local.host/reports/profitability       200
http://local.host/reports/customer-analysis   200
http://local.host/reports/compliance-summary  200
http://local.host/users/1                     200
```

### Automated tests

```bash
php artisan test --compact
```

Result: **1,198 passed**, 5 skipped, 1 deprecated.

## Conclusion

All admin-facing pages reachable during the crawl now load successfully. The fixes are covered by a new feature test, and the full test suite remains green. `APP_DEBUG` has been restored to `false`.
