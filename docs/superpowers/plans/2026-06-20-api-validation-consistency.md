# API Validation Consistency — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace all inline `$request->validate()` calls in API v1 controllers with Form Request classes, establishing consistent validation patterns and error response formats across the API.

**Architecture:** Create an `ApiFormRequest` base class with consistent error envelope (`{success, message, errors}`), then create 26 Form Request classes grouped by controller, and update each controller to use them. Existing root-level Form Requests that overlap with API needs will be replaced by proper API-specific versions.

**Tech Stack:** PHP 8.3, Laravel 10, PHPUnit 11

## Global Constraints

- PHP 8.3.30, Laravel 10, PHPUnit 11
- Run `vendor/bin/pint --dirty --format agent` before every commit
- Run affected tests after each task to verify no regressions
- Use `php artisan make:request` to scaffold new Form Request classes
- All API Form Requests extend `ApiFormRequest` (new base class)
- Validation error responses must use `{success: false, message: "Validation failed", errors: {...}}` format
- Do NOT add comments to code
- Preserve exact validation rules from inline code — do not modify rules

---

## File Structure

### New Files (28 total)

| File | Purpose |
|------|---------|
| `app/Http/Requests/ApiFormRequest.php` | Base class with `failedValidation()` override for consistent API error envelope |
| `app/Http/Requests/Api/V1/Transaction/StoreTransactionRequest` | TransactionController::store |
| `app/Http/Requests/Api/V1/Customer/UploadDocumentRequest` | CustomerController::uploadDocument |
| `app/Http/Requests/Api/V1/Customer/SearchCustomerRequest` | CustomerController::searchForTransaction |
| `app/Http/Requests/Api/V1/Screening/BatchScreenRequest` | ScreeningController::batchScreen |
| `app/Http/Requests/Api/V1/Sanction/SearchSanctionRequest` | SanctionController::search |
| `app/Http/Requests/Api/V1/TellerAllocation/ApproveAllocationRequest` | TellerAllocationController::approve |
| `app/Http/Requests/Api/V1/TellerAllocation/RejectAllocationRequest` | TellerAllocationController::reject |
| `app/Http/Requests/Api/V1/TellerAllocation/ModifyAllocationRequest` | TellerAllocationController::modify |
| `app/Http/Requests/Api/V1/TellerAllocation/MyActiveAllocationRequest` | TellerAllocationController::myActiveAllocation |
| `app/Http/Requests/Api/V1/Counter/InitiateOpeningRequest` | CounterOpeningController::initiateOpeningRequest |
| `app/Http/Requests/Api/V1/Counter/ApproveAndOpenRequest` | CounterOpeningController::approveAndOpen |
| `app/Http/Requests/Api/V1/Counter/AcknowledgeHandoverRequest` | CounterHandoverController::acknowledge |
| `app/Http/Requests/Api/V1/Counter/InitiateEmergencyCloseRequest` | EmergencyCounterController::initiateClose |
| `app/Http/Requests/Api/V1/Report/ExportReportRequest` | ReportController::export |
| `app/Http/Requests/Api/V1/Eod/ShowReconciliationRequest` | EodReconciliationController::show |
| `app/Http/Requests/Api/V1/Eod/CounterReconciliationRequest` | EodReconciliationController::counterReconciliation |
| `app/Http/Requests/Api/V1/Eod/GenerateReportRequest` | EodReconciliationController::report |
| `app/Http/Requests/Api/V1/Compliance/BulkAssignAlertRequest` | AlertController::bulkAssign |
| `app/Http/Requests/Api/V1/Compliance/BulkResolveAlertRequest` | AlertController::bulkResolve |
| `app/Http/Requests/Api/V1/Compliance/AddCaseNoteRequest` | CaseController::addNote |
| `app/Http/Requests/Api/V1/Compliance/CloseCaseRequest` | CaseController::close |
| `app/Http/Requests/Api/V1/Compliance/DismissFindingRequest` | FindingController::dismiss |
| `app/Http/Requests/Api/V1/Compliance/AuditTrailRequest` | DashboardController::auditTrail |
| `app/Http/Requests/Api/V1/Compliance/AuditTrailExportRequest` | DashboardController::auditTrailExport |
| `app/Http/Requests/Api/V1/Compliance/SubmitQuestionnaireRequest` | EddController::submitQuestionnaire |
| `app/Http/Requests/Api/V1/Compliance/RejectEddRequest` | EddController::reject |
| `app/Http/Requests/Api/V1/Compliance/LockRiskProfileRequest` | RiskController::lock |
| `app/Http/Requests/Api/V1/SanctionList/StoreSanctionEntryRequest` | SanctionListController::storeEntry |
| `app/Http/Requests/Api/V1/SanctionList/UpdateSanctionEntryRequest` | SanctionListController::updateEntry |
| `app/Http/Requests/Api/V1/SanctionList/IndexSanctionEntryRequest` | SanctionListController::entries |

### Modified Files (16 controllers)

All controllers in `app/Http/Controllers/Api/V1/` that currently use inline validation.

---

### Task 1: Create ApiFormRequest Base Class

**Files:**
- Create: `app/Http/Requests/ApiFormRequest.php`

**Interfaces:**
- Consumes: (none)
- Produces: `ApiFormRequest` — base class for all API Form Requests with consistent `{success, message, errors}` error envelope

- [ ] **Step 1: Create the ApiFormRequest base class**

```bash
php artisan make:request ApiFormRequest --no-interaction
```

Edit the created file to:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

abstract class ApiFormRequest extends AuthorizedFormRequest
{
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $validator->errors(),
        ], 422));
    }
}
```

- [ ] **Step 2: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 3: Commit**

```bash
git add app/Http/Requests/ApiFormRequest.php
git commit -m "feat: add ApiFormRequest base class with consistent error envelope"
```

---

### Task 2: Core Transaction & Customer Form Requests (5 classes)

**Files:**
- Create: `app/Http/Requests/Api/V1/Transaction/StoreTransactionRequest.php`
- Create: `app/Http/Requests/Api/V1/Customer/UploadDocumentRequest.php`
- Create: `app/Http/Requests/Api/V1/Customer/SearchCustomerRequest.php`
- Create: `app/Http/Requests/Api/V1/Screening/BatchScreenRequest.php`
- Create: `app/Http/Requests/Api/V1/Sanction/SearchSanctionRequest.php`
- Modify: `app/Http/Controllers/Api/V1/TransactionController.php`
- Modify: `app/Http/Controllers/Api/V1/CustomerController.php`
- Modify: `app/Http/Controllers/Api/V1/ScreeningController.php`
- Modify: `app/Http/Controllers/Api/V1/SanctionController.php`

**Interfaces:**
- Consumes: `ApiFormRequest` (from Task 1)
- Produces: 5 Form Request classes, 4 controllers updated

- [ ] **Step 1: Create StoreTransactionRequest**

```bash
mkdir -p app/Http/Requests/Api/V1/Transaction
```

```php
<?php

namespace App\Http\Requests\Api\V1\Transaction;

use App\Http\Requests\ApiFormRequest;

class StoreTransactionRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'customer_id' => 'required|exists:customers,id',
            'type' => ['required', 'in:buy,sell'],
            'currency_code' => 'required|exists:currencies,code',
            'amount_foreign' => 'required|numeric|min:0.01|max:9999999999.9999',
            'rate' => 'required|numeric|min:0.0001|max:999999',
            'purpose' => 'required|string|max:255',
            'source_of_funds' => 'required|string|max:255',
            'till_id' => 'required|string',
            'idempotency_key' => 'nullable|string|max:100',
        ];
    }
}
```

- [ ] **Step 2: Update TransactionController::store()**

Replace the inline validation block with Form Request injection:

```php
public function store(StoreTransactionRequest $request): JsonResponse
{
    $validated = $request->validated();
    // ... rest unchanged
```

Update imports: add `use App\Http\Requests\Api\V1\Transaction\StoreTransactionRequest;`, remove inline validation.

- [ ] **Step 3: Create UploadDocumentRequest**

```bash
mkdir -p app/Http/Requests/Api/V1/Customer
```

```php
<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Http\Requests\ApiFormRequest;

class UploadDocumentRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'document' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'document_type' => 'required|string|max:100',
        ];
    }
}
```

- [ ] **Step 4: Update CustomerController::uploadDocument()**

Replace `$request->validate([...])` with Form Request injection:

```php
public function uploadDocument(UploadDocumentRequest $request, int $id): JsonResponse
{
    $validated = $request->validated();
    // ... rest unchanged
```

- [ ] **Step 5: Create SearchCustomerRequest**

```php
<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Http\Requests\ApiFormRequest;

class SearchCustomerRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'query' => 'required|string|min:2',
        ];
    }
}
```

- [ ] **Step 6: Update CustomerController::searchForTransaction()**

Replace inline validation with Form Request injection.

- [ ] **Step 7: Create BatchScreenRequest**

```bash
mkdir -p app/Http/Requests/Api/V1/Screening
```

```php
<?php

namespace App\Http\Requests\Api\V1\Screening;

use App\Http\Requests\ApiFormRequest;

class BatchScreenRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'customer_ids' => 'required|array|min:1|max:100',
            'customer_ids.*' => 'integer|exists:customers,id',
        ];
    }
}
```

- [ ] **Step 8: Update ScreeningController::batchScreen()**

Replace inline validation with Form Request injection.

- [ ] **Step 9: Create SearchSanctionRequest**

```bash
mkdir -p app/Http/Requests/Api/V1/Sanction
```

```php
<?php

namespace App\Http\Requests\Api\V1\Sanction;

use App\Http\Requests\ApiFormRequest;

class SearchSanctionRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|min:3',
        ];
    }
}
```

- [ ] **Step 10: Update SanctionController::search()**

Replace inline validation with Form Request injection.

- [ ] **Step 11: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 12: Run tests**

```bash
php artisan test --compact --filter="TransactionTest|CustomerTest|ScreeningTest|SanctionTest"
```

- [ ] **Step 13: Commit**

```bash
git add app/Http/Requests/Api/V1/ app/Http/Controllers/Api/V1/TransactionController.php app/Http/Controllers/Api/V1/CustomerController.php app/Http/Controllers/Api/V1/ScreeningController.php app/Http/Controllers/Api/V1/SanctionController.php
git commit -m "feat(api): extract Form Requests for Transaction, Customer, Screening, Sanction controllers"
```

---

### Task 3: TellerAllocation Form Requests (4 classes)

**Files:**
- Create: `app/Http/Requests/Api/V1/TellerAllocation/ApproveAllocationRequest.php`
- Create: `app/Http/Requests/Api/V1/TellerAllocation/RejectAllocationRequest.php`
- Create: `app/Http/Requests/Api/V1/TellerAllocation/ModifyAllocationRequest.php`
- Create: `app/Http/Requests/Api/V1/TellerAllocation/MyActiveAllocationRequest.php`
- Modify: `app/Http/Controllers/Api/V1/TellerAllocationController.php`

**Interfaces:**
- Consumes: `ApiFormRequest` (from Task 1)
- Produces: 4 Form Request classes, 1 controller updated

- [ ] **Step 1: Create all 4 Form Requests**

```bash
mkdir -p app/Http/Requests/Api/V1/TellerAllocation
```

**ApproveAllocationRequest.php:**
```php
<?php

namespace App\Http\Requests\Api\V1\TellerAllocation;

use App\Http\Requests\ApiFormRequest;

class ApproveAllocationRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'approved_amount' => 'required|numeric|min:0.0001',
            'daily_limit_myr' => 'nullable|numeric|min:0',
        ];
    }
}
```

**RejectAllocationRequest.php:**
```php
<?php

namespace App\Http\Requests\Api\V1\TellerAllocation;

use App\Http\Requests\ApiFormRequest;

class RejectAllocationRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'rejection_reason' => 'nullable|string|max:500',
        ];
    }
}
```

**ModifyAllocationRequest.php:**
```php
<?php

namespace App\Http\Requests\Api\V1\TellerAllocation;

use App\Http\Requests\ApiFormRequest;

class ModifyAllocationRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'new_amount' => 'required|numeric|min:0.0001',
            'is_increase' => 'required|boolean',
        ];
    }
}
```

**MyActiveAllocationRequest.php:**
```php
<?php

namespace App\Http\Requests\Api\V1\TellerAllocation;

use App\Http\Requests\ApiFormRequest;

class MyActiveAllocationRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'currency_code' => 'required|string|size:3',
        ];
    }
}
```

- [ ] **Step 2: Update TellerAllocationController**

Replace all 4 inline `$request->validate()` calls with Form Request injection:

```php
public function approve(ApproveAllocationRequest $request, int $allocationId): JsonResponse
{
    $validated = $request->validated();
    // ... rest unchanged
```

Same pattern for `reject()`, `modify()`, `myActiveAllocation()`.

- [ ] **Step 3: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 4: Run tests**

```bash
php artisan test --compact --filter="TellerAllocation"
```

- [ ] **Step 5: Commit**

```bash
git add app/Http/Requests/Api/V1/TellerAllocation/ app/Http/Controllers/Api/V1/TellerAllocationController.php
git commit -m "feat(api): extract Form Requests for TellerAllocationController"
```

---

### Task 4: Counter Operations Form Requests (4 classes)

**Files:**
- Create: `app/Http/Requests/Api/V1/Counter/InitiateOpeningRequest.php`
- Create: `app/Http/Requests/Api/V1/Counter/ApproveAndOpenRequest.php`
- Create: `app/Http/Requests/Api/V1/Counter/AcknowledgeHandoverRequest.php`
- Create: `app/Http/Requests/Api/V1/Counter/InitiateEmergencyCloseRequest.php`
- Modify: `app/Http/Controllers/Api/V1/CounterOpeningController.php`
- Modify: `app/Http/Controllers/Api/V1/CounterHandoverController.php`
- Modify: `app/Http/Controllers/Api/V1/EmergencyCounterController.php`

**Interfaces:**
- Consumes: `ApiFormRequest` (from Task 1)
- Produces: 4 Form Request classes, 3 controllers updated

- [ ] **Step 1: Create all 4 Form Requests**

**InitiateOpeningRequest.php:**
```php
<?php

namespace App\Http\Requests\Api\V1\Counter;

use App\Http\Requests\ApiFormRequest;

class InitiateOpeningRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'requested_floats' => 'required|array',
            'requested_floats.*' => 'required|numeric|min:0.0001',
        ];
    }
}
```

**ApproveAndOpenRequest.php:**
```php
<?php

namespace App\Http\Requests\Api\V1\Counter;

use App\Http\Requests\ApiFormRequest;

class ApproveAndOpenRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'teller_id' => 'required|integer|exists:users,id',
            'approved_floats' => 'required|array',
            'approved_floats.*' => 'required|numeric|min:0',
            'daily_limits' => 'nullable|array',
            'daily_limits.*' => 'nullable|numeric|min:0',
        ];
    }
}
```

**AcknowledgeHandoverRequest.php:**
```php
<?php

namespace App\Http\Requests\Api\V1\Counter;

use App\Http\Requests\ApiFormRequest;

class AcknowledgeHandoverRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'verified' => 'required|boolean',
            'notes' => 'nullable|string|max:500',
        ];
    }
}
```

**InitiateEmergencyCloseRequest.php:**
```php
<?php

namespace App\Http\Requests\Api\V1\Counter;

use App\Http\Requests\ApiFormRequest;

class InitiateEmergencyCloseRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'reason' => 'required|string|max:500',
        ];
    }
}
```

- [ ] **Step 2: Update CounterOpeningController**

Replace `initiateOpeningRequest()` and `approveAndOpen()` inline validation with Form Request injection.

- [ ] **Step 3: Update CounterHandoverController**

Replace `acknowledge()` inline validation with Form Request injection.

- [ ] **Step 4: Update EmergencyCounterController**

Replace `initiateClose()` inline validation with Form Request injection.

- [ ] **Step 5: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 6: Run tests**

```bash
php artisan test --compact --filter="Counter|Emergency|Handover"
```

- [ ] **Step 7: Commit**

```bash
git add app/Http/Requests/Api/V1/Counter/ app/Http/Controllers/Api/V1/CounterOpeningController.php app/Http/Controllers/Api/V1/CounterHandoverController.php app/Http/Controllers/Api/V1/EmergencyCounterController.php
git commit -m "feat(api): extract Form Requests for CounterOpening, CounterHandover, EmergencyCounter controllers"
```

---

### Task 5: Reporting & EOD Form Requests (4 classes)

**Files:**
- Create: `app/Http/Requests/Api/V1/Report/ExportReportRequest.php`
- Create: `app/Http/Requests/Api/V1/Eod/ShowReconciliationRequest.php`
- Create: `app/Http/Requests/Api/V1/Eod/CounterReconciliationRequest.php`
- Create: `app/Http/Requests/Api/V1/Eod/GenerateReportRequest.php`
- Modify: `app/Http/Controllers/Api/V1/ReportController.php`
- Modify: `app/Http/Controllers/Api/V1/EodReconciliationController.php`

**Interfaces:**
- Consumes: `ApiFormRequest` (from Task 1)
- Produces: 4 Form Request classes, 2 controllers updated

- [ ] **Step 1: Create all 4 Form Requests**

**ExportReportRequest.php:**
```php
<?php

namespace App\Http\Requests\Api\V1\Report;

use App\Http\Requests\ApiFormRequest;

class ExportReportRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'report_type' => 'required|in:msb2,trial_balance,pl,balance_sheet',
            'period' => 'required|string',
            'format' => 'required|in:CSV,PDF,XLSX',
        ];
    }
}
```

**ShowReconciliationRequest.php:**
```php
<?php

namespace App\Http\Requests\Api\V1\Eod;

use App\Http\Requests\ApiFormRequest;

class ShowReconciliationRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'date' => 'required|date_format:Y-m-d',
            'branch_id' => 'nullable|exists:branches,id',
        ];
    }
}
```

**CounterReconciliationRequest.php:**
```php
<?php

namespace App\Http\Requests\Api\V1\Eod;

use App\Http\Requests\ApiFormRequest;

class CounterReconciliationRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'date' => 'required|date_format:Y-m-d',
        ];
    }
}
```

**GenerateReportRequest.php:**
```php
<?php

namespace App\Http\Requests\Api\V1\Eod;

use App\Http\Requests\ApiFormRequest;

class GenerateReportRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'date' => 'required|date_format:Y-m-d',
            'branch_id' => 'nullable|exists:branches,id',
            'counter_id' => 'nullable|exists:counters,id',
            'format' => 'nullable|in:pdf,json',
        ];
    }
}
```

- [ ] **Step 2: Update ReportController::export()**

Replace inline validation with Form Request injection.

- [ ] **Step 3: Update EodReconciliationController**

Replace `show()`, `counterReconciliation()`, and `report()` inline validation with Form Request injection.

- [ ] **Step 4: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 5: Run tests**

```bash
php artisan test --compact --filter="Report|Eod|Reconciliation"
```

- [ ] **Step 6: Commit**

```bash
git add app/Http/Requests/Api/V1/Report/ app/Http/Requests/Api/V1/Eod/ app/Http/Controllers/Api/V1/ReportController.php app/Http/Controllers/Api/V1/EodReconciliationController.php
git commit -m "feat(api): extract Form Requests for Report and EodReconciliation controllers"
```

---

### Task 6: Compliance — Alerts, Cases, Findings (5 classes)

**Files:**
- Create: `app/Http/Requests/Api/V1/Compliance/BulkAssignAlertRequest.php`
- Create: `app/Http/Requests/Api/V1/Compliance/BulkResolveAlertRequest.php`
- Create: `app/Http/Requests/Api/V1/Compliance/AddCaseNoteRequest.php`
- Create: `app/Http/Requests/Api/V1/Compliance/CloseCaseRequest.php`
- Create: `app/Http/Requests/Api/V1/Compliance/DismissFindingRequest.php`
- Modify: `app/Http/Controllers/Api/V1/Compliance/AlertController.php`
- Modify: `app/Http/Controllers/Api/V1/Compliance/CaseController.php`
- Modify: `app/Http/Controllers/Api/V1/Compliance/FindingController.php`

**Interfaces:**
- Consumes: `ApiFormRequest` (from Task 1)
- Produces: 5 Form Request classes, 3 controllers updated

- [ ] **Step 1: Create all 5 Form Requests**

```bash
mkdir -p app/Http/Requests/Api/V1/Compliance
```

**BulkAssignAlertRequest.php:**
```php
<?php

namespace App\Http\Requests\Api\V1\Compliance;

use App\Http\Requests\ApiFormRequest;

class BulkAssignAlertRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'alert_ids' => 'required|array|min:1',
            'alert_ids.*' => 'integer',
            'user_id' => 'required|integer|exists:users,id',
        ];
    }
}
```

**BulkResolveAlertRequest.php:**
```php
<?php

namespace App\Http\Requests\Api\V1\Compliance;

use App\Http\Requests\ApiFormRequest;

class BulkResolveAlertRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'alert_ids' => 'required|array|min:1',
            'alert_ids.*' => 'integer',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
```

**AddCaseNoteRequest.php:**
```php
<?php

namespace App\Http\Requests\Api\V1\Compliance;

use App\Http\Requests\ApiFormRequest;

class AddCaseNoteRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'note_type' => 'required|string',
            'content' => 'required|string|max:2000',
            'is_internal' => 'boolean',
        ];
    }
}
```

**CloseCaseRequest.php:**
```php
<?php

namespace App\Http\Requests\Api\V1\Compliance;

use App\Http\Requests\ApiFormRequest;

class CloseCaseRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'resolution' => 'required|string',
            'notes' => 'nullable|string',
        ];
    }
}
```

**DismissFindingRequest.php (API version):**
```php
<?php

namespace App\Http\Requests\Api\V1\Compliance;

use App\Http\Requests\ApiFormRequest;

class DismissFindingRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'reason' => 'required|string|max:500',
        ];
    }
}
```

- [ ] **Step 2: Update AlertController**

Replace `bulkAssign()` and `bulkResolve()` inline validation with Form Request injection.

- [ ] **Step 3: Update CaseController**

Replace `addNote()` and `close()` inline validation with Form Request injection.

- [ ] **Step 4: Update FindingController**

Replace `dismiss()` inline validation with Form Request injection.

- [ ] **Step 5: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 6: Run tests**

```bash
php artisan test --compact --filter="Alert|Case|Finding|Compliance"
```

- [ ] **Step 7: Commit**

```bash
git add app/Http/Requests/Api/V1/Compliance/ app/Http/Controllers/Api/V1/Compliance/AlertController.php app/Http/Controllers/Api/V1/Compliance/CaseController.php app/Http/Controllers/Api/V1/Compliance/FindingController.php
git commit -m "feat(api): extract Form Requests for Compliance Alert, Case, Finding controllers"
```

---

### Task 7: Compliance — Dashboard, EDD, Risk, SanctionList (6 classes)

**Files:**
- Create: `app/Http/Requests/Api/V1/Compliance/AuditTrailRequest.php`
- Create: `app/Http/Requests/Api/V1/Compliance/AuditTrailExportRequest.php`
- Create: `app/Http/Requests/Api/V1/Compliance/SubmitQuestionnaireRequest.php`
- Create: `app/Http/Requests/Api/V1/Compliance/RejectEddRequest.php`
- Create: `app/Http/Requests/Api/V1/Compliance/LockRiskProfileRequest.php`
- Create: `app/Http/Requests/Api/V1/SanctionList/IndexSanctionEntryRequest.php`
- Create: `app/Http/Requests/Api/V1/SanctionList/StoreSanctionEntryRequest.php`
- Create: `app/Http/Requests/Api/V1/SanctionList/UpdateSanctionEntryRequest.php`
- Modify: `app/Http/Controllers/Api/V1/Compliance/DashboardController.php`
- Modify: `app/Http/Controllers/Api/V1/Compliance/EddController.php`
- Modify: `app/Http/Controllers/Api/V1/Compliance/RiskController.php`
- Modify: `app/Http/Controllers/Api/V1/SanctionListController.php`

**Interfaces:**
- Consumes: `ApiFormRequest` (from Task 1)
- Produces: 8 Form Request classes, 4 controllers updated

- [ ] **Step 1: Create Dashboard Form Requests**

**AuditTrailRequest.php:**
```php
<?php

namespace App\Http\Requests\Api\V1\Compliance;

use App\Http\Requests\ApiFormRequest;

class AuditTrailRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'per_page' => 'nullable|integer|min:1|max:100',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
            'case_id' => 'nullable|integer',
        ];
    }
}
```

**AuditTrailExportRequest.php:**
```php
<?php

namespace App\Http\Requests\Api\V1\Compliance;

use App\Http\Requests\ApiFormRequest;

class AuditTrailExportRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
            'case_id' => 'nullable|integer',
        ];
    }
}
```

- [ ] **Step 2: Create EDD Form Requests**

**SubmitQuestionnaireRequest.php:**
```php
<?php

namespace App\Http\Requests\Api\V1\Compliance;

use App\Http\Requests\ApiFormRequest;

class SubmitQuestionnaireRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'responses' => 'required|array',
            'responses.*' => 'nullable|string',
        ];
    }
}
```

**RejectEddRequest.php:**
```php
<?php

namespace App\Http\Requests\Api\V1\Compliance;

use App\Http\Requests\ApiFormRequest;

class RejectEddRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'reason' => 'required|string|max:1000',
        ];
    }
}
```

- [ ] **Step 3: Create Risk Form Request**

**LockRiskProfileRequest.php:**
```php
<?php

namespace App\Http\Requests\Api\V1\Compliance;

use App\Http\Requests\ApiFormRequest;

class LockRiskProfileRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'reason' => 'required|string|max:500',
        ];
    }
}
```

- [ ] **Step 4: Create SanctionList Form Requests**

```bash
mkdir -p app/Http/Requests/Api/V1/SanctionList
```

**IndexSanctionEntryRequest.php:**
```php
<?php

namespace App\Http\Requests\Api\V1\SanctionList;

use App\Http\Requests\ApiFormRequest;

class IndexSanctionEntryRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
            'list_id' => 'integer|exists:sanction_lists,id',
            'search' => 'string|max:255',
            'status' => 'in:active,inactive,all',
        ];
    }
}
```

**StoreSanctionEntryRequest.php:**
```php
<?php

namespace App\Http\Requests\Api\V1\SanctionList;

use App\Http\Requests\ApiFormRequest;

class StoreSanctionEntryRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'list_id' => 'required|exists:sanction_lists,id',
            'entity_name' => 'required|string|max:255',
            'entity_type' => 'required|in:Individual,Entity',
            'aliases' => 'nullable|string',
            'nationality' => 'nullable|string|max:100',
            'date_of_birth' => 'nullable|date',
            'reference_number' => 'nullable|string|max:100',
            'listing_date' => 'nullable|date',
            'details' => 'nullable|array',
        ];
    }
}
```

**UpdateSanctionEntryRequest.php:**
```php
<?php

namespace App\Http\Requests\Api\V1\SanctionList;

use App\Http\Requests\ApiFormRequest;

class UpdateSanctionEntryRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'entity_name' => 'nullable|string|max:255',
            'entity_type' => 'nullable|in:Individual,Entity',
            'aliases' => 'nullable|string',
            'nationality' => 'nullable|string|max:100',
            'date_of_birth' => 'nullable|date',
            'reference_number' => 'nullable|string|max:100',
            'listing_date' => 'nullable|date',
            'details' => 'nullable|array',
            'status' => 'nullable|in:active,inactive',
        ];
    }
}
```

- [ ] **Step 5: Update DashboardController**

Replace `auditTrail()` and `auditTrailExport()` inline validation with Form Request injection.

- [ ] **Step 6: Update EddController**

Replace `submitQuestionnaire()` and `reject()` inline validation with Form Request injection.

- [ ] **Step 7: Update RiskController**

Replace `lock()` inline validation with Form Request injection.

- [ ] **Step 8: Update SanctionListController**

Replace `entries()`, `storeEntry()`, and `updateEntry()` inline validation with Form Request injection.

- [ ] **Step 9: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 10: Run tests**

```bash
php artisan test --compact --filter="Dashboard|Edd|Risk|SanctionList"
```

- [ ] **Step 11: Commit**

```bash
git add app/Http/Requests/Api/V1/Compliance/ app/Http/Requests/Api/V1/SanctionList/ app/Http/Controllers/Api/V1/Compliance/DashboardController.php app/Http/Controllers/Api/V1/Compliance/EddController.php app/Http/Controllers/Api/V1/Compliance/RiskController.php app/Http/Controllers/Api/V1/SanctionListController.php
git commit -m "feat(api): extract Form Requests for Compliance Dashboard, EDD, Risk, SanctionList controllers"
```

---

### Task 8: Update Existing API Form Requests to Extend ApiFormRequest

**Files:**
- Modify: `app/Http/Requests/Api/V1/Counter/CloseCounterRequest.php`
- Modify: `app/Http/Requests/Api/V1/Counter/StoreCounterRequest.php`
- Modify: `app/Http/Requests/Api/V1/Rate/UpdateRateRequest.php`
- Modify: `app/Http/Requests/Api/V1/Rate/StoreRateRequest.php`
- Modify: `app/Http/Requests/Api/V1/Rate/CheckRateSetRequest.php`
- Modify: `app/Http/Requests/Api/V1/Rate/ValidateRateRequest.php`
- Modify: `app/Http/Requests/Api/V1/Rate/CopyPreviousRateRequest.php`

**Interfaces:**
- Consumes: `ApiFormRequest` (from Task 1)
- Produces: 7 existing Form Requests updated to extend `ApiFormRequest`

- [ ] **Step 1: Update all 7 existing API Form Requests**

For each file, change:
```php
use App\Http\Requests\AuthorizedFormRequest;
```
to:
```php
use App\Http\Requests\ApiFormRequest;
```

And change:
```php
class XyzRequest extends AuthorizedFormRequest
```
to:
```php
class XyzRequest extends ApiFormRequest
```

Also remove the `failedValidation()` method from `CloseCounterRequest` and `StoreCounterRequest` since `ApiFormRequest` now handles it.

- [ ] **Step 2: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 3: Run full test suite**

```bash
php artisan test --compact
```

- [ ] **Step 4: Commit**

```bash
git add app/Http/Requests/Api/V1/
git commit -m "refactor(api): update existing API Form Requests to extend ApiFormRequest"
```

---

### Task 9: Final Verification

- [ ] **Step 1: Verify no remaining inline validation in API controllers**

```bash
grep -rn 'request->validate(' app/Http/Controllers/Api/ --include="*.php"
```

Expected: No output (all migrated)

- [ ] **Step 2: Run full test suite**

```bash
php artisan test --compact
```

Expected: All tests pass, no regressions

- [ ] **Step 3: Run slow test suite**

```bash
php artisan test --compact --group=slow
```

Expected: All slow tests pass

- [ ] **Step 4: Verify consistent error response format**

```bash
grep -rn "failedValidation" app/Http/Requests/Api/ --include="*.php"
```

Expected: Only `ApiFormRequest.php` has `failedValidation()` — no duplicates in individual requests

- [ ] **Step 5: Push to GitHub**

```bash
git push origin main
```
