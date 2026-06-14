# Codebase Consistency Cleanup Specification

**Date:** 2026-04-30
**Project:** CEMS-MY (Currency Exchange Management System)
**Status:** Draft

---

## 1. Executive Summary

Consolidate duplicate API routes, remove orphaned code, fix controller-route-view consistency gaps, and migrate controllers to proper dependency injection patterns. The goal is a single clean API surface (`/api/v1/...`), zero orphaned code, consistent MVC architecture, and eliminated duplication.

---

## 2. Route Consolidation

### 2.1 Problem

Two API route files loaded simultaneously:
- `routes/api.php` (deprecated, `/api/...`) — 180 lines
- `routes/api_v1.php` (`/api/v1/...`) — 312 lines

~80% of routes are duplicated. Both loaded via `RouteServiceProvider`.

### 2.2 Routes Unique to api.php

These routes exist ONLY in the deprecated `api.php` and must be migrated:

| Route | Controller | Middleware |
|-------|-----------|-----------|
| `POST /api/wizard/transactions/step1` | `TransactionWizardController::step1` | `auth:sanctum`, `role:teller` |
| `POST /api/wizard/transactions/step2` | `TransactionWizardController::step2` | `auth:sanctum`, `role:teller` |
| `POST /api/wizard/transactions/step3` | `TransactionWizardController::step3` | `auth:sanctum`, `role:teller` |
| `GET /api/wizard/transactions/{sessionId}/status` | `TransactionWizardController::status` | `auth:sanctum`, `role:teller` |
| `DELETE /api/wizard/transactions/{sessionId}` | `TransactionWizardController::cancel` | `auth:sanctum`, `role:teller` |
| `POST /api/webhooks/sanctions/update` | `SanctionsWebhookController::__invoke` | Public (token-based) |
| `GET /api/webhooks/sanctions/health` | `SanctionsWebhookController::health` | Public (token-based) |

### 2.3 Migration Actions

1. Add wizard routes to `api_v1.php` under `wizard/transactions/` prefix with `role:teller` middleware
2. Move sanctions webhook routes to a separate public section at the top of `api_v1.php` (no `auth:sanctum` middleware since they use token auth)
3. Remove `RouteServiceProvider` reference to `routes/api.php`
4. Delete `routes/api.php`

---

## 3. Duplicate Business Logic → Service Consolidation

### 3.1 RateController::copyPrevious() — Consolidate into RateManagementService

**Files affected:**
- `app/Http/Controllers/RateController.php` (web, lines 71-137) — has full business logic
- `app/Http/Controllers/Api/V1/RateController.php` (API, lines 155-212) — has duplicated full business logic

**Action:** Move the `copyPrevious()` logic into `RateManagementService` with a method signature:
```php
public function copyPreviousRates(?int $branchId = null): array
```
Both controllers delegate to this method.

### 3.2 StrController::store() — Consolidate into StrReportService

**Files affected:**
- `app/Http/Controllers/StrController.php` (web, lines 73-119)
- `app/Http/Controllers/Api/V1/StrController.php` (API, lines 44-95)

Both call `StrReport::create(...)` directly with the same fields and call `$this->complianceService->calculateStrDeadline(now())`.

**Action:** Add a `createStrReport(array $data)` method to `StrReportService`. Both controllers delegate to it. The service method returns the created `StrReport`.

### 3.3 CustomerController::search() — Consolidate into CustomerService

**Files affected:**
- `app/Http/Controllers/CustomerController.php` (web, lines 33-88)
- `app/Http/Controllers/Api/V1/CustomerController.php` (API, lines 233-289)

Both have identical search logic: query building, blind-index fallback, CDD/CddLevel mapping, sanction checks, same response structure.

**Action:** Add `searchCustomers(string $query)` to `CustomerService` returning a standardized collection.

### 3.4 BranchController CRUD — Consolidate into BranchService

**Files affected:**
- `app/Http/Controllers/BranchController.php` (web) — zero service delegation
- `app/Http/Controllers/Api/V1/BranchController.php` (API) — zero service delegation

**Action:** Create `BranchService` with full CRUD methods. Both controllers delegate.

### 3.5 Transaction Cancellation Workflow — Align Web with API

**Problem:** Web `TransactionCancellationController` uses a single-step `cancel()` method. API uses 3-step workflow (`requestCancellation` → `approveCancellation` / `rejectCancellation`).

**Action:** Update web `TransactionCancellationController` to use the same 3-step workflow as API V1:
- Replace single `cancel()` with `requestCancellation()`, `approveCancellation()`, `rejectCancellation()`
- Remove `showCancel()` and `cancel()` methods
- Update `routes/web.php` to match

---

## 4. Remove Orphaned/Unused Code

### 4.1 Unreferenced Controller Method

| File | Method | Action |
|------|--------|--------|
| `app/Http/Controllers/Api/V1/FiscalYearController.php` | `periods()` | Remove the method (no route references it) |

### 4.2 Completely Unused Services

| Service | Action |
|---------|--------|
| `app/Services/ComplianceAlertService.php` | Remove (zero references anywhere; AlertTriageService handles this) |
| `app/Services/RiskCalculationService.php` legacy methods | Remove fallback methods: `legacyCalculateVelocityRisk()`, `legacyCalculateStructuringRisk()`, `legacyCalculateGeographicRisk()`, `legacyCalculateAmountRisk()` (never called since services are always injected) |

### 4.3 Unused Public Methods

| File | Method | Action |
|------|--------|--------|
| `TransactionMonitoringService` | `getOpenFlags()` | Remove (never called) |
| `TransactionMonitoringService` | `assignFlag()` | Remove (handled by AlertTriageService) |
| `TransactionMonitoringService` | `resolveFlag()` | Remove (handled by AlertTriageService) |
| `TransactionCancellationService` | `cancelTransaction()` | Remove (always throws RuntimeException) |

---

## 5. Fix Critical Gaps

### 5.1 Missing EncryptionService Injection

**File:** `app/Http/Controllers/CustomerController.php`

**Problem:** `edit()` method calls `$this->encryptionService->decrypt()` but `EncryptionService` is not injected in the constructor.

**Action:** Add `EncryptionService $encryptionService` to the constructor parameters and store as `$this->encryptionService`.

### 5.2 Missing Views

| Route/Controller | View Referenced | Expected Path | Action |
|-----------------|----------------|---------------|--------|
| `MonthEndCloseController::index()` | `accounting.month-end` | `resources/views/accounting/month-end.blade.php` | Create view |
| `TransactionReportController::exportToPdf()` | `transactions.export.customer-history-pdf` | `resources/views/transactions/export/customer-history-pdf.blade.php` | Create view |
| `AuditController::exportToPdf()` | `audit.pdf` | `resources/views/audit/pdf.blade.php` | Create view |

### 5.3 Wrong View Path

**File:** `app/Http/Controllers/DashboardController.php:268`

**Problem:** `return view('reports', compact('recentReports'));` — no `reports.blade.php` exists. Actual file is `reports/index.blade.php`.

**Action:** Change to `return view('reports.index', compact('recentReports'));`

### 5.4 Inconsistent Enum/String Usage in StrController

**Files affected:**
- `app/Http/Controllers/StrController.php` (web)
- `app/Http/Controllers/Api/V1/StrController.php` (API)

**Problem:** Mixes hardcoded strings like `'status' => 'draft'` with `StrStatus::Submitted->value`.

**Action:** Replace ALL hardcoded status strings with `StrStatus::*->value`

---

## 6. Fix Dependency Injection Patterns

### 6.1 Controllers Using app() Service Locator

| Controller | Line(s) | Fix |
|-----------|---------|-----|
| `CounterController` | 341, 365, 433 | Inject `EmergencyCounterService` and `CounterHandoverService` via constructor |

### 6.2 Controllers Using `new` Instead of DI

| Controller | Line | Fix |
|-----------|------|-----|
| `StockTransferController` | 21 | Inject `StockTransferService` via constructor |
| `TransactionBatchController` | 85-92 | Inject `TransactionImportService` via constructor |
| `StockCashController` | 30-31 | Inject `CurrencyPositionService` via constructor |

---

## 7. Remove Deprecated Route File

### 7.1 Steps

1. All unique routes migrated to `api_v1.php` (Section 2)
2. Remove `Route::middleware('api')->prefix('api')->group(base_path('routes/api.php'));` from `RouteServiceProvider`
3. Delete `routes/api.php`

### 7.2 Impact

- Reduces active route files from 4 to 3 (web.php, api_v1.php, auth.php)
- Eliminates duplicate API surface at `/api/...`
- Single source of truth for API routes at `/api/v1/...`

---

## 8. Files to Modify

### Route Files
- `routes/api_v1.php` — add wizard + webhook routes
- `routes/api.php` — DELETE
- `app/Providers/RouteServiceProvider.php` — remove api.php reference

### Controllers
- `app/Http/Controllers/RateController.php` — delegate to RateManagementService
- `app/Http/Controllers/Api/V1/RateController.php` — delegate to RateManagementService
- `app/Http/Controllers/StrController.php` — delegate to StrReportService, fix enums
- `app/Http/Controllers/Api/V1/StrController.php` — delegate to StrReportService, fix enums
- `app/Http/Controllers/CustomerController.php` — delegate search, inject EncryptionService
- `app/Http/Controllers/Api/V1/CustomerController.php` — delegate search
- `app/Http/Controllers/BranchController.php` (web) — delegate to BranchService
- `app/Http/Controllers/Api/V1/BranchController.php` — delegate to BranchService
- `app/Http/Controllers/Transaction/TransactionCancellationController.php` — align with 3-step workflow
- `app/Http/Controllers/CounterController.php` — fix DI
- `app/Http/Controllers/StockTransferController.php` — fix DI
- `app/Http/Controllers/TransactionBatchController.php` — fix DI
- `app/Http/Controllers/StockCashController.php` — fix DI
- `app/Http/Controllers/DashboardController.php` — fix view path
- `app/Http/Controllers/Api/V1/FiscalYearController.php` — remove unreferenced periods()
- `app/Http/Controllers/MonthEndCloseController.php` — already references correct view? (needs creation)
- `app/Http/Controllers/TransactionReportController.php` — needs view creation
- `app/Http/Controllers/AuditController.php` — needs view creation

### Services
- `app/Services/RateManagementService.php` — add copyPreviousRates()
- `app/Services/StrReportService.php` — add createStrReport()
- `app/Services/CustomerService.php` — add searchCustomers()
- `app/Services/BranchService.php` — CREATE with full CRUD
- `app/Services/ComplianceAlertService.php` — DELETE
- `app/Services/RiskCalculationService.php` — remove legacy fallback methods
- `app/Services/TransactionMonitoringService.php` — remove 3 unused methods
- `app/Services/TransactionCancellationService.php` — remove cancelTransaction()

### Views (CREATE)
- `resources/views/accounting/month-end.blade.php`
- `resources/views/transactions/export/customer-history-pdf.blade.php`
- `resources/views/audit/pdf.blade.php`

### Routes Update
- `routes/web.php` — update cancel routes to 3-step workflow

---

## 9. Testing Requirements

- Full test suite must pass after changes: `php artisan test`
- Linting must pass: `./vendor/bin/pint`
- No change in behavior for migrated routes (webhook and wizard must work at new prefix)
- Cancellation workflow tests must pass with both web and API paths