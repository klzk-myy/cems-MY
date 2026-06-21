# Controller Audit Fixes — Phase 2 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix critical runtime bug, unused injections, N+1 queries, and unused imports across controllers.

**Architecture:** 5 tasks prioritized by severity: critical runtime bug first, then high-priority dead code and performance issues.

**Tech Stack:** Laravel 10, PHP 8.3, PHPUnit 10

## Global Constraints

- PHP 8.3.30, Laravel 10, PHPUnit 10
- Run `vendor/bin/pint --dirty --format agent` before every commit
- Run affected tests after each task
- Preserve exact API response shapes — no breaking changes

---

### Task 1: Fix MonthEndCloseController missing Request import (CRITICAL)

**Files:**
- Modify: `app/Http/Controllers/Api/V1/MonthEndCloseController.php`

**Context:** `status()` method type-hints `Request` but `Illuminate\Http\Request` is not imported. Will cause fatal error at runtime.

- [ ] **Step 1: Add the missing import**

Read `app/Http/Controllers/Api/V1/MonthEndCloseController.php` and add `use Illuminate\Http\Request;` to the imports.

- [ ] **Step 2: Run tests**

```bash
php artisan test --compact --filter=MonthEndClose
```

- [ ] **Step 3: Run Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Api/V1/MonthEndCloseController.php
git commit -m "fix: add missing Request import to MonthEndCloseController (runtime bug)"
```

---

### Task 2: Remove unused constructor injections

**Files:**
- Modify: `app/Http/Controllers/Api/V1/CounterOpeningController.php`
- Modify: `app/Http/Controllers/Api/V1/ReportController.php`
- Modify: `app/Http/Controllers/Report/AnalyticsController.php`

**Context:** CounterOpeningController has 3 unused injections, ReportController has 1, AnalyticsController has 1 unused ThresholdService import/injection.

- [ ] **Step 1: Read CounterOpeningController and remove unused injections**

Remove `BranchPoolService`, `TellerAllocationService`, `CounterService` from constructor and their use statements.

- [ ] **Step 2: Read ReportController and remove unused ExportService injection**

- [ ] **Step 3: Read AnalyticsController and remove unused ThresholdService injection and import**

- [ ] **Step 4: Run tests**

```bash
php artisan test --compact --filter="CounterOpening|Report|Analytics"
```

- [ ] **Step 5: Run Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Api/V1/CounterOpeningController.php app/Http/Controllers/Api/V1/ReportController.php app/Http/Controllers/Report/AnalyticsController.php
git commit -m "fix: remove 5 unused constructor injections from controllers"
```

---

### Task 3: Fix N+1 in TestResultsController and RiskDashboardController

**Files:**
- Modify: `app/Http/Controllers/TestResultsController.php`
- Modify: `app/Http/Controllers/RiskDashboardController.php`

**Context:** TestResultsController::buildLatestBySuite() runs a DB query per test suite inside map loop. RiskDashboardController::getAlertVolumeTrend() runs count query per month inside loop.

- [ ] **Step 1: Read TestResultsController and batch the query**

Replace per-suite query with a single grouped query that fetches latest run per suite.

- [ ] **Step 2: Read RiskDashboardController and batch the alert volume query**

Replace per-month count with a single grouped query: `Alert::whereYear(...)->selectRaw('MONTH(created_at) as month, COUNT(*) as count')->groupBy('month')->get()`

- [ ] **Step 3: Run tests**

```bash
php artisan test --compact --filter="TestResults|RiskDashboard"
```

- [ ] **Step 4: Run Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/TestResultsController.php app/Http/Controllers/RiskDashboardController.php
git commit -m "fix: batch N+1 queries in TestResultsController and RiskDashboardController"
```

---

### Task 4: Fix N+1 in API AlertController::overdue()

**Files:**
- Modify: `app/Http/Controllers/Api/V1/Compliance/AlertController.php`

**Context:** `overdue()` loads all non-case alerts into memory then filters with PHP `isOverdue()`. Should push the overdue check into SQL.

- [ ] **Step 1: Read AlertController and check Alert model for overdue scope**

Check if Alert model has an `isOverdue()` method or scope. If it accesses `sla_deadline` or similar column, add a where clause.

- [ ] **Step 2: Push overdue filter into SQL**

Replace `$alerts->filter(fn ($alert) => $alert->isOverdue())` with a query-level where clause.

- [ ] **Step 3: Run tests**

```bash
php artisan test --compact --filter=AlertController
```

- [ ] **Step 4: Run Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Api/V1/Compliance/AlertController.php
git commit -m "fix: push overdue filter into SQL query in AlertController"
```

---

### Task 5: Remove unused Request imports from API controllers

**Files:**
- Modify: `app/Http/Controllers/Api/V1/SanctionListController.php`
- Modify: `app/Http/Controllers/Api/V1/SanctionController.php`
- Modify: `app/Http/Controllers/Api/V1/BranchController.php`

**Context:** 3 controllers import `Request` and type-hint it but never read `$request`. Remove the parameter and import where possible.

- [ ] **Step 1: Read each controller and remove unused Request parameters**

For methods that dont use $request, remove the parameter. If no methods in the controller use Request, remove the import.

- [ ] **Step 2: Run tests**

```bash
php artisan test --compact --filter="SanctionList|Sanction|Branch"
```

- [ ] **Step 3: Run Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Api/V1/SanctionListController.php app/Http/Controllers/Api/V1/SanctionController.php app/Http/Controllers/Api/V1/BranchController.php
git commit -m "fix: remove unused Request imports from 3 API controllers"
```
