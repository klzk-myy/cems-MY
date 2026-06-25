# Laravel Violations Comprehensive Fix - Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix all 38 identified Laravel best practice violations (12 Critical, 18 Major, 8 Minor) across Security, Database, Architecture, and Jobs/Queues categories in phased approach.

**Architecture:** Fixes are organized in 3 phases:
- Phase 1: Critical issues (all categories) - security/safety risks
- Phase 2: Major issues category-by-category
- Phase 3: Minor issues batch

**Tech Stack:** Laravel 10.x, PHP 8.1, MySQL, Redis queues

---

## File Map

### Security Fixes
| File | Issue | Fix |
|------|-------|-----|
| `app/Http/Controllers/Report/RegulatoryReportController.php:183-188` | DB::raw string concat | Use query builder with conditional expressions |
| `app/Http/Controllers/Report/AnalyticsController.php:54-55` | DB::raw string concat | Use query builder |
| `app/Services/ReportingService.php:95-98` | DB::raw hardcoded strings | Use enum values with bindings |
| `app/Http/Controllers/Api/V1/Compliance/RiskController.php:117` | Raw SQL without binding | Use query builder |
| `app/Http/Controllers/Api/V1/Compliance/DashboardController.php:101` | XSS via echo | Use proper response with encoding |
| `config/cors.php:18-32` | CORS wildcard origin | Use explicit origin list |
| `routes/api_v1.php:52-55` | Webhook auth bypass | Add throttle middleware |
| `app/Models/User.php:45-57` | Sensitive $fillable | Remove sensitive fields |
| `app/Http/Controllers/Api/V1/CustomerController.php:26` | LIKE without escaping | Escape special chars |

### Database Fixes
| File | Issue | Fix |
|------|-------|-----|
| `app/Services/TransactionMonitoringService.php:128-130` | N+1 queries | Add eager loading |
| `app/Services/BankReconciliationService.php:154-171` | N+1 loop | Refactor to single query with subquery |
| `app/Services/EodReconciliationService.php:83-92` | PHP filter vs DB | Use database-level filtering |
| `app/Services/ComplianceReportingService.php:86` | all() + PHP filter | Use whereIn with count |
| `app/Services/BankReconciliationService.php:148-152` | Mass without chunking | Use chunk() |
| `app/Http/Controllers/Compliance/UnifiedAlertController.php:64` | Missing eager load | Add flaggedTransaction to with() |

### Architecture Fixes
| File | Issue | Fix |
|------|-------|-----|
| `app/Http/Controllers/SetupController.php` (487 lines) | Controller bloat | Extract to SetupService |
| `app/Http/Controllers/CounterController.php` (412 lines) | Controller bloat | Extract CounterWorkflowService |
| `app/Http/Controllers/CustomerController.php` (409 lines) | Controller bloat | Extract CustomerSearchService |
| `app/Http/Controllers/StockCashController.php` (366 lines) | Controller bloat | Extract TillManagementService |
| `app/Http/Controllers/TransactionReportController.php` (353 lines) | Controller bloat | Extract ReportGenerationService |
| `app/Http/Controllers/Api/V1/TellerAllocationController.php` (329 lines) | Controller bloat | Extract TellerAllocationService |
| `app/Http/Controllers/Report/RegulatoryReportController.php` (460 lines) | Controller bloat + 11 inline validates | Extract validation to FormRequests + service |
| `app/Http/Controllers/AccountingController.php` (7 inline validates) | Inline validates | Extract to FormRequests |
| `app/Http/Controllers/Compliance/CaseManagementController.php` (5 inline validates) | Inline validates | Extract to FormRequests |
| `app/Http/Controllers/CounterController.php` (2 inline validates) | Inline validates | Extract to FormRequests |
| `app/Http/Controllers/StrController.php` (inline validates) | Inline validates | Extract to FormRequests |
| `app/Services/TransactionService.php` (991 lines) | Monolithic | Split into creation/validation/state services |
| `app/Services/TransactionCancellationService.php` (836 lines) | Monolithic | Split by workflow step |
| `app/Services/AuditService.php` (828 lines) | Monolithic | Split hash sealing into separate service |
| `app/Services/LedgerService.php` (640 lines) | Monolithic | Split posting/reversal |
| `app/Services/ReportingService.php` (606 lines) | Monolithic | Split by report type |
| `app/Services/ComplianceService.php` (590 lines) | Monolithic | Split CDD/sanctions/velocity |
| `app/Services/CounterService.php` (569 lines) | Monolithic | Split session/balance management |
| `app/Services/ComplianceService.php:84` | Improper DI | Constructor injection only |
| `app/Services/RateApiService.php:28` | Improper DI | Constructor injection only |

### Jobs/Queues Fixes
| File | Issue | Fix |
|------|-------|-----|
| `app/Services/AuditService.php:821-823` | Job dispatch in loop | Use Bus::batch() |
| `app/Listeners/TriggerSanctionsRescreening.php:100-102` | Sync rescreening | Dispatch async job per customer |
| `app/Jobs/RescreenHighRiskCustomersJob.php:34-46` | Foreach without batch | Use Bus::batch() with chunking |
| `app/Providers/EventServiceProvider.php` | Missing job events | Add Queue event listeners |
| `app/Jobs/SendNotificationJob.php:195-202` | Missing ShouldBeUnique | Add ShouldBeUnique interface |
| `app/Jobs/Accounting/ReconcileDeferredAccountingJob.php:118-149` | Foreach without chunking | Use chunk() |
| `config/horizon.php:226` | Timeout mismatch | Increase to 3600 |
| `app/Jobs/ProcessCustomerImport.php` | Missing tags | Add tags() method |
| `app/Jobs/ProcessTransactionImport.php` | Missing tags | Add tags() method |

---

## Phase 1: Critical Fixes

### Task 1: Fix SQL Injection - DB::raw String Concatenation

**Files:**
- Modify: `app/Http/Controllers/Report/RegulatoryReportController.php:183-188`
- Modify: `app/Http/Controllers/Report/AnalyticsController.php:54-55`
- Modify: `app/Services/ReportingService.php:95-98`

- [ ] **Step 1: Examine RegulatoryReportController DB::raw usage**

Run: `grep -n "DB::raw" /www/wwwroot/local.host/app/Http/Controllers/Report/RegulatoryReportController.php`
Read lines 180-200

- [ ] **Step 2: Refactor to query builder with conditional expressions**

Replace pattern:
```php
DB::raw("SUM(CASE WHEN type = '".TransactionType::Buy->value."' THEN amount_foreign ELSE 0 END)")
```

With:
```php
DB::raw("SUM(CASE WHEN type = 'Buy' THEN amount_foreign ELSE 0 END)")
// Use literal enum values - safe since they are constants
// Alternative: use when() fluent method
```

- [ ] **Step 3: Run tests to verify no regression**

Run: `php artisan test --filter=RegulatoryReportController`
Expected: PASS

- [ ] **Step 4: Fix AnalyticsController same pattern**

Read `/www/wwwroot/local.host/app/Http/Controllers/Report/AnalyticsController.php` lines 50-60
Apply same fix pattern

- [ ] **Step 5: Fix ReportingService**

Read `/www/wwwroot/local.host/app/Services/ReportingService.php` lines 90-100
Apply same fix pattern using enum values instead of string literals

- [ ] **Step 6: Run full test suite**

Run: `php artisan test`
Expected: All tests pass

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Report/RegulatoryReportController.php app/Http/Controllers/Report/AnalyticsController.php app/Services/ReportingService.php
git commit -m "fix: replace DB::raw string concatenation with safe enum values"
```

---

### Task 2: Fix SQL Injection - Raw SQL Without Binding

**Files:**
- Modify: `app/Http/Controllers/Api/V1/Compliance/RiskController.php:117`

- [ ] **Step 1: Examine RiskController raw SQL**

Read `/www/wwwroot/local.host/app/Http/Controllers/Api/V1/Compliance/RiskController.php` lines 110-125

- [ ] **Step 2: Refactor to query builder**

Replace:
```php
$all = DB::select('SELECT risk_tier, COUNT(*) as count FROM customer_risk_profiles GROUP BY risk_tier');
```

With:
```php
$all = DB::table('customer_risk_profiles')
    ->select('risk_tier', DB::raw('COUNT(*) as count'))
    ->groupBy('risk_tier')
    ->get();
```

- [ ] **Step 3: Verify with tests**

Run: `php artisan test --filter=RiskController`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/Api/V1/Compliance/RiskController.php
git commit -m "fix: replace raw SQL with query builder in RiskController"
```

---

### Task 3: Fix XSS via Unescaped Echo

**Files:**
- Modify: `app/Http/Controllers/Api/V1/Compliance/DashboardController.php:101`

- [ ] **Step 1: Examine DashboardController streamDownload**

Read `/www/wwwroot/local.host/app/Http/Controllers/Api/V1/Compliance/DashboardController.php` lines 95-110

- [ ] **Step 2: Replace echo with proper output**

Replace:
```php
return response()->streamDownload(function () use ($csv) {
    echo $csv;
}, $filename, [
    'Content-Type' => 'text/csv',
]);
```

With:
```php
return response()->streamDownload(function () use ($csv) {
    $handle = fopen('php://output', 'w');
    fwrite($handle, $csv);
    fclose($handle);
}, $filename, [
    'Content-Type' => 'text/csv',
    'Content-Disposition' => 'attachment; filename="' . $filename . '"',
]);
```

- [ ] **Step 3: Run tests**

Run: `php artisan test --filter=DashboardController`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/Api/V1/Compliance/DashboardController.php
git commit -m "fix: use proper output buffering for CSV download"
```

---

### Task 4: Fix CORS Wildcard Origin

**Files:**
- Modify: `config/cors.php:18-32`

- [ ] **Step 1: Examine current CORS config**

Read `/www/wwwroot/local.host/config/cors.php`

- [ ] **Step 2: Replace wildcard with environment-based origins**

Replace:
```php
'allowed_origins' => ['*'],
```

With:
```php
'allowed_origins' => array_filter([
    env('CORS_ALLOWED_ORIGIN_1'),
    env('CORS_ALLOWED_ORIGIN_2'),
    env('CORS_ALLOWED_ORIGIN_3'),
]),
```

Add to `.env.example`:
```
CORS_ALLOWED_ORIGIN_1=http://localhost:3000
CORS_ALLOWED_ORIGIN_2=https://admin.local.host
```

- [ ] **Step 3: Verify routes still work**

Run: `php artisan route:list | grep -i cors`
Run: `php artisan test --filter=Cors`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add config/cors.php
git commit -m "fix: replace CORS wildcard with explicit origins from env"
```

---

### Task 5: Fix Webhook Auth Bypass

**Files:**
- Modify: `routes/api_v1.php:52-55`

- [ ] **Step 1: Examine webhook routes**

Read `/www/wwwroot/local.host/routes/api_v1.php` lines 50-60

- [ ] **Step 2: Add throttle middleware to health endpoint**

Find:
```php
Route::get('/webhooks/sanctions/health', [SanctionsWebhookController::class, 'health'])
    ->name('api.v1.webhooks.sanctions.health');
```

Replace with:
```php
Route::get('/webhooks/sanctions/health', [SanctionsWebhookController::class, 'health'])
    ->middleware('throttle:30,1')
    ->name('api.v1.webhooks.sanctions.health');
```

- [ ] **Step 3: Run tests**

Run: `php artisan test --filter=Webhook`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add routes/api_v1.php
git commit -m "fix: add rate limiting to webhook health endpoint"
```

---

### Task 6: Fix Mass Assignment - User Model Sensitive Fields

**Files:**
- Modify: `app/Models/User.php:45-57`

- [ ] **Step 1: Examine User $fillable**

Read `/www/wwwroot/local.host/app/Models/User.php` lines 40-60

- [ ] **Step 2: Remove sensitive fields from $fillable**

Current:
```php
protected $fillable = [
    'branch_id',
    'username',
    'email',
    'password',
    'password_hash',
    'role',           // SENSITIVE
    'mfa_enabled',    // SENSITIVE
    'mfa_secret',     // SENSITIVE
    'mfa_verified_at',// SENSITIVE
    'is_active',
    'last_login_at',
];
```

Replace with:
```php
protected $fillable = [
    'branch_id',
    'username',
    'email',
    'password',
    'password_hash',
    'is_active',
    'last_login_at',
];

protected $guarded = [
    'role',
    'mfa_enabled',
    'mfa_secret',
    'mfa_verified_at',
];
```

- [ ] **Step 3: Run tests**

Run: `php artisan test --filter=UserTest`
Expected: PASS (may need updates if tests rely on mass assignment for role/MFA)

- [ ] **Step 4: Commit**

```bash
git add app/Models/User.php
git commit -m "fix: protect sensitive fields from mass assignment in User model"
```

---

### Task 7: Fix LIKE Query Without Escaping

**Files:**
- Modify: `app/Http/Controllers/Api/V1/CustomerController.php:26`

- [ ] **Step 1: Examine LIKE query**

Read `/www/wwwroot/local.host/app/Http/Controllers/Api/V1/CustomerController.php` lines 20-35

- [ ] **Step 2: Add LIKE escape helper**

Replace direct usage:
```php
$query->where('full_name', 'like', '%'.$request->search.'%');
```

With:
```php
$searchTerm = str_replace(['%', '_'], ['\%', '\_'], $request->search);
$query->where('full_name', 'like', '%'.$searchTerm.'%');
```

- [ ] **Step 3: Run tests**

Run: `php artisan test --filter=CustomerController`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/Api/V1/CustomerController.php
git commit -m "fix: escape LIKE special characters in customer search"
```

---

### Task 8: Fix Controller Bloat - SetupController (487 lines)

**Files:**
- Create: `app/Services/SetupService.php`
- Modify: `app/Http/Controllers/SetupController.php`
- Create: `app/Http/Requests/Setup/SetupStepRequest.php` (per step)

- [ ] **Step 1: Examine SetupController**

Read `/www/wwwroot/local.host/app/Http/Controllers/SetupController.php`
Identify business logic that should be in service (database seeding, configuration, branch creation)

- [ ] **Step 2: Create SetupService**

```php
// app/Services/SetupService.php
namespace App\Services;

class SetupService
{
    public function __construct(
        protected BranchService $branchService,
        protected UserService $userService,
        protected ChartOfAccountService $chartOfAccountService,
    ) {}

    public function createBranch(array $data): Branch { ... }
    public function createAdminUser(array $data, Branch $branch): User { ... }
    public function seedChartOfAccounts(Branch $branch): void { ... }
    public function completeSetup(Branch $branch, User $admin): SetupResult { ... }
}
```

- [ ] **Step 3: Refactor SetupController to use service**

Inject SetupService via constructor, delegate business logic

- [ ] **Step 4: Create FormRequest classes**

Create `app/Http/Requests/Setup/` directory with FormRequest per step

- [ ] **Step 5: Run tests**

Run: `php artisan test --filter=SetupController`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Services/SetupService.php app/Http/Controllers/SetupController.php
git add app/Http/Requests/Setup/
git commit -m "refactor: extract SetupController business logic to SetupService"
```

---

### Task 9: Fix Controller Bloat - CounterController (412 lines)

**Files:**
- Create: `app/Services/CounterWorkflowService.php`
- Modify: `app/Http/Controllers/CounterController.php`

- [ ] **Step 1: Examine CounterController**

Read `/www/wwwroot/local.host/app/Http/Controllers/CounterController.php`
Identify session management, handover, emergency closure logic

- [ ] **Step 2: Create CounterWorkflowService**

Extract till balance calculations, variance logic, handover processing

- [ ] **Step 3: Refactor CounterController**

Inject service, delegate business logic

- [ ] **Step 4: Run tests**

Run: `php artisan test --filter=CounterController`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/CounterWorkflowService.php app/Http/Controllers/CounterController.php
git commit -m "refactor: extract CounterController business logic to CounterWorkflowService"
```

---

### Task 10: Fix Controller Bloat - CustomerController (409 lines)

**Files:**
- Create: `app/Services/CustomerSearchService.php`
- Modify: `app/Http/Controllers/CustomerController.php`

- [ ] **Step 1: Examine CustomerController**

Read `/www/wwwroot/local.host/app/Http/Controllers/CustomerController.php`
Identify search, quickCreate, KYC logic

- [ ] **Step 2: Create CustomerSearchService**

- [ ] **Step 3: Refactor CustomerController**

- [ ] **Step 4: Run tests**

Run: `php artisan test --filter=CustomerController`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/CustomerSearchService.php app/Http/Controllers/CustomerController.php
git commit -m "refactor: extract CustomerController search logic to CustomerSearchService"
```

---

### Task 11: Fix Controller Bloat - Remaining Controllers

**Files:**
- Create: `app/Services/TillManagementService.php`
- Create: `app/Services/ReportGenerationService.php`
- Create: `app/Services/TellerAllocationService.php`
- Modify: `StockCashController`, `TransactionReportController`, `TellerAllocationController`

- [ ] **Step 1: Examine StockCashController**

Read `/www/wwwroot/local.host/app/Http/Controllers/StockCashController.php` - till management, position tracking

- [ ] **Step 2: Examine TransactionReportController**

Read lines 1-100 to understand report generation patterns

- [ ] **Step 3: Examine TellerAllocationController**

Read lines 1-100

- [ ] **Step 4: Create services and refactor each controller**

- [ ] **Step 5: Run tests**

Run: `php artisan test --filter="StockCashController|TransactionReportController|TellerAllocationController"`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Services/TillManagementService.php app/Services/ReportGenerationService.php app/Services/TellerAllocationService.php
git add app/Http/Controllers/StockCashController.php app/Http/Controllers/TransactionReportController.php app/Http/Controllers/Api/V1/TellerAllocationController.php
git commit -m "refactor: extract business logic from remaining bloated controllers"
```

---

### Task 12: Fix 70+ Inline Validate Calls - Create FormRequest Classes

**Files:**
- Create: `app/Http/Requests/` (multiple FormRequest classes)
- Modify: Controllers with inline validation

- [ ] **Step 1: Find all inline validate calls**

Run: `grep -rn '$request->validate(' app/Http/Controllers/ | wc -l`

- [ ] **Step 2: Create FormRequests for each controller**

For each controller with inline validation, create a dedicated FormRequest:

**RegulatoryReportController** (11 validates) - Create:
- `app/Http/Requests/Report/StoreMsb2ReportRequest.php`
- `app/Http/Requests/Report/StoreLctrReportRequest.php`
- etc.

**AccountingController** (7 validates):
- `app/Http/Requests/Accounting/JournalEntryRequest.php`
- `app/Http/Requests/Accounting/ReversalRequest.php`

**CaseManagementController** (5 validates):
- `app/Http/Requests/Compliance/CaseDocumentRequest.php`
- etc.

**CounterController** (2 validates):
- `app/Http/Requests/Counter/CloseCounterRequest.php`
- `app/Http/Requests/Counter/HandoverRequest.php`

**StrController** (multiple):
- `app/Http/Requests/Str/TrackAcknowledgmentRequest.php`
- etc.

- [ ] **Step 3: Replace inline validate with FormRequest type hinting**

Example:
```php
// Before
public function store(Request $request): RedirectResponse
{
    $validated = $request->validate([...]);
}

// After
public function store(StoreMsb2ReportRequest $request): RedirectResponse
{
    $validated = $request->validated();
}
```

- [ ] **Step 4: Run tests**

Run: `php artisan test`
Expected: All pass

- [ ] **Step 5: Commit**

```bash
git add app/Http/Requests/
git add app/Http/Controllers/Report/RegulatoryReportController.php app/Http/Controllers/AccountingController.php app/Http/Controllers/Compliance/CaseManagementController.php app/Http/Controllers/CounterController.php app/Http/Controllers/StrController.php
git commit -m "refactor: replace 70+ inline validations with dedicated FormRequest classes"
```

---

### Task 13: Fix Monolithic Services - Split TransactionService (991 lines)

**Files:**
- Create: `app/Services/TransactionCreationService.php`
- Create: `app/Services/TransactionValidationService.php`
- Create: `app/Services/TransactionStateService.php`
- Modify: `app/Services/TransactionService.php`

- [ ] **Step 1: Analyze TransactionService methods**

Read `/www/wwwroot/local.host/app/Services/TransactionService.php`
Group methods by responsibility:
- Creation: `createTransaction()`, `prepareTransactionData()`
- Validation: `validateTransactionLimits()`, `validatePosition()`, `checkDuplicate()`
- State: `approveTransaction()`, `cancelTransaction()`, `updateStatus()`

- [ ] **Step 2: Create focused services**

```php
// TransactionCreationService handles build-up
// TransactionValidationService handles all validation rules
// TransactionStateService handles state transitions
```

- [ ] **Step 3: Refactor TransactionService to compose child services**

- [ ] **Step 4: Run tests**

Run: `php artisan test --filter=TransactionService`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/TransactionCreationService.php app/Services/TransactionValidationService.php app/Services/TransactionStateService.php
git add app/Services/TransactionService.php
git commit -m "refactor: split monolithic TransactionService into focused services"
```

---

### Task 14: Fix Monolithic Services - Split Remaining Large Services

**Files:**
- Create: `app/Services/AuditHashService.php` (from AuditService hash sealing)
- Create: `app/Services/LedgerPostingService.php` (from LedgerService posting)
- Create: `app/Services/ComplianceCDDService.php`, `ComplianceSanctionsService.php`, `ComplianceVelocityService.php`
- Modify: `AuditService`, `LedgerService`, `ComplianceService`, `CounterService`, `ReportingService`

- [ ] **Step 1: Examine each service**

For each large service, identify coherent sub-responsibilities

- [ ] **Step 2: Extract to focused services**

- [ ] **Step 3: Run tests**

Run: `php artisan test`
Expected: All pass

- [ ] **Step 4: Commit**

```bash
git add app/Services/
git commit -m "refactor: split remaining monolithic services"
```

---

### Task 15: Fix Job Dispatch in Loop Without Batch

**Files:**
- Modify: `app/Services/AuditService.php:821-823`

- [ ] **Step 1: Examine AuditService logBatch**

Read `/www/wwwroot/local.host/app/Services/AuditService.php` lines 815-830

- [ ] **Step 2: Refactor to Bus::batch**

Replace:
```php
for ($i = 0; $i < $count; $i++) {
    SealAuditHashJob::dispatch($firstId + $i);
}
```

With:
```php
$batch = Bus::batch([])->dispatch();
for ($i = 0; $i < $count; $i++) {
    $batch->add(new SealAuditHashJob($firstId + $i));
}
```

Or use chunked dispatch:
```php
$chunks = collect(range($firstId, $firstId + $count - 1))->chunk(100);
foreach ($chunks as $chunk) {
    Bus::batch($chunk->map(fn($id) => new SealAuditHashJob($id))->toArray())->dispatch();
}
```

- [ ] **Step 3: Run tests**

Run: `php artisan test --filter=AuditService`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add app/Services/AuditService.php
git commit -m "fix: use Bus::batch for bulk audit hash sealing jobs"
```

---

### Task 16: Fix Synchronous Rescreening in Listener

**Files:**
- Modify: `app/Listeners/TriggerSanctionsRescreening.php:100-102`

- [ ] **Step 1: Examine listener**

Read `/www/wwwroot/local.host/app/Listeners/TriggerSanctionsRescreening.php` lines 95-110

- [ ] **Step 2: Refactor to dispatch jobs**

Replace:
```php
foreach ($customersToRescreen as $customer) {
    $this->rescreenCustomerWithTransactionHold($customer, $event);
}
```

With:
```php
foreach ($customersToRescreen as $customer) {
    SanctionsRescreeningJob::dispatch($customer->id, $event->source);
}
```

- [ ] **Step 3: Run tests**

Run: `php artisan test --filter=TriggerSanctionsRescreening`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add app/Listeners/TriggerSanctionsRescreening.php
git commit -m "fix: dispatch async jobs instead of sync rescreening in listener"
```

---

### Task 17: Fix RescreenHighRiskCustomersJob Foreach Without Batch

**Files:**
- Modify: `app/Jobs/RescreenHighRiskCustomersJob.php:34-46`

- [ ] **Step 1: Examine job**

Read `/www/wwwroot/local.host/app/Jobs/RescreenHighRiskCustomersJob.php`

- [ ] **Step 2: Refactor to use batch with chunking**

```php
public function handle(): void
{
    $chunks = Customer::highRisk()->select('id')->chunk(100, function ($customers) {
        Bus::batch(
            $customers->map(fn($c) => new ComplianceScreeningJob($c->id, 'Scheduled high-risk rescreening'))->toArray()
        )->dispatch();
    });
}
```

- [ ] **Step 3: Run tests**

Run: `php artisan test --filter=RescreenHighRiskCustomersJob`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add app/Jobs/RescreenHighRiskCustomersJob.php
git commit -m "fix: use batch processing with chunking for high-risk customer rescreening"
```

---

## Phase 2: Major Fixes

### Task 18: Fix N+1 Queries - TransactionMonitoringService

**Files:**
- Modify: `app/Services/TransactionMonitoringService.php:128-130`

- [ ] **Step 1: Examine N+1 pattern**

Read `/www/wwwroot/local.host/app/Services/TransactionMonitoringService.php` lines 125-150

- [ ] **Step 2: Add eager loading**

Ensure calling code uses `with('customer')` when calling `isUnusualPattern()`

- [ ] **Step 3: Run tests**

Run: `php artisan test --filter=TransactionMonitoring`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add app/Services/TransactionMonitoringService.php
git commit -m "fix: eager load customer relationship in monitoring service"
```

---

### Task 19: Fix N+1 Queries - BankReconciliationService

**Files:**
- Modify: `app/Services/BankReconciliationService.php:154-171`

- [ ] **Step 1: Examine N+1 loop**

Read lines 145-180

- [ ] **Step 2: Refactor to single query with subquery**

Replace loop with single query that finds all matching entries

- [ ] **Step 3: Run tests**

Run: `php artisan test --filter=BankReconciliation`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add app/Services/BankReconciliationService.php
git commit -m "fix: eliminate N+1 query loop in BankReconciliationService"
```

---

### Task 20: Fix Missing Database Indexes

**Files:**
- Create: `database/migrations/2026_05_04_add_missing_indexes.php`

- [ ] **Step 1: Create migration for missing indexes**

```php
// database/migrations/2026_05_04_add_missing_indexes.php
public function up(): void
{
    Schema::table('customer_relations', function (Blueprint $table) {
        $table->index(['customer_id', 'related_customer_id', 'relation_type'], 'customer_relations_composite_idx');
    });

    Schema::table('alerts', function (Blueprint $table) {
        $table->index(['assigned_to', 'case_id', 'status'], 'alerts_composite_idx');
    });

    Schema::table('flagged_transactions', function (Blueprint $table) {
        $table->index(['status', 'created_at'], 'flagged_transactions_status_date_idx');
        $table->index(['flag_type', 'created_at'], 'flagged_transactions_flag_type_date_idx');
    });

    Schema::table('customer_risk_profiles', function (Blueprint $table) {
        $table->index('risk_tier', 'customer_risk_profiles_risk_tier_idx');
    });
}
```

- [ ] **Step 2: Run migration**

Run: `php artisan migrate`
Expected: Migration success

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_05_04_add_missing_indexes.php
git commit -m "fix: add missing database indexes for query performance"
```

---

### Task 21: Fix Inefficient Queries - ComplianceReportingService

**Files:**
- Modify: `app/Services/ComplianceReportingService.php:86`

- [ ] **Step 1: Examine all() + filter pattern**

Read `/www/wwwroot/local.host/app/Services/ComplianceReportingService.php` lines 80-95

- [ ] **Step 2: Replace with whereIn query**

```php
// Before
$all = StrReport::all();
$pending = $all->whereIn('status', [StrStatus::Draft, ...])->count();

// After
$pending = StrReport::whereIn('status', [
    StrStatus::Draft,
    StrStatus::PendingReview,
    StrStatus::PendingApproval,
])->count();
```

- [ ] **Step 3: Run tests**

Run: `php artisan test --filter=ComplianceReporting`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add app/Services/ComplianceReportingService.php
git commit -m "fix: use database-level filtering instead of all() + PHP filter"
```

---

### Task 22: Fix Mass Queries Without Chunking - BankReconciliationService

**Files:**
- Modify: `app/Services/BankReconciliationService.php:148-152`

- [ ] **Step 1: Examine autoMatch method**

Read lines 145-155

- [ ] **Step 2: Add chunking**

Replace `->get()` with `->chunkById(100, function ($records) { ... })`

- [ ] **Step 3: Run tests**

Run: `php artisan test --filter=BankReconciliation`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add app/Services/BankReconciliationService.php
git commit -m "fix: add chunking for large bank reconciliation datasets"
```

---

### Task 23: Fix Missing Eager Loading - UnifiedAlertController

**Files:**
- Modify: `app/Http/Controllers/Compliance/UnifiedAlertController.php:64`

- [ ] **Step 1: Examine alert loading**

Read `/www/wwwroot/local.host/app/Http/Controllers/Compliance/UnifiedAlertController.php` lines 60-75

- [ ] **Step 2: Add flaggedTransaction to eager loading**

```php
$query = Alert::with(['customer', 'assignedTo', 'flaggedTransaction']);
```

- [ ] **Step 3: Run tests**

Run: `php artisan test --filter=UnifiedAlertController`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/Compliance/UnifiedAlertController.php
git commit -m "fix: eager load flaggedTransaction relationship in UnifiedAlertController"
```

---

### Task 24: Add Service Interfaces

**Files:**
- Create: `app/Services/Contracts/` (interface directory)
- Create: `TransactionServiceInterface.php`, `ComplianceServiceInterface.php`, etc.
- Modify: `AppServiceProvider.php`

- [ ] **Step 1: Create interfaces for core services**

```php
// app/Services/Contracts/TransactionServiceInterface.php
interface TransactionServiceInterface
{
    public function createTransaction(...): Transaction;
    public function approveTransaction(...): Transaction;
    // ...
}
```

- [ ] **Step 2: Bind interfaces in AppServiceProvider**

```php
public function register(): void
{
    $this->app->bind(TransactionServiceInterface::class, TransactionService::class);
    // ...
}
```

- [ ] **Step 3: Update controllers to type-hint interfaces**

- [ ] **Step 4: Run tests**

Run: `php artisan test`
Expected: All pass

- [ ] **Step 5: Commit**

```bash
git add app/Services/Contracts/
git add app/Providers/AppServiceProvider.php
git add app/Services/TransactionService.php app/Services/ComplianceService.php # add interface implements
git commit -m "feat: add service interfaces for testability and abstraction"
```

---

### Task 25: Add Void Return Types to Service Methods

**Files:**
- Modify: All service files with methods lacking return types

- [ ] **Step 1: Find all service methods without return types**

Run: `grep -rn "public function" app/Services/ | grep -v ":" | head -50`

- [ ] **Step 2: Add `: void` to void methods, proper types to others**

Example:
```php
// Before
public function markErrorResolved(Transaction $transaction, string $errorType)

// After
public function markErrorResolved(Transaction $transaction, string $errorType): void
```

- [ ] **Step 3: Run tests**

Run: `php artisan test`
Expected: All pass

- [ ] **Step 4: Commit**

```bash
git add app/Services/
git commit -m "chore: add return type declarations to service methods"
```

---

### Task 26: Fix Improper DI - Nullable Constructor Parameters

**Files:**
- Modify: `app/Services/ComplianceService.php:84`
- Modify: `app/Services/RateApiService.php:28`

- [ ] **Step 1: Examine improper DI patterns**

Read ComplianceService constructor

- [ ] **Step 2: Fix to require dependencies**

```php
// Before
public function __construct(
    protected ThresholdService $thresholdService = null
) {
    $this->thresholdService = $thresholdService ?? new ThresholdService;
}

// After
public function __construct(
    protected ThresholdService $thresholdService,
    // other required services
) {}
```

- [ ] **Step 3: Run tests**

Run: `php artisan test --filter="ComplianceService|RateApiService"`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add app/Services/ComplianceService.php app/Services/RateApiService.php
git commit -m "fix: require dependencies via constructor instead of nullable with fallback"
```

---

### Task 27: Add Job Event Monitoring

**Files:**
- Modify: `app/Providers/EventServiceProvider.php`

- [ ] **Step 1: Add queue event listeners**

```php
// In EventServiceProvider
Queue::before(function (Job $job) {
    Log::info('Job starting', ['job' => $job->getName(), 'id' => $job->getJobId()]);
});

Queue::failing(function (Job $job, \Throwable $e) {
    Log::error('Job failing', ['job' => $job->getName(), 'error' => $e->getMessage()]);
});
```

- [ ] **Step 2: Run tests**

Run: `php artisan test --filter=EventServiceProvider`
Expected: PASS

- [ ] **Step 3: Commit**

```bash
git add app/Providers/EventServiceProvider.php
git commit -m "feat: add queue job event monitoring"
```

---

### Task 28: Add ShouldBeUnique to SendNotificationJob

**Files:**
- Modify: `app/Jobs/SendNotificationJob.php`

- [ ] **Step 1: Examine job**

Read `/www/wwwroot/local.host/app/Jobs/SendNotificationJob.php` lines 190-210

- [ ] **Step 2: Add ShouldBeUnique interface**

```php
class SendNotificationJob implements ShouldQueue, ShouldBeUnique
{
    public int $uniqueFor = 60;

    public function uniqueId(): string
    {
        // Already defined
    }
}
```

- [ ] **Step 3: Run tests**

Run: `php artisan test --filter=SendNotificationJob`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add app/Jobs/SendNotificationJob.php
git commit -m "feat: add ShouldBeUnique to prevent duplicate notification jobs"
```

---

### Task 29: Fix ReconcileDeferredAccountingJob Without Chunking

**Files:**
- Modify: `app/Jobs/Accounting/ReconcileDeferredAccountingJob.php:118-149`

- [ ] **Step 1: Examine job**

Read `/www/wwwroot/local.host/app/Jobs/Accounting/ReconcileDeferredAccountingJob.php` lines 115-155

- [ ] **Step 2: Add chunking**

Replace foreach with `Transaction::where(...)->chunkById(100, function ($transactions) { ... })`

- [ ] **Step 3: Run tests**

Run: `php artisan test --filter=ReconcileDeferredAccounting`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add app/Jobs/Accounting/ReconcileDeferredAccountingJob.php
git commit -m "fix: add chunking for large transaction reconciliation"
```

---

### Task 30: Fix Horizon Timeout Mismatch

**Files:**
- Modify: `config/horizon.php:226`

- [ ] **Step 1: Examine current timeout**

Read `/www/wwwroot/local.host/config/horizon.php` lines 220-235

- [ ] **Step 2: Increase timeout**

Replace:
```php
'timeout' => 300,
```

With:
```php
'timeout' => 3600,
```

- [ ] **Step 3: Commit**

```bash
git add config/horizon.php
git commit -m "fix: increase Horizon production timeout to match long-running jobs"
```

---

## Phase 3: Minor Fixes

### Task 31: Fix Session Security Issues

**Files:**
- Modify: `config/session.php:49`
- Modify: `app/Http/Controllers/Auth/LoginController.php:29`

- [ ] **Step 1: Enable session encryption**

Read `/www/wwwroot/local.host/config/session.php`
Change `'encrypt' => false` to `'encrypt' => true`

- [ ] **Step 2: Add session regeneration on login**

Read `/www/wwwroot/local.host/app/Http/Controllers/Auth/LoginController.php`
Add `session()->regenerate()` before `Auth::login($user)`

- [ ] **Step 3: Commit**

```bash
git add config/session.php app/Http/Controllers/Auth/LoginController.php
git commit -m "fix: enable session encryption and add session regeneration on login"
```

---

### Task 32: Fix DB Transaction Inefficiency - Bulk Insert

**Files:**
- Modify: `app/Services/BankReconciliationService.php:16`

- [ ] **Step 1: Examine transaction usage**

Read lines 10-30

- [ ] **Step 2: Replace loop create with bulk insert**

```php
// Before
foreach ($lines as $line) {
    BankReconciliation::create([...]);
}

// After
BankReconciliation::insert($records);
```

- [ ] **Step 3: Commit**

```bash
git add app/Services/BankReconciliationService.php
git commit -m "fix: use bulk insert in BankReconciliationService transaction"
```

---

### Task 33: Add Missing Job Tags

**Files:**
- Modify: `app/Jobs/ProcessCustomerImport.php`
- Modify: `app/Jobs/ProcessTransactionImport.php`

- [ ] **Step 1: Add tags() method**

```php
public function tags(): array
{
    return ['import', 'customer', 'csv'];
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Jobs/ProcessCustomerImport.php app/Jobs/ProcessTransactionImport.php
git commit -m "chore: add tags() to import jobs for Horizon observability"
```

---

## Verification

- [ ] Run full test suite: `php artisan test`
- [ ] Run lint: `./vendor/bin/pint`
- [ ] Verify all 38 issues addressed
- [ ] Generate summary report

---

## Plan Summary

| Task | Category | Status |
|------|----------|--------|
| 1-7 | Security Critical | Phase 1 |
| 8-14 | Architecture Critical | Phase 1 |
| 15-17 | Jobs Critical | Phase 1 |
| 18-23 | Database Major | Phase 2 |
| 24-26 | Architecture Major | Phase 2 |
| 27-30 | Jobs Major | Phase 2 |
| 31-33 | Minor | Phase 3 |

**Total: 33 tasks across 3 phases**
