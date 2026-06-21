# API/Web Validation Inconsistency Fix — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Extract all 26 inline `$request->validate()` calls from API v1 controllers into Form Request classes, standardize error response format, and eliminate duplicate Form Requests.

**Architecture:** Create a base `ApiFormRequest` class that extends `AuthorizedFormRequest` and overrides `failedValidation()` to return `{success: false, message: "Validation failed", errors: {...}}` — the format already used by `CloseCounterRequest` and `StoreCounterRequest`. All new API Form Requests extend this base. Controllers switch from `Request` type-hints to specific Form Request type-hints. Existing root-level Form Requests are reused where rules match exactly; new API-specific ones created where rules differ.

**Tech Stack:** Laravel 10, PHP 8.3, PHPUnit 11, Laravel Pint

## Global Constraints

- PHP 8.3.30, Laravel 10, PHPUnit 11
- Run `vendor/bin/pint --dirty --format agent` before every commit
- Run `php artisan test --compact` after each task to verify no regressions
- Follow existing code conventions: `AuthorizedFormRequest` base class, `rules()` method, no comments in code
- Validation rules must match existing inline rules exactly (no additions, no removals)
- Form Request classes go in `app/Http/Requests/Api/V1/<ControllerGroup>/`
- Each Form Request has one purpose (Store, Update, etc.) — follow existing naming conventions

---

## File Structure

### New Files (32 total)

**Base class:**
- `app/Http/Requests/Api/V1/ApiFormRequest.php` — Base for all API v1 Form Requests with standardized `failedValidation()`

**TransactionController (1):**
- `app/Http/Requests/Api/V1/Transaction/StoreTransactionRequest.php`

**ReportController (1):**
- `app/Http/Requests/Api/V1/Report/ExportReportRequest.php`

**CustomerController (2):**
- `app/Http/Requests/Api/V1/Customer/UploadDocumentRequest.php`
- `app/Http/Requests/Api/V1/Customer/SearchCustomerRequest.php`

**TellerAllocationController (4):**
- `app/Http/Requests/Api/V1/TellerAllocation/ApproveAllocationRequest.php`
- `app/Http/Requests/Api/V1/TellerAllocation/RejectAllocationRequest.php`
- `app/Http/Requests/Api/V1/TellerAllocation/ModifyAllocationRequest.php`
- `app/Http/Requests/Api/V1/TellerAllocation/MyActiveAllocationRequest.php`

**CounterOpeningController (2):**
- `app/Http/Requests/Api/V1/Counter/InitiateOpeningRequest.php`
- `app/Http/Requests/Api/V1/Counter/ApproveAndOpenRequest.php`

**EmergencyCounterController (1):**
- `app/Http/Requests/Api/V1/Counter/InitiateCloseRequest.php`

**CounterHandoverController (1):**
- `app/Http/Requests/Api/V1/Counter/AcknowledgeHandoverRequest.php`

**ScreeningController (1):**
- `app/Http/Requests/Api/V1/Screening/BatchScreenRequest.php`

**SanctionListController (2):**
- `app/Http/Requests/Api/V1/SanctionList/FilterEntriesRequest.php`
- `app/Http/Requests/Api/V1/SanctionList/StoreEntryRequest.php`

**EodReconciliationController (3):**
- `app/Http/Requests/Api/V1/Eod/ShowReconciliationRequest.php`
- `app/Http/Requests/Api/V1/Eod/CounterReconciliationRequest.php`
- `app/Http/Requests/Api/V1/Eod/ExportReconciliationRequest.php`

**SanctionController (1):**
- `app/Http/Requests/Api/V1/Sanction/SearchSanctionRequest.php`

**Compliance/FindingController (1):**
- `app/Http/Requests/Api/V1/Compliance/DismissFindingRequest.php`

**Compliance/RiskController (1):**
- `app/Http/Requests/Api/V1/Compliance/LockRiskRequest.php`

**Compliance/DashboardController (2):**
- `app/Http/Requests/Api/V1/Compliance/AuditTrailRequest.php`
- `app/Http/Requests/Api/V1/Compliance/AuditTrailExportRequest.php`

**Compliance/EddController (2):**
- `app/Http/Requests/Api/V1/Compliance/SubmitQuestionnaireRequest.php`
- `app/Http/Requests/Api/V1/Compliance/RejectEddRequest.php`

**Compliance/CaseController (2):**
- `app/Http/Requests/Api/V1/Compliance/AddCaseNoteRequest.php`
- `app/Http/Requests/Api/V1/Compliance/CloseCaseRequest.php`

**Compliance/AlertController (2):**
- `app/Http/Requests/Api/V1/Compliance/BulkAssignAlertRequest.php`
- `app/Http/Requests/Api/V1/Compliance/BulkResolveAlertRequest.php`

### Modified Files (17 controllers)

- `app/Http/Controllers/Api/V1/TransactionController.php`
- `app/Http/Controllers/Api/V1/ReportController.php`
- `app/Http/Controllers/Api/V1/CustomerController.php`
- `app/Http/Controllers/Api/V1/TellerAllocationController.php`
- `app/Http/Controllers/Api/V1/CounterOpeningController.php`
- `app/Http/Controllers/Api/V1/EmergencyCounterController.php`
- `app/Http/Controllers/Api/V1/CounterHandoverController.php`
- `app/Http/Controllers/Api/V1/ScreeningController.php`
- `app/Http/Controllers/Api/V1/SanctionListController.php`
- `app/Http/Controllers/Api/V1/EodReconciliationController.php`
- `app/Http/Controllers/Api/V1/SanctionController.php`
- `app/Http/Controllers/Api/V1/Compliance/FindingController.php`
- `app/Http/Controllers/Api/V1/Compliance/RiskController.php`
- `app/Http/Controllers/Api/V1/Compliance/DashboardController.php`
- `app/Http/Controllers/Api/V1/Compliance/EddController.php`
- `app/Http/Controllers/Api/V1/Compliance/CaseController.php`
- `app/Http/Controllers/Api/V1/Compliance/AlertController.php`

### Files to Delete (1 duplicate)

- `app/Http/Requests/CloseCounterRequest.php` — Duplicate of `Api/V1/Counter/CloseCounterRequest.php`

### Files to Modify (1 existing)

- `app/Http/Requests/Api/V1/Counter/CloseCounterRequest.php` — Switch parent from `AuthorizedFormRequest` to `ApiFormRequest`
- `app/Http/Requests/Api/V1/Counter/StoreCounterRequest.php` — Switch parent from `AuthorizedFormRequest` to `ApiFormRequest`

---

### Task 1: Create ApiFormRequest base class

**Files:**
- Create: `app/Http/Requests/Api/V1/ApiFormRequest.php`
- Modify: `app/Http/Requests/Api/V1/Counter/CloseCounterRequest.php`
- Modify: `app/Http/Requests/Api/V1/Counter/StoreCounterRequest.php`
- Delete: `app/Http/Requests/CloseCounterRequest.php`

**Interfaces:**
- Produces: `ApiFormRequest` base class with `failedValidation()` returning `{success: false, message: "Validation failed", errors: {...}}`

- [ ] **Step 1: Create ApiFormRequest base class**

```php
<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\AuthorizedFormRequest;
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

- [ ] **Step 2: Update CloseCounterRequest to extend ApiFormRequest**

Remove the `failedValidation()` method and change parent to `ApiFormRequest`. Remove unused imports (`Illuminate\Contracts\Validation\Validator`, `Illuminate\Http\Exceptions\HttpResponseException`).

```php
<?php

namespace App\Http\Requests\Api\V1\Counter;

use App\Http\Requests\Api\V1\ApiFormRequest;

class CloseCounterRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'closing_floats' => 'required|array',
            'closing_floats.*' => 'numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ];
    }
}
```

- [ ] **Step 3: Update StoreCounterRequest to extend ApiFormRequest**

Same change — remove `failedValidation()`, change parent to `ApiFormRequest`.

- [ ] **Step 4: Delete duplicate root-level CloseCounterRequest**

```bash
rm app/Http/Requests/CloseCounterRequest.php
```

Check if anything imports `App\Http\Requests\CloseCounterRequest` — if so, update to `App\Http\Requests\Api\V1\Counter\CloseCounterRequest`.

- [ ] **Step 5: Run tests**

```bash
php artisan test --compact
```

- [ ] **Step 6: Run Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Requests/Api/V1/ApiFormRequest.php app/Http/Requests/Api/V1/Counter/CloseCounterRequest.php app/Http/Requests/Api/V1/Counter/StoreCounterRequest.php app/Http/Requests/CloseCounterRequest.php
git commit -m "feat(validation): add ApiFormRequest base class with standardized error format"
```

---

### Task 2: TransactionController + ReportController Form Requests

**Files:**
- Create: `app/Http/Requests/Api/V1/Transaction/StoreTransactionRequest.php`
- Create: `app/Http/Requests/Api/V1/Report/ExportReportRequest.php`
- Modify: `app/Http/Controllers/Api/V1/TransactionController.php`
- Modify: `app/Http/Controllers/Api/V1/ReportController.php`

**Interfaces:**
- Consumes: `ApiFormRequest` from Task 1
- Produces: `StoreTransactionRequest`, `ExportReportRequest`

- [ ] **Step 1: Create StoreTransactionRequest**

```php
<?php

namespace App\Http\Requests\Api\V1\Transaction;

use App\Enums\TransactionType;
use App\Http\Requests\Api\V1\ApiFormRequest;

class StoreTransactionRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'customer_id' => 'required|exists:customers,id',
            'type' => ['required', 'in:'.TransactionType::Buy->value.','.TransactionType::Sell->value],
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

- [ ] **Step 2: Create ExportReportRequest**

```php
<?php

namespace App\Http\Requests\Api\V1\Report;

use App\Http\Requests\Api\V1\ApiFormRequest;

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

- [ ] **Step 3: Update TransactionController::store()**

Replace `Request $request` with `StoreTransactionRequest $request`. Remove the `$request->validate([...])` block. Change `$validated` to `$request->validated()`.

```php
public function store(StoreTransactionRequest $request): JsonResponse
{
    $validated = $request->validated();

    try {
        // ... rest unchanged
```

- [ ] **Step 4: Update ReportController::export()**

Replace `Request $request` with `ExportReportRequest $request`. Remove the `$request->validate([...])` block.

- [ ] **Step 5: Run tests**

```bash
php artisan test --compact
```

- [ ] **Step 6: Run Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Requests/Api/V1/Transaction/StoreTransactionRequest.php app/Http/Requests/Api/V1/Report/ExportReportRequest.php app/Http/Controllers/Api/V1/TransactionController.php app/Http/Controllers/Api/V1/ReportController.php
git commit -m "feat(validation): extract TransactionController and ReportController to Form Requests"
```

---

### Task 3: CustomerController Form Requests

**Files:**
- Create: `app/Http/Requests/Api/V1/Customer/UploadDocumentRequest.php`
- Create: `app/Http/Requests/Api/V1/Customer/SearchCustomerRequest.php`
- Modify: `app/Http/Controllers/Api/V1/CustomerController.php`

**Interfaces:**
- Consumes: `ApiFormRequest` from Task 1
- Produces: `UploadDocumentRequest`, `SearchCustomerRequest`

- [ ] **Step 1: Create UploadDocumentRequest**

```php
<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Http\Requests\Api\V1\ApiFormRequest;

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

- [ ] **Step 2: Create SearchCustomerRequest**

```php
<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Http\Requests\Api\V1\ApiFormRequest;

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

- [ ] **Step 3: Update CustomerController::uploadDocument()**

Replace `Request $request` with `UploadDocumentRequest $request`. Remove inline validation. Change `$validated` to `$request->validated()`.

```php
public function uploadDocument(UploadDocumentRequest $request, int $id): JsonResponse
{
    $customer = Customer::findOrFail($id);

    $validated = $request->validated();

    $file = $request->file('document');
    // ... rest unchanged
```

- [ ] **Step 4: Update CustomerController::searchForTransaction()**

Replace `Request $request` with `SearchCustomerRequest $request`. Remove inline validation.

```php
public function searchForTransaction(SearchCustomerRequest $request): JsonResponse
{
    $validated = $request->validated();

    $results = $this->customerService->searchCustomers($validated['query']);
    // ... rest unchanged
```

- [ ] **Step 5: Run tests**

```bash
php artisan test --compact
```

- [ ] **Step 6: Run Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Requests/Api/V1/Customer/UploadDocumentRequest.php app/Http/Requests/Api/V1/Customer/SearchCustomerRequest.php app/Http/Controllers/Api/V1/CustomerController.php
git commit -m "feat(validation): extract CustomerController uploadDocument and searchForTransaction to Form Requests"
```

---

### Task 4: TellerAllocationController Form Requests (4 methods)

**Files:**
- Create: `app/Http/Requests/Api/V1/TellerAllocation/ApproveAllocationRequest.php`
- Create: `app/Http/Requests/Api/V1/TellerAllocation/RejectAllocationRequest.php`
- Create: `app/Http/Requests/Api/V1/TellerAllocation/ModifyAllocationRequest.php`
- Create: `app/Http/Requests/Api/V1/TellerAllocation/MyActiveAllocationRequest.php`
- Modify: `app/Http/Controllers/Api/V1/TellerAllocationController.php`

**Interfaces:**
- Consumes: `ApiFormRequest` from Task 1
- Produces: `ApproveAllocationRequest`, `RejectAllocationRequest`, `ModifyAllocationRequest`, `MyActiveAllocationRequest`

- [ ] **Step 1: Create ApproveAllocationRequest**

```php
<?php

namespace App\Http\Requests\Api\V1\TellerAllocation;

use App\Http\Requests\Api\V1\ApiFormRequest;

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

- [ ] **Step 2: Create RejectAllocationRequest**

```php
<?php

namespace App\Http\Requests\Api\V1\TellerAllocation;

use App\Http\Requests\Api\V1\ApiFormRequest;

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

- [ ] **Step 3: Create ModifyAllocationRequest**

```php
<?php

namespace App\Http\Requests\Api\V1\TellerAllocation;

use App\Http\Requests\Api\V1\ApiFormRequest;

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

- [ ] **Step 4: Create MyActiveAllocationRequest**

```php
<?php

namespace App\Http\Requests\Api\V1\TellerAllocation;

use App\Http\Requests\Api\V1\ApiFormRequest;

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

- [ ] **Step 5: Update TellerAllocationController**

Replace `Request $request` with specific Form Request types in `approve()`, `reject()`, `modify()`, `myActiveAllocation()`. Remove inline validation blocks. Use `$request->validated()`.

- [ ] **Step 6: Run tests**

```bash
php artisan test --compact
```

- [ ] **Step 7: Run Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Requests/Api/V1/TellerAllocation/ app/Http/Controllers/Api/V1/TellerAllocationController.php
git commit -m "feat(validation): extract TellerAllocationController to Form Requests"
```

---

### Task 5: Counter Controllers Form Requests (3 controllers, 4 methods)

**Files:**
- Create: `app/Http/Requests/Api/V1/Counter/InitiateOpeningRequest.php`
- Create: `app/Http/Requests/Api/V1/Counter/ApproveAndOpenRequest.php`
- Create: `app/Http/Requests/Api/V1/Counter/InitiateCloseRequest.php`
- Create: `app/Http/Requests/Api/V1/Counter/AcknowledgeHandoverRequest.php`
- Modify: `app/Http/Controllers/Api/V1/CounterOpeningController.php`
- Modify: `app/Http/Controllers/Api/V1/EmergencyCounterController.php`
- Modify: `app/Http/Controllers/Api/V1/CounterHandoverController.php`

**Interfaces:**
- Consumes: `ApiFormRequest` from Task 1
- Produces: `InitiateOpeningRequest`, `ApproveAndOpenRequest`, `InitiateCloseRequest`, `AcknowledgeHandoverRequest`

- [ ] **Step 1: Create InitiateOpeningRequest**

```php
<?php

namespace App\Http\Requests\Api\V1\Counter;

use App\Http\Requests\Api\V1\ApiFormRequest;

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

- [ ] **Step 2: Create ApproveAndOpenRequest**

```php
<?php

namespace App\Http\Requests\Api\V1\Counter;

use App\Http\Requests\Api\V1\ApiFormRequest;

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

- [ ] **Step 3: Create InitiateCloseRequest**

```php
<?php

namespace App\Http\Requests\Api\V1\Counter;

use App\Http\Requests\Api\V1\ApiFormRequest;

class InitiateCloseRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'reason' => 'required|string|max:500',
        ];
    }
}
```

- [ ] **Step 4: Create AcknowledgeHandoverRequest**

```php
<?php

namespace App\Http\Requests\Api\V1\Counter;

use App\Http\Requests\Api\V1\ApiFormRequest;

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

- [ ] **Step 5: Update CounterOpeningController**

Replace `Request $request` with `InitiateOpeningRequest` in `initiateOpeningRequest()` and `ApproveAndOpenRequest` in `approveAndOpen()`. Remove inline validation.

- [ ] **Step 6: Update EmergencyCounterController**

Replace `Request $request` with `InitiateCloseRequest` in `initiateClose()`. Remove inline validation.

- [ ] **Step 7: Update CounterHandoverController**

Replace `Request $request` with `AcknowledgeHandoverRequest` in `acknowledge()`. Remove inline validation.

- [ ] **Step 8: Run tests**

```bash
php artisan test --compact
```

- [ ] **Step 9: Run Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Requests/Api/V1/Counter/InitiateOpeningRequest.php app/Http/Requests/Api/V1/Counter/ApproveAndOpenRequest.php app/Http/Requests/Api/V1/Counter/InitiateCloseRequest.php app/Http/Requests/Api/V1/Counter/AcknowledgeHandoverRequest.php app/Http/Controllers/Api/V1/CounterOpeningController.php app/Http/Controllers/Api/V1/EmergencyCounterController.php app/Http/Controllers/Api/V1/CounterHandoverController.php
git commit -m "feat(validation): extract Counter controllers to Form Requests"
```

---

### Task 6: Screening + Sanction + EOD Controllers (4 controllers, 6 methods)

**Files:**
- Create: `app/Http/Requests/Api/V1/Screening/BatchScreenRequest.php`
- Create: `app/Http/Requests/Api/V1/SanctionList/FilterEntriesRequest.php`
- Create: `app/Http/Requests/Api/V1/SanctionList/StoreEntryRequest.php`
- Create: `app/Http/Requests/Api/V1/Eod/ShowReconciliationRequest.php`
- Create: `app/Http/Requests/Api/V1/Eod/CounterReconciliationRequest.php`
- Create: `app/Http/Requests/Api/V1/Eod/ExportReconciliationRequest.php`
- Modify: `app/Http/Controllers/Api/V1/ScreeningController.php`
- Modify: `app/Http/Controllers/Api/V1/SanctionListController.php`
- Modify: `app/Http/Controllers/Api/V1/EodReconciliationController.php`

**Interfaces:**
- Consumes: `ApiFormRequest` from Task 1
- Produces: `BatchScreenRequest`, `FilterEntriesRequest`, `StoreEntryRequest` (Api\V1 version), `ShowReconciliationRequest`, `CounterReconciliationRequest`, `ExportReconciliationRequest`

- [ ] **Step 1: Create BatchScreenRequest**

```php
<?php

namespace App\Http\Requests\Api\V1\Screening;

use App\Http\Requests\Api\V1\ApiFormRequest;

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

- [ ] **Step 2: Create FilterEntriesRequest**

```php
<?php

namespace App\Http\Requests\Api\V1\SanctionList;

use App\Http\Requests\Api\V1\ApiFormRequest;

class FilterEntriesRequest extends ApiFormRequest
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

- [ ] **Step 3: Create StoreEntryRequest (Api\V1 version)**

Note: The root-level `StoreSanctionEntryRequest` has different rules than the API controller's inline validation. Create a new API-specific one.

```php
<?php

namespace App\Http\Requests\Api\V1\SanctionList;

use App\Http\Requests\Api\V1\ApiFormRequest;

class StoreEntryRequest extends ApiFormRequest
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

- [ ] **Step 4: Create ShowReconciliationRequest**

```php
<?php

namespace App\Http\Requests\Api\V1\Eod;

use App\Http\Requests\Api\V1\ApiFormRequest;

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

- [ ] **Step 5: Create CounterReconciliationRequest**

```php
<?php

namespace App\Http\Requests\Api\V1\Eod;

use App\Http\Requests\Api\V1\ApiFormRequest;

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

- [ ] **Step 6: Create ExportReconciliationRequest**

```php
<?php

namespace App\Http\Requests\Api\V1\Eod;

use App\Http\Requests\Api\V1\ApiFormRequest;

class ExportReconciliationRequest extends ApiFormRequest
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

- [ ] **Step 7: Update ScreeningController::batchScreen()**

Replace `Request $request` with `BatchScreenRequest $request`. Remove inline validation.

- [ ] **Step 8: Update SanctionListController::entries() and storeEntry()**

Replace `Request $request` with `FilterEntriesRequest` in `entries()` and `StoreEntryRequest` in `storeEntry()`. Remove inline validation.

- [ ] **Step 9: Update EodReconciliationController**

Replace `Request $request` with appropriate Form Requests in `show()`, `counterReconciliation()`, `report()`. Remove inline validation.

- [ ] **Step 10: Run tests**

```bash
php artisan test --compact
```

- [ ] **Step 11: Run Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Requests/Api/V1/Screening/ app/Http/Requests/Api/V1/SanctionList/ app/Http/Requests/Api/V1/Eod/ app/Http/Controllers/Api/V1/ScreeningController.php app/Http/Controllers/Api/V1/SanctionListController.php app/Http/Controllers/Api/V1/EodReconciliationController.php
git commit -m "feat(validation): extract Screening, SanctionList, and Eod controllers to Form Requests"
```

---

### Task 7: Compliance Controllers Form Requests (6 controllers, 11 methods)

**Files:**
- Create: `app/Http/Requests/Api/V1/Compliance/DismissFindingRequest.php`
- Create: `app/Http/Requests/Api/V1/Compliance/LockRiskRequest.php`
- Create: `app/Http/Requests/Api/V1/Compliance/AuditTrailRequest.php`
- Create: `app/Http/Requests/Api/V1/Compliance/AuditTrailExportRequest.php`
- Create: `app/Http/Requests/Api/V1/Compliance/SubmitQuestionnaireRequest.php`
- Create: `app/Http/Requests/Api/V1/Compliance/RejectEddRequest.php`
- Create: `app/Http/Requests/Api/V1/Compliance/AddCaseNoteRequest.php`
- Create: `app/Http/Requests/Api/V1/Compliance/CloseCaseRequest.php`
- Create: `app/Http/Requests/Api/V1/Compliance/BulkAssignAlertRequest.php`
- Create: `app/Http/Requests/Api/V1/Compliance/BulkResolveAlertRequest.php`
- Modify: `app/Http/Controllers/Api/V1/Compliance/FindingController.php`
- Modify: `app/Http/Controllers/Api/V1/Compliance/RiskController.php`
- Modify: `app/Http/Controllers/Api/V1/Compliance/DashboardController.php`
- Modify: `app/Http/Controllers/Api/V1/Compliance/EddController.php`
- Modify: `app/Http/Controllers/Api/V1/Compliance/CaseController.php`
- Modify: `app/Http/Controllers/Api/V1/Compliance/AlertController.php`

**Interfaces:**
- Consumes: `ApiFormRequest` from Task 1
- Produces: All 10 Compliance Form Request classes

- [ ] **Step 1: Create DismissFindingRequest**

```php
<?php

namespace App\Http\Requests\Api\V1\Compliance;

use App\Http\Requests\Api\V1\ApiFormRequest;

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

- [ ] **Step 2: Create LockRiskRequest**

```php
<?php

namespace App\Http\Requests\Api\V1\Compliance;

use App\Http\Requests\Api\V1\ApiFormRequest;

class LockRiskRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'reason' => 'required|string|max:500',
        ];
    }
}
```

- [ ] **Step 3: Create AuditTrailRequest**

```php
<?php

namespace App\Http\Requests\Api\V1\Compliance;

use App\Http\Requests\Api\V1\ApiFormRequest;

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

- [ ] **Step 4: Create AuditTrailExportRequest**

```php
<?php

namespace App\Http\Requests\Api\V1\Compliance;

use App\Http\Requests\Api\V1\ApiFormRequest;

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

- [ ] **Step 5: Create SubmitQuestionnaireRequest**

```php
<?php

namespace App\Http\Requests\Api\V1\Compliance;

use App\Http\Requests\Api\V1\ApiFormRequest;

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

- [ ] **Step 6: Create RejectEddRequest**

```php
<?php

namespace App\Http\Requests\Api\V1\Compliance;

use App\Http\Requests\Api\V1\ApiFormRequest;

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

- [ ] **Step 7: Create AddCaseNoteRequest**

```php
<?php

namespace App\Http\Requests\Api\V1\Compliance;

use App\Http\Requests\Api\V1\ApiFormRequest;

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

- [ ] **Step 8: Create CloseCaseRequest**

```php
<?php

namespace App\Http\Requests\Api\V1\Compliance;

use App\Http\Requests\Api\V1\ApiFormRequest;

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

- [ ] **Step 9: Create BulkAssignAlertRequest**

```php
<?php

namespace App\Http\Requests\Api\V1\Compliance;

use App\Http\Requests\Api\V1\ApiFormRequest;

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

- [ ] **Step 10: Create BulkResolveAlertRequest**

```php
<?php

namespace App\Http\Requests\Api\V1\Compliance;

use App\Http\Requests\Api\V1\ApiFormRequest;

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

- [ ] **Step 11: Update all 6 Compliance controllers**

Replace `Request $request` with specific Form Request types. Remove inline validation blocks. Use `$request->validated()`.

- FindingController::dismiss() → DismissFindingRequest
- RiskController::lock() → LockRiskRequest
- DashboardController::auditTrail() → AuditTrailRequest
- DashboardController::auditTrailExport() → AuditTrailExportRequest
- EddController::submitQuestionnaire() → SubmitQuestionnaireRequest
- EddController::reject() → RejectEddRequest
- CaseController::addNote() → AddCaseNoteRequest
- CaseController::close() → CloseCaseRequest
- AlertController::bulkAssign() → BulkAssignAlertRequest
- AlertController::bulkResolve() → BulkResolveAlertRequest

- [ ] **Step 12: Run tests**

```bash
php artisan test --compact
```

- [ ] **Step 13: Run Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Requests/Api/V1/Compliance/ app/Http/Controllers/Api/V1/Compliance/FindingController.php app/Http/Controllers/Api/V1/Compliance/RiskController.php app/Http/Controllers/Api/V1/Compliance/DashboardController.php app/Http/Controllers/Api/V1/Compliance/EddController.php app/Http/Controllers/Api/V1/Compliance/CaseController.php app/Http/Controllers/Api/V1/Compliance/AlertController.php
git commit -m "feat(validation): extract Compliance controllers to Form Requests"
```

---

### Task 8: SanctionController Form Request + Final Verification

**Files:**
- Create: `app/Http/Requests/Api/V1/Sanction/SearchSanctionRequest.php`
- Modify: `app/Http/Controllers/Api/V1/SanctionController.php`

**Interfaces:**
- Consumes: `ApiFormRequest` from Task 1
- Produces: `SearchSanctionRequest`

- [ ] **Step 1: Create SearchSanctionRequest**

```php
<?php

namespace App\Http\Requests\Api\V1\Sanction;

use App\Http\Requests\Api\V1\ApiFormRequest;

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

- [ ] **Step 2: Update SanctionController::search()**

Replace `Request $request` with `SearchSanctionRequest $request`. Remove inline validation.

- [ ] **Step 3: Run full test suite**

```bash
php artisan test --compact
```

- [ ] **Step 4: Verify no remaining inline validation in API controllers**

```bash
grep -rn '->validate\[' app/Http/Controllers/Api/V1/ --include='*.php' | grep -v 'validated()'
```

Expected: zero results (all inline `validate()` calls replaced with Form Requests).

- [ ] **Step 5: Run Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Requests/Api/V1/Sanction/SearchSanctionRequest.php app/Http/Controllers/Api/V1/SanctionController.php
git commit -m "feat(validation): extract SanctionController search to Form Request — all API v1 validation now uses Form Requests"
```

- [ ] **Step 6: Final verification — run full test suite one more time**

```bash
php artisan test --compact
```

All 978+ tests should pass with 0 warnings.

- [ ] **Step 7: Push to GitHub**

```bash
git push origin main
```
