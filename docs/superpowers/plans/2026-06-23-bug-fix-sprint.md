# Bug-Fix Sprint Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development` (recommended) or `superpowers:executing-plans` to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix the concrete functional, performance, and data-integrity bugs identified in the cems-my codebase audit: customer risk-rating UI mismatch, sanctions update field drift, regulatory report license placeholder, counter-allocation scoping/currency-resolution bugs, query-log false positives, and orphaned Blade views.

**Architecture:** Each bug is isolated to a small surface area (a few views, one controller method, or one service). Fixes follow existing Laravel 10 / Tailwind CSS 4 conventions and are guarded by targeted PHPUnit feature/unit tests. No new dependencies or application structure changes are required.

**Tech Stack:** Laravel 10, PHP 8.3, PHPUnit 10, Tailwind CSS 4, SQLite/MySQL, Redis cache.

---

## Bug inventory & priority

| ID | Bug | Severity | Evidence |
|---|---|---|---|
| B1 | Customer views use `risk_level` instead of the model attribute `risk_rating`; risk badge, filter, and edit form are broken. | High | `resources/views/customers/*.blade.php`; `UpdateCustomerRequest` validates `risk_rating` |
| B2 | Customer create/edit ID-type options do not match validation (`IC` vs `MyKad`). | High | `create.blade.php`, `edit.blade.php`, `StoreCustomerRequest` |
| B3 | `Customer::latestRiskSnapshot()` uses `latest()` without `latestOfMany()`, producing incorrect/inefficient eager loads. | Medium | `app/Models/Customer.php:185` |
| B4 | Web sanctions entry update uses `date_listed` while the model column is `listing_date`; the date is silently lost. | High | `SanctionListController.php:192`, `UpdateSanctionEntryRequest.php:15` |
| B5 | `ReportingService` reads the license from non-existent `app.license_number`; generated reports always show the placeholder. | High | `ReportingService.php:256`, `config/cems.php:106` |
| B6 | `CounterOpeningWorkflowService::approveAndOpen` does not scope pending allocations by branch or counter. | Medium | `CounterOpeningWorkflowService.php:62-67` |
| B7 | `CounterService::resolveCurrencies` drops numeric currency-ID keys, so `TillBalance` rows are skipped when numeric IDs are passed. | Medium | `CounterService.php:518-541` |
| B8 | `QueryLoggingService::detectNPlusOne` flags any repeated query as an N+1, creating noise. | Low | `QueryLoggingService.php:99-123` |
| B9 | 11 orphaned Blade views remain from the prior cleanup. | Low | `docs/orphaned-code-report.md` |

---

## File map

| File | Responsibility in this plan |
|---|---|
| `resources/views/customers/index.blade.php` | Fix risk-rating filter and badge. |
| `resources/views/customers/show.blade.php` | Fix risk-rating badge display. |
| `resources/views/customers/create.blade.php` | Fix ID-type options; remove ignored risk-level input. |
| `resources/views/customers/edit.blade.php` | Fix ID-type and risk-rating inputs. |
| `app/Models/Customer.php` | Fix `latestRiskSnapshot()` to use `latestOfMany()`. |
| `app/Http/Controllers/CustomerController.php` | Remove unused eager loads from `index()`. |
| `resources/views/compliance/sanctions/entries/edit.blade.php` | Rename date input to `listing_date`. |
| `app/Http/Requests/UpdateSanctionEntryRequest.php` | Validate `listing_date` instead of `date_listed`. |
| `app/Http/Controllers/Compliance/SanctionListController.php` | Persist `listing_date` on update. |
| `app/Services/Reporting/ReportingService.php` | Read license from `cems.license_number`. |
| `app/Services/Branch/CounterOpeningWorkflowService.php` | Scope pending allocation by branch/counter. |
| `app/Services/Branch/CounterService.php` | Preserve numeric currency-ID keys in `resolveCurrencies()`. |
| `app/Services/System/QueryLoggingService.php` | Make N+1 detection require varied IDs. |
| 11 orphaned view files (listed in Task 7) | Delete after verifying no route/controller references them. |
| New test files | Cover each fix. |

---

### Task 1: Fix customer risk-rating and ID-type UI mismatch

**Files:**
- Modify: `resources/views/customers/index.blade.php`
- Modify: `resources/views/customers/show.blade.php`
- Modify: `resources/views/customers/create.blade.php`
- Modify: `resources/views/customers/edit.blade.php`
- Create: `tests/Feature/CustomerRiskRatingDisplayTest.php`

- [ ] **Step 1: Update the customers index filter and badge**

Replace the risk-level filter (`resources/views/customers/index.blade.php:11`) with a risk-rating filter:

```blade
<x-select name="risk_rating" :options="['' => 'All Risk Ratings', 'Low' => 'Low', 'Medium' => 'Medium', 'High' => 'High']" :selected="request('risk_rating')" inline />
```

Replace the badge block (`resources/views/customers/index.blade.php:36-39`) with:

```blade
@php
$riskValue = $customer->risk_rating instanceof \App\Enums\RiskRating
    ? $customer->risk_rating->value
    : ($customer->risk_rating ?? 'gray');
$riskVariant = match (strtolower($riskValue)) {
    'high' => 'danger',
    'medium' => 'warning',
    default => 'success',
};
@endphp
<x-badge :variant="$riskVariant">
    {{ $customer->risk_rating instanceof \App\Enums\RiskRating
        ? $customer->risk_rating->value
        : ($customer->risk_rating ?? 'Unknown') }}
</x-badge>
```

- [ ] **Step 2: Fix the customer show badge**

Replace the risk-level logic in `resources/views/customers/show.blade.php:23-35` with:

```blade
@php
$riskValue = $customer->risk_rating instanceof \App\Enums\RiskRating
    ? $customer->risk_rating->value
    : ($customer->risk_rating ?? 'Medium');
$riskVariant = match (strtolower($riskValue)) {
    'high' => 'danger',
    'medium' => 'warning',
    default => 'success',
};
@endphp
<x-badge :variant="$riskVariant">
    {{ ucfirst($riskValue) }} Risk
</x-badge>
```

- [ ] **Step 3: Fix the edit form**

In `resources/views/customers/edit.blade.php`:

1. Update the ID-type select (`:14-21`) to match validated values:

```blade
<x-select
    name="id_type"
    label="ID Type"
    :options="['MyKad' => 'MyKad (Malaysian IC)', 'Passport' => 'Passport', 'Others' => 'Other ID']"
    placeholder="-- Select --"
    selected="{{ old('id_type', $customer->id_type?->value ?? $customer->id_type ?? '') }}"
    required
/>
```

2. Update the risk-level select (`:48-53`) to use the model attribute:

```blade
<x-select
    name="risk_rating"
    label="Risk Rating"
    :options="['Low' => 'Low', 'Medium' => 'Medium', 'High' => 'High']"
    selected="{{ old('risk_rating', $customer->risk_rating?->value ?? $customer->risk_rating ?? '') }}"
/>
```

- [ ] **Step 4: Fix the create form**

In `resources/views/customers/create.blade.php`:

1. Update the ID-type select (`:14-20`) to:

```blade
<x-select
    name="id_type"
    label="ID Type"
    :options="['MyKad' => 'MyKad (Malaysian IC)', 'Passport' => 'Passport', 'Others' => 'Other ID']"
    placeholder="-- Select --"
    required
/>
```

2. Remove the ignored `risk_level` radio group (`:41-46`). The backend (`CustomerService::createCustomer`) always sets the initial risk to `Low`, so the field is misleading.

- [ ] **Step 5: Write the feature test**

Create `tests/Feature/CustomerRiskRatingDisplayTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\RiskRating;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CustomerRiskRatingDisplayTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function index_shows_customer_risk_rating(): void
    {
        $user = User::factory()->create(['role' => UserRole::Manager]);
        Customer::factory()->create(['risk_rating' => RiskRating::High]);

        $response = $this->actingAs($user)->get(route('customers.index'));

        $response->assertStatus(200);
        $response->assertSee('High');
    }

    #[Test]
    public function edit_form_prefills_risk_rating(): void
    {
        $user = User::factory()->create(['role' => UserRole::Manager]);
        $customer = Customer::factory()->create(['risk_rating' => RiskRating::Medium]);

        $response = $this->actingAs($user)->get(route('customers.edit', $customer));

        $response->assertStatus(200);
        $response->assertSee('value="Medium"', false);
    }

    #[Test]
    public function update_persists_risk_rating(): void
    {
        $user = User::factory()->create(['role' => UserRole::Manager]);
        $customer = Customer::factory()->create(['risk_rating' => RiskRating::Low]);

        $response = $this->actingAs($user)
            ->put(route('customers.update', $customer), [
                'full_name' => $customer->full_name,
                'id_type' => 'MyKad',
                'id_number' => '900123-01-2345',
                'date_of_birth' => '1990-01-23',
                'nationality' => 'Malaysian',
                'risk_rating' => 'High',
            ]);

        $response->assertRedirect(route('customers.show', $customer));
        $this->assertEquals(RiskRating::High, $customer->fresh()->risk_rating);
    }

    #[Test]
    public function create_accepts_valid_id_type_options(): void
    {
        $user = User::factory()->create(['role' => UserRole::Manager]);

        $response = $this->actingAs($user)
            ->post(route('customers.store'), [
                'full_name' => 'Test User',
                'id_type' => 'MyKad',
                'id_number' => '900123-01-2345',
                'date_of_birth' => '1990-01-23',
                'nationality' => 'Malaysian',
                'phone' => '+60123456789',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('customers', ['full_name' => 'Test User', 'id_type' => 'MyKad']);
    }
}
```

- [ ] **Step 6: Run the test and ensure it passes**

```bash
php artisan test --compact tests/Feature/CustomerRiskRatingDisplayTest.php
```

Expected: 4 passing tests.

- [ ] **Step 7: Commit**

```bash
git add resources/views/customers/ tests/Feature/CustomerRiskRatingDisplayTest.php
git commit -m "fix(customer): align risk_rating and id_type UI with model/validation"
```

---

### Task 2: Fix `Customer::latestRiskSnapshot()` relation and index performance

**Files:**
- Modify: `app/Models/Customer.php`
- Modify: `app/Http/Controllers/CustomerController.php`
- Create: `tests/Feature/CustomerIndexPerformanceTest.php`

- [ ] **Step 1: Make `latestRiskSnapshot()` a true latest-of-many relation**

In `app/Models/Customer.php:185`, change:

```php
public function latestRiskSnapshot(): HasOne
{
    return $this->hasOne(RiskScoreSnapshot::class)->latestOfMany('snapshot_date');
}
```

- [ ] **Step 2: Remove unused eager loads from `CustomerController@index`**

The index view only uses `full_name`, `id_type`, `id_number_masked`, `nationality`, `risk_rating`, and `last_transaction_at`. Delete both relationship calls at `app/Http/Controllers/CustomerController.php:169-170`:

```php
$query->with(['documents', 'latestRiskSnapshot'])
    ->withCount(['documents']);
```

After the change, only pagination and ordering should remain on the query builder.

- [ ] **Step 3: Write a performance + correctness test**

Create `tests/Feature/CustomerIndexPerformanceTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\RiskScoreSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CustomerIndexPerformanceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function customer_index_uses_limited_queries(): void
    {
        $user = User::factory()->create(['role' => UserRole::Manager]);
        Customer::factory()->count(20)->create();

        DB::enableQueryLog();
        $response = $this->actingAs($user)->get(route('customers.index'));
        $queryCount = count(DB::getQueryLog());

        $response->assertStatus(200);
        $this->assertLessThanOrEqual(8, $queryCount, "Expected <= 8 queries, got {$queryCount}");
    }

    #[Test]
    public function latest_risk_snapshot_returns_most_recent_snapshot(): void
    {
        $customer = Customer::factory()->create();
        RiskScoreSnapshot::factory()->create([
            'customer_id' => $customer->id,
            'snapshot_date' => now()->subDays(5),
            'overall_score' => 10,
        ]);
        $latest = RiskScoreSnapshot::factory()->create([
            'customer_id' => $customer->id,
            'snapshot_date' => now(),
            'overall_score' => 90,
        ]);

        $found = $customer->fresh()->latestRiskSnapshot;

        $this->assertNotNull($found);
        $this->assertEquals($latest->id, $found->id);
        $this->assertEquals(90, $found->overall_score);
    }
}
```

- [ ] **Step 4: Run the test**

```bash
php artisan test --compact tests/Feature/CustomerIndexPerformanceTest.php
```

Expected: 2 passing tests.

- [ ] **Step 5: Commit**

```bash
git add app/Models/Customer.php app/Http/Controllers/CustomerController.php tests/Feature/CustomerIndexPerformanceTest.php
git commit -m "fix(customer): correct latestRiskSnapshot relation and reduce index query load"
```

---

### Task 3: Fix sanctions entry `listing_date` update mismatch

**Files:**
- Modify: `resources/views/compliance/sanctions/entries/edit.blade.php`
- Modify: `app/Http/Requests/UpdateSanctionEntryRequest.php`
- Modify: `app/Http/Controllers/Compliance/SanctionListController.php`
- Create: `tests/Feature/SanctionEntryUpdateDateTest.php`

- [ ] **Step 1: Rename the date input in the edit view**

In `resources/views/compliance/sanctions/entries/edit.blade.php:20`, change:

```blade
<x-input type="date" name="listing_date" label="Date Listed" value="{{ old('listing_date', $sanctionEntry->listing_date?->format('Y-m-d')) }}" />
```

- [ ] **Step 2: Update the form request rule**

In `app/Http/Requests/UpdateSanctionEntryRequest.php:15`, change:

```php
'listing_date' => 'nullable|date',
```

- [ ] **Step 3: Persist the correct field in the controller**

In `app/Http/Controllers/Compliance/SanctionListController.php:192`, change:

```php
'listing_date' => $validated['listing_date'] ?? null,
```

- [ ] **Step 4: Write the test**

Create `tests/Feature/SanctionEntryUpdateDateTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\SanctionEntry;
use App\Models\SanctionList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SanctionEntryUpdateDateTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function update_persists_listing_date(): void
    {
        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $list = SanctionList::factory()->create();
        $entry = SanctionEntry::factory()->create([
            'list_id' => $list->id,
            'listing_date' => null,
        ]);

        $response = $this->actingAs($user)
            ->put(route('compliance.sanctions.entries.update', $entry), [
                'entity_name' => $entry->entity_name,
                'entity_type' => $entry->entity_type?->value ?? 'Individual',
                'list_source' => $list->name,
                'listing_date' => '2024-03-15',
            ]);

        $response->assertRedirect();
        $this->assertEquals('2024-03-15', $entry->fresh()->listing_date->format('Y-m-d'));
    }
}
```

- [ ] **Step 5: Run the test**

```bash
php artisan test --compact tests/Feature/SanctionEntryUpdateDateTest.php
```

Expected: 1 passing test.

- [ ] **Step 6: Commit**

```bash
git add resources/views/compliance/sanctions/entries/edit.blade.php app/Http/Requests/UpdateSanctionEntryRequest.php app/Http/Controllers/Compliance/SanctionListController.php tests/Feature/SanctionEntryUpdateDateTest.php
git commit -m "fix(sanctions): persist listing_date correctly on web entry update"
```

---

### Task 4: Fix regulatory reporting license number config

**Files:**
- Modify: `app/Services/Reporting/ReportingService.php`
- Modify: `tests/Unit/ReportingServiceTest.php`

- [ ] **Step 1: Use the correct config key**

In `app/Services/Reporting/ReportingService.php:256`, change:

```php
'license_number' => config('cems.license_number', 'MSB-XXXXXXX'),
```

- [ ] **Step 2: Add a license-number assertion to the existing test**

Open `tests/Unit/ReportingServiceTest.php` and add a test (or extend the existing LMCA test). Example new test:

```php
#[Test]
public function generate_form_lmca_uses_configured_license_number(): void
{
    config(['cems.license_number' => 'MSB-TEST-12345']);

    $result = $this->service->generateFormLMCA(now()->format('Y-m'));

    $this->assertEquals('MSB-TEST-12345', $result['license_number']);
}
```

- [ ] **Step 3: Run the test**

```bash
php artisan test --compact tests/Unit/ReportingServiceTest.php
```

Expected: all tests pass.

- [ ] **Step 4: Commit**

```bash
git add app/Services/Reporting/ReportingService.php tests/Unit/ReportingServiceTest.php
git commit -m "fix(reporting): read license number from cems.license_number config"
```

---

### Task 5: Scope counter opening allocations and fix numeric currency resolution

**Files:**
- Modify: `app/Services/Branch/CounterOpeningWorkflowService.php`
- Modify: `app/Services/Branch/CounterService.php`
- Create: `tests/Feature/CounterOpeningAllocationScopeTest.php`

- [ ] **Step 1: Scope the pending-allocation lookup**

In `app/Services/Branch/CounterOpeningWorkflowService.php:62-67`, replace:

```php
$allocation = TellerAllocation::where('user_id', $teller->id)
    ->where('currency_code', $currency)
    ->where('status', TellerAllocationStatus::PENDING->value)
    ->whereDate('session_date', '<=', $today)
    ->orderByDesc('session_date')
    ->first();
```

with:

```php
$allocation = TellerAllocation::where('user_id', $teller->id)
    ->where('branch_id', $teller->branch_id)
    ->where('counter_id', $counter->id)
    ->where('currency_code', $currency)
    ->where('status', TellerAllocationStatus::PENDING->value)
    ->whereDate('session_date', '<=', $today)
    ->orderByDesc('session_date')
    ->first();
```

- [ ] **Step 2: Preserve numeric currency-ID keys**

In `app/Services/Branch/CounterService.php:518-541`, replace `resolveCurrencies` with:

```php
private function resolveCurrencies(array $floats): array
{
    $ids = collect($floats)->pluck('currency_id')->unique()->toArray();

    $numericIds = array_filter($ids, 'is_numeric');
    $stringCodes = array_filter($ids, fn ($id) => ! is_numeric($id));

    $resolved = [];

    foreach ($stringCodes as $code) {
        $resolved[$code] = $code;
    }

    if (! empty($numericIds)) {
        $currencies = Currency::whereIn('id', $numericIds)->pluck('code', 'id');
        foreach ($currencies as $id => $code) {
            $resolved[$id] = $code;
            $resolved[$code] = $code;
        }
    }

    return $resolved;
}
```

- [ ] **Step 3: Write the scope test**

Create `tests/Feature/CounterOpeningAllocationScopeTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\BranchPool;
use App\Models\Counter;
use App\Models\Currency;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Branch\BranchPoolService;
use App\Services\Branch\CounterOpeningWorkflowService;
use App\Services\Branch\CounterService;
use App\Services\Branch\TellerAllocationService;
use App\Services\System\MathService;
use App\Services\ThresholdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CounterOpeningAllocationScopeTest extends TestCase
{
    use RefreshDatabase;

    private function makeWorkflow(): CounterOpeningWorkflowService
    {
        $mathService = new MathService;
        $branchPoolService = new BranchPoolService($mathService);
        $tellerAllocationService = new TellerAllocationService($branchPoolService, $mathService);
        $counterService = new CounterService($tellerAllocationService, new ThresholdService);

        return new CounterOpeningWorkflowService(
            $branchPoolService,
            $tellerAllocationService,
            $counterService,
            resolve(AuditService::class)
        );
    }

    #[Test]
    public function approve_and_open_scopes_allocation_to_counter(): void
    {
        $currency = Currency::where('code', 'USD')->firstOrFail();
        $branch = Branch::factory()->create();
        $pool = BranchPool::factory()->create([
            'branch_id' => $branch->id,
            'currency_code' => 'USD',
            'available_balance' => '100000.0000',
        ]);
        $counterA = Counter::factory()->create(['branch_id' => $branch->id]);
        $counterB = Counter::factory()->create(['branch_id' => $branch->id]);
        $manager = User::factory()->create(['role' => UserRole::Manager, 'branch_id' => $branch->id]);
        $teller = User::factory()->create(['role' => UserRole::Teller, 'branch_id' => $branch->id]);

        $workflow = $this->makeWorkflow();
        $workflow->initiateOpeningRequest($teller, $counterA, ['USD' => '5000.0000']);

        $session = $workflow->approveAndOpen(
            $manager,
            $counterA,
            $teller,
            ['USD' => '5000.0000']
        );

        $this->assertEquals($counterA->id, $session->counter_id);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No pending allocation found for USD');
        $workflow->approveAndOpen(
            $manager,
            $counterB,
            $teller,
            ['USD' => '5000.0000']
        );
    }

    #[Test]
    public function open_session_creates_till_balance_with_numeric_currency_id(): void
    {
        $currency = Currency::factory()->create(['code' => 'EUR', 'is_active' => true]);
        $branch = Branch::factory()->create();
        $counter = Counter::factory()->create(['branch_id' => $branch->id]);
        $teller = User::factory()->create(['role' => UserRole::Teller, 'branch_id' => $branch->id]);

        $service = new CounterService(
            resolve(TellerAllocationService::class),
            new ThresholdService
        );

        $session = $service->openSession($counter, $teller, [
            ['currency_id' => (string) $currency->id, 'amount' => '1000.00'],
        ]);

        $this->assertDatabaseHas('till_balances', [
            'till_id' => (string) $counter->id,
            'currency_code' => 'EUR',
            'opening_balance' => '1000.00',
        ]);
    }
}
```

- [ ] **Step 4: Run the test**

```bash
php artisan test --compact tests/Feature/CounterOpeningAllocationScopeTest.php
```

Expected: 2 passing tests.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Branch/CounterOpeningWorkflowService.php app/Services/Branch/CounterService.php tests/Feature/CounterOpeningAllocationScopeTest.php
git commit -m "fix(counters): scope opening allocations by branch/counter and resolve numeric currency IDs"
```

---

### Task 6: Reduce N+1 false positives in query logging

**Files:**
- Modify: `app/Services/System/QueryLoggingService.php`
- Create: `tests/Unit/QueryLoggingServiceTest.php`

- [ ] **Step 1: Replace naive repeated-query detection**

In `app/Services/System/QueryLoggingService.php:99-123`, replace `detectNPlusOne` with:

```php
private function detectNPlusOne(array $queries, Request $request): void
{
    $patterns = [];

    foreach ($queries as $query) {
        $pattern = $this->normalizeQuery($query['query']);
        $id = $this->extractFirstIntegerBinding($query['query'], $query['bindings'] ?? []);

        if (! isset($patterns[$pattern])) {
            $patterns[$pattern] = ['count' => 0, 'ids' => []];
        }

        $patterns[$pattern]['count']++;
        if ($id !== null) {
            $patterns[$pattern]['ids'][$id] = true;
        }
    }

    foreach ($patterns as $pattern => $data) {
        if ($data['count'] >= 3 && count($data['ids']) >= 2) {
            Log::warning('Potential N+1 query detected', [
                'pattern' => $pattern,
                'count' => $data['count'],
                'unique_ids' => count($data['ids']),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
            ]);
        }
    }
}

private function extractFirstIntegerBinding(string $sql, array $bindings): ?int
{
    foreach ($bindings as $binding) {
        if (is_int($binding)) {
            return $binding;
        }
        if (is_string($binding) && ctype_digit($binding)) {
            return (int) $binding;
        }
    }

    return null;
}
```

- [ ] **Step 2: Write the unit test**

Create `tests/Unit/QueryLoggingServiceTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Services\System\QueryLoggingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class QueryLoggingServiceTest extends TestCase
{
    #[Test]
    public function it_warns_when_same_query_repeats_with_different_ids(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->with('Potential N+1 query detected', \Mockery::subset(['count' => 3, 'unique_ids' => 3]));

        $service = new QueryLoggingService;
        $service->enable();

        foreach ([1, 2, 3] as $id) {
            \DB::select('select * from customers where id = ?', [$id]);
        }

        $service->analyzeAndLog(Request::create('/test'));
    }

    #[Test]
    public function it_does_not_warn_for_identical_repeated_queries(): void
    {
        Log::shouldReceive('warning')->never();

        $service = new QueryLoggingService;
        $service->enable();

        foreach (range(1, 3) as $_) {
            \DB::select('select count(*) from transactions');
        }

        $service->analyzeAndLog(Request::create('/test'));
    }
}
```

- [ ] **Step 3: Run the test**

```bash
php artisan test --compact tests/Unit/QueryLoggingServiceTest.php
```

Expected: 2 passing tests.

- [ ] **Step 4: Commit**

```bash
git add app/Services/System/QueryLoggingService.php tests/Unit/QueryLoggingServiceTest.php
git commit -m "fix(logging): reduce N+1 false positives by requiring varied IDs"
```

---

### Task 7: Verify and remove orphaned Blade views

**Files:**
- Delete (after verification): the 11 views listed in `docs/orphaned-code-report.md`

- [ ] **Step 1: Verify each view has no route/controller reference**

Run the following commands. If any view is referenced, remove it from the deletion list and update `docs/orphaned-code-report.md` instead.

```bash
for view in customers.kyc accounting.month-end compliance.edd-templates.index compliance.edd-templates.show compliance.reporting.schedule auth.change-password transactions.customer-history pages.performance pages.audit.index pages.branches.index pages.rates.index; do
  echo "=== $view ==="
  grep -R "view(['\"]$view" app/ routes/ resources/views/ --include="*.php" || echo "No static reference"
done
```

- [ ] **Step 2: Delete confirmed orphaned views**

```bash
rm resources/views/customers/kyc.blade.php
rm resources/views/accounting/month-end.blade.php
rm resources/views/compliance/edd-templates/index.blade.php
rm resources/views/compliance/edd-templates/show.blade.php
rm resources/views/compliance/reporting/schedule.blade.php
rm resources/views/auth/change-password.blade.php
rm resources/views/transactions/customer-history.blade.php
rm resources/views/pages/performance.blade.php
rm resources/views/pages/audit/index.blade.php
rm resources/views/pages/branches/index.blade.php
rm resources/views/pages/rates/index.blade.php
```

- [ ] **Step 3: Update the orphaned-code report**

In `docs/orphaned-code-report.md`, move the deleted views to the "Completed Cleanup" section and set the "NEEDS REVIEW" count to 0.

- [ ] **Step 4: Run the full test suite**

```bash
php artisan test --compact
```

Expected: all tests still pass.

- [ ] **Step 5: Commit**

```bash
git add docs/orphaned-code-report.md
git add -u resources/views/
git commit -m "chore(views): remove confirmed orphaned Blade views"
```

---

## Final verification

After all tasks are complete, run the full verification pipeline:

```bash
php artisan test --compact
vendor/bin/pint --dirty --format agent
```

Expected result:
- PHPUnit: 1141+ passing, 3 skipped (same baseline as before the sprint).
- Pint: no unformatted files.

---

## Self-review checklist

- [ ] **Spec coverage:** Every bug in the inventory (B1–B9) maps to a task.
- [ ] **Placeholder scan:** No `TBD`, `TODO`, or "implement later" steps remain.
- [ ] **Type consistency:** `risk_rating` is used consistently across views, requests, tests, and the model. `listing_date` is used consistently in the sanctions flow.
- [ ] **Test coverage:** Each functional change has a failing-then-passing test.
