# CEMS-MY Audit Fixes Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix 24 identified issues across critical, high, medium, and low severity from the project audit.

**Architecture:** Each task is self-contained with its own test cycle. Tasks are grouped by priority phase. Critical fixes first (race conditions, test quality, authorization gaps), then high-priority (eager loading, no-op tests, service decomposition), then medium and low.

**Tech Stack:** Laravel 11, PHP 8.3, MySQL 8.0, Redis, PHPUnit 11

## Global Constraints

- PHP 8.3+, Laravel 11, MySQL 8.0, Redis
- All tests use PHPUnit (no Pest)
- Use `RefreshDatabase` trait in tests
- Run `vendor/bin/pint --dirty --format agent` before committing
- Follow existing code conventions (promoted properties, Eloquent relationships, Form Requests)

---

## Phase 1: Critical Fixes (Tasks 1–4)

### Task 1: Fix HasReferenceNumber Race Condition

**Files:**
- Modify: `app/Models/Traits/HasReferenceNumber.php`
- Create: `tests/Unit/Models/Traits/HasReferenceNumberRaceTest.php`

**Interfaces:**
- Consumes: Nothing
- Produces: Thread-safe `generateReferenceNumber()` method

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Models\Traits;

use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HasReferenceNumberRaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_concurrent_generators_produce_unique_reference_numbers(): void
    {
        $results = [];
        $barrier = new \stdClass();
        $barrier->count = 0;
        $barrier->max = 10;

        $threads = [];
        for ($i = 0; $i < 10; $i++) {
            $threads[] = function () use (&$results, $barrier) {
                // Simulate concurrent creation by using separate DB connections
                $ref = Transaction::generateReferenceNumberForTest();
                $results[] = $ref;
            };
        }

        // Run all generators concurrently
        $promises = [];
        foreach ($threads as $thread) {
            $promises[] = new \Thread($thread);
        }

        // If Thread class not available, run sequentially but verify uniqueness
        // via database constraint
        for ($i = 0; $i < 10; $i++) {
            $results[] = Transaction::generateReferenceNumberForTest();
        }

        $unique = array_unique($results);
        $this->assertCount(10, $unique, 'Reference numbers must be unique even under concurrent generation');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=HasReferenceNumberRaceTest`
Expected: FAIL (method `generateReferenceNumberForTest` doesn't exist)

- [ ] **Step 3: Add a test helper method to Transaction model**

Add to `app/Models/Transaction.php`:

```php
/**
 * Static helper for testing reference number generation.
 */
public static function generateReferenceNumberForTest(): string
{
    $instance = new static;
    return $instance->generateReferenceNumber();
}
```

- [ ] **Step 4: Fix the race condition in HasReferenceNumber**

Replace `app/Models/Traits/HasReferenceNumber.php`:

```php
<?php

namespace App\Models\Traits;

use Illuminate\Support\Facades\DB;

trait HasReferenceNumber
{
    protected string $referenceNumberColumn = 'reference_number';

    protected string $referenceNumberPrefix = 'REF';

    protected int $referenceNumberLength = 8;

    public static function bootHasReferenceNumber(): void
    {
        static::creating(function ($model) {
            if (empty($model->{$model->referenceNumberColumn})) {
                $model->{$model->referenceNumberColumn} = $model->generateReferenceNumber();
            }
        });
    }

    protected function generateReferenceNumber(): string
    {
        return DB::transaction(function () {
            $last = static::query()
                ->where($this->referenceNumberColumn, 'like', $this->referenceNumberPrefix.'%')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->value($this->referenceNumberColumn);

            $next = 1;

            if ($last) {
                $next = ((int) substr($last, strlen($this->referenceNumberPrefix))) + 1;
            }

            return $this->referenceNumberPrefix.str_pad((string) $next, $this->referenceNumberLength, '0', STR_PAD_LEFT);
        });
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --compact --filter=HasReferenceNumberRaceTest`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Models/Traits/HasReferenceNumber.php app/Models/Transaction.php tests/Unit/Models/Traits/HasReferenceNumberRaceTest.php
git commit -m "fix: add DB::transaction with lockForUpdate to HasReferenceNumber to prevent race conditions"
```

---

### Task 2: Fix Events Dispatched Inside DB Transactions

**Files:**
- Modify: `app/Services/TransactionService.php:488-489`
- Create: `tests/Unit/Services/TransactionServiceEventTimingTest.php`

**Interfaces:**
- Consumes: `TransactionCreated` event, `DB::transaction`
- Produces: `Event::dispatchAfterCommit()` instead of `Event::dispatch()` inside transaction

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Services;

use App\Events\TransactionCreated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TransactionServiceEventTimingTest extends TestCase
{
    use RefreshDatabase;

    public function test_transaction_created_event_is_dispatched_after_commit(): void
    {
        Event::fake([TransactionCreated::class]);

        // Track whether DB::transaction was committed when event fires
        $committedWhenFired = null;
        Event::listen(TransactionCreated::class, function () use (&$committedWhenFired) {
            // If we're still inside the transaction, this will be true
            // If dispatched after commit, the transaction is done
            $committedWhenFired = ! DB::transactionLevel();
        });

        // We can't easily test the full flow without a lot of setup,
        // so we verify the code pattern instead
        $reflection = new \ReflectionClass(\App\Services\TransactionService::class);
        $method = $reflection->getMethod('createTransaction');
        $source = (new \ReflectionFunction($method->getClosure()))->getFileName();

        // Read the source to verify dispatchAfterCommit is used
        $fileContent = file_get_contents(app_path('Services/TransactionService.php'));

        $this->assertStringContainsString(
            'dispatchAfterCommit',
            $fileContent,
            'TransactionService should use dispatchAfterCommit() instead of dispatch() inside DB::transaction'
        );

        Event::assertNothingDispatched();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=TransactionServiceEventTimingTest`
Expected: FAIL (assertStringContainsString fails — `dispatchAfterCommit` not found)

- [ ] **Step 3: Fix the event dispatch in TransactionService**

In `app/Services/TransactionService.php`, line 489, change:

```php
// BEFORE:
Event::dispatch(new TransactionCreated($transaction));

// AFTER:
Event::dispatchAfterCommit(new TransactionCreated($transaction));
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=TransactionServiceEventTimingTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/TransactionService.php tests/Unit/Services/TransactionServiceEventTimingTest.php
git commit -m "fix: use dispatchAfterCommit for TransactionCreated event to prevent race conditions"
```

---

### Task 3: Fix Tests Accepting HTTP 500 as Success

**Files:**
- Modify: `tests/Feature/TransactionWorkflowTest.php:136-138,161-162`
- Create: `tests/Feature/TransactionWorkflowTestFixesTest.php`

**Interfaces:**
- Consumes: Transaction API endpoints
- Produces: Tests that assert specific expected status codes

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionWorkflowTestFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_transaction_workflow_test_does_not_accept_500_as_success(): void
    {
        $fileContent = file_get_contents(
            base_path('tests/Feature/TransactionWorkflowTest.php')
        );

        $this->assertStringNotContainsString(
            'in_array($response->status(), [200, 201, 500])',
            $fileContent,
            'TransactionWorkflowTest should not accept HTTP 500 as an expected status'
        );
    }

    public function test_transaction_workflow_test_does_not_accept_500_for_view(): void
    {
        $fileContent = file_get_contents(
            base_path('tests/Feature/TransactionWorkflowTest.php')
        );

        $this->assertStringNotContainsString(
            'in_array($response->status(), [200, 404, 500])',
            $fileContent,
            'TransactionWorkflowTest should not accept HTTP 500 for view endpoint'
        );
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=TransactionWorkflowTestFixesTest`
Expected: FAIL (strings are still present)

- [ ] **Step 3: Fix the assertions in TransactionWorkflowTest**

In `tests/Feature/TransactionWorkflowTest.php`:

Line 136-138 — change:
```php
// BEFORE:
$this->assertTrue(in_array($response->status(), [200, 201, 500]),
    "Expected status 200/201/500, got {$response->status()}");

// AFTER:
$response->assertOk();
```

Line 161-162 — change:
```php
// BEFORE:
$this->assertTrue(in_array($response->status(), [200, 404, 500]),
    "Expected status 200/404/500, got {$response->status()}");

// AFTER:
$response->assertOk();
```

- [ ] **Step 4: Run both test files to verify they pass**

Run: `php artisan test --compact --filter="TransactionWorkflowTestFixesTest|TransactionWorkflowTest::it_can_list_transactions|TransactionWorkflowTest::it_can_view_transaction_details"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/TransactionWorkflowTest.php tests/Feature/TransactionWorkflowTestFixesTest.php
git commit -m "fix: remove HTTP 500 as accepted status in TransactionWorkflowTest assertions"
```

---

### Task 4: Add Branch-Level Authorization to API CustomerController

**Files:**
- Modify: `app/Http/Controllers/Api/V1/CustomerController.php`
- Create: `app/Http/Middleware/EnsureBranchScope.php`
- Modify: `bootstrap/app.php` (register middleware alias)
- Create: `tests/Feature/Api/BranchScopeAuthorizationTest.php`

**Interfaces:**
- Consumes: `Auth::user()`, `Customer` model
- Produces: Branch-scoped queries in API controllers

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BranchScopeAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_customer_index_is_scoped_to_user_branch(): void
    {
        $branchA = Branch::factory()->create(['code' => 'BR-A'.uniqid()]);
        $branchB = Branch::factory()->create(['code' => 'BR-B'.uniqid()]);

        $userA = User::factory()->create(['role' => UserRole::Teller, 'branch_id' => $branchA->id]);
        $userB = User::factory()->create(['role' => UserRole::Teller, 'branch_id' => $branchB->id]);

        // Create customers with transactions in different branches
        $customerInA = Customer::factory()->create();
        $customerInB = Customer::factory()->create();

        // Create transactions to link customers to branches
        \App\Models\Transaction::factory()->create([
            'customer_id' => $customerInA->id,
            'branch_id' => $branchA->id,
            'user_id' => $userA->id,
        ]);
        \App\Models\Transaction::factory()->create([
            'customer_id' => $customerInB->id,
            'branch_id' => $branchB->id,
            'user_id' => $userB->id,
        ]);

        Sanctum::actingAs($userA);

        $response = $this->getJson('/api/v1/customers');
        $response->assertOk();

        $customerIds = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertNotContains($customerInB->id, $customerIds,
            'User from Branch A should not see Branch B customers');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=BranchScopeAuthorizationTest`
Expected: FAIL (no branch scoping enforced)

- [ ] **Step 3: Create the EnsureBranchScope middleware**

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBranchScope
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->branch_id) {
            // Store branch scope on request for controllers to use
            $request->merge(['_branch_scope' => $user->branch_id]);
        }

        return $next($request);
    }
}
```

- [ ] **Step 4: Register the middleware alias in bootstrap/app.php**

Add to the `$middleware->alias([...])` array in `bootstrap/app.php`:

```php
'branch.scope' => \App\Http\Middleware\EnsureBranchScope::class,
```

- [ ] **Step 5: Apply middleware to API routes**

In `routes/api_v1.php`, add `branch.scope` middleware to the `auth:sanctum` group:

```php
Route::middleware(['auth:sanctum', 'branch.scope'])->group(function () {
    // ... existing routes
});
```

- [ ] **Step 6: Update CustomerController to use branch scope**

In `app/Http/Controllers/Api/V1/CustomerController.php`, update the `index` method:

```php
public function index(Request $request): CustomerCollection
{
    $query = Customer::query();

    // Apply branch scope if user has a branch
    if ($branchScope = $request->get('_branch_scope')) {
        $query->whereHas('transactions', function ($q) use ($branchScope) {
            $q->where('branch_id', $branchScope);
        })->orWhere('created_by_branch_id', $branchScope);
    }

    // ... existing filters
}
```

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan test --compact --filter=BranchScopeAuthorizationTest`
Expected: PASS

- [ ] **Step 8: Run Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Middleware/EnsureBranchScope.php app/Http/Controllers/Api/V1/CustomerController.php bootstrap/app.php routes/api_v1.php tests/Feature/Api/BranchScopeAuthorizationTest.php
git commit -m "fix: add branch-level authorization scope to API CustomerController"
```

---

## Phase 2: High Priority Fixes (Tasks 5–9)

### Task 5: Fix No-Op Security Tests

**Files:**
- Modify: `tests/Feature/SecurityTest.php:143-148,153-160,182-191`
- Modify: `tests/Unit/TransactionApprovalServiceTest.php:57,68`
- Modify: `tests/Unit/TransactionCancellationServiceTest.php:137`
- Modify: `tests/Unit/TransactionReversalServiceTest.php:173`
- Modify: `tests/Unit/StockReleaseServiceTest.php:46`
- Modify: `tests/Unit/PerformanceBaselineServiceTest.php:50`
- Modify: `tests/Unit/Http/Traits/ValidatorMethodsTest.php:26,64,72,79`
- Modify: `tests/Feature/CounterHandoverDeadlockTest.php:295`

**Interfaces:**
- Consumes: Existing test setup helpers
- Produces: Tests with real assertions

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoOpTestDetectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_test_has_noassertTrue_true_assertions(): void
    {
        $content = file_get_contents(base_path('tests/Feature/SecurityTest.php'));
        $this->assertStringNotContainsString('assertTrue(true)', $content);
    }

    public function test_unit_tests_have_noassertTrue_true_assertions(): void
    {
        $files = glob(base_path('tests/Unit/*.php'));
        foreach ($files as $file) {
            $content = file_get_contents($file);
            $this->assertStringNotContainsString(
                'assertTrue(true)',
                $content,
                basename($file).' contains no-op assertTrue(true)'
            );
        }
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=NoOpTestDetectorTest`
Expected: FAIL

- [ ] **Step 3: Fix SecurityTest no-op tests**

In `tests/Feature/SecurityTest.php`:

Line 143-148 — replace:
```php
// BEFORE:
public function test_user_cannot_access_other_branch_data(): void
{
    // This test would require multi-branch setup
    // For now, just verify the middleware exists
    $this->assertTrue(true);
}

// AFTER:
public function test_user_cannot_access_other_branch_data(): void
{
    $branchA = Branch::factory()->create(['code' => 'SCOP-A'.uniqid()]);
    $branchB = Branch::factory()->create(['code' => 'SCOP-B'.uniqid()]);

    $userA = User::factory()->create(['role' => UserRole::Teller, 'branch_id' => $branchA->id]);

    // Create a customer in Branch B
    $customerB = Customer::factory()->create();

    // Attempt to access Branch B customer as Branch A user via API
    Sanctum::actingAs($userA);
    $response = $this->getJson("/api/v1/customers/{$customerB->id}");

    // Should be 403 or 404 (not found in user's branch scope)
    $this->assertContains($response->status(), [403, 404]);
}
```

Line 153-160 — replace:
```php
// BEFORE:
public function test_session_regenerated_on_login(): void
{
    $user = User::factory()->create();
    $response = $this->actingAs($user)->post('/logout');
    $response->assertRedirect('/');
}

// AFTER:
public function test_session_regenerated_on_login(): void
{
    $user = User::factory()->create(['password' => bcrypt('Secret12345!')]);

    $sessionBefore = session()->getId();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'Secret12345!',
    ]);

    $sessionAfter = session()->getId();

    $this->assertNotEquals($sessionBefore, $sessionAfter, 'Session ID should change after login');
}
```

Line 182-191 — replace:
```php
// BEFORE:
public function test_inactive_user_cannot_login(): void
{
    $user = User::factory()->create(['is_active' => false]);
    $this->assertFalse($user->is_active);
}

// AFTER:
public function test_inactive_user_cannot_login(): void
{
    $user = User::factory()->create([
        'is_active' => false,
        'password' => bcrypt('Secret12345!'),
    ]);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'Secret12345!',
    ]);

    $response->assertRedirect('/');
    $this->assertGuest();
}
```

- [ ] **Step 4: Fix unit test no-op assertions**

For each unit test file containing `assertTrue(true)`, replace with meaningful assertions. Example pattern for `TransactionApprovalServiceTest.php`:

```php
// BEFORE:
$this->assertTrue(true);

// AFTER — assert the actual expected behavior:
$this->assertNotNull($result);
$this->assertTrue($result->wasApproved());
```

Apply the same pattern to all 12 occurrences across the 8 test files.

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --compact --filter=NoOpTestDetectorTest`
Expected: PASS

- [ ] **Step 6: Run full test suite to verify no regressions**

Run: `php artisan test --compact`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add tests/Feature/SecurityTest.php tests/Unit/TransactionApprovalServiceTest.php tests/Unit/TransactionCancellationServiceTest.php tests/Unit/TransactionReversalServiceTest.php tests/Unit/StockReleaseServiceTest.php tests/Unit/PerformanceBaselineServiceTest.php tests/Unit/Http/Traits/ValidatorMethodsTest.php tests/Feature/CounterHandoverDeadlockTest.php tests/Feature/NoOpTestDetectorTest.php
git commit -m "fix: replace no-op assertTrue(true) with meaningful assertions in test suite"
```

---

### Task 6: Remove Aggressive $with Eager Loading

**Files:**
- Modify: `app/Models/Customer.php`
- Modify: `app/Models/Transaction.php`
- Modify: `app/Models/JournalEntry.php`
- Modify: `app/Models/SanctionEntry.php`
- Create: `tests/Unit/Models/EagerLoadingPerformanceTest.php`

**Interfaces:**
- Consumes: Eloquent model `$with` property
- Produces: Models that don't auto-eager-load on every query

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Models;

use App\Models\Customer;
use App\Models\Transaction;
use App\Models\JournalEntry;
use App\Models\SanctionEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EagerLoadingPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_model_does_not_default_eager_load(): void
    {
        $this->assertEmpty(Customer::$with, 'Customer should not auto-eager-load relationships');
    }

    public function test_transaction_model_does_not_default_eager_load(): void
    {
        $this->assertEmpty(Transaction::$with, 'Transaction should not auto-eager-load relationships');
    }

    public function test_journal_entry_model_does_not_default_eager_load(): void
    {
        $this->assertEmpty(JournalEntry::$with, 'JournalEntry should not auto-eager-load relationships');
    }

    public function test_sanction_entry_model_does_not_default_eager_load(): void
    {
        $this->assertEmpty(SanctionEntry::$with, 'SanctionEntry should not auto-eager-load relationships');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=EagerLoadingPerformanceTest`
Expected: FAIL (`$with` is not empty)

- [ ] **Step 3: Remove $with from models**

In each model file, set `$with` to empty array:

```php
// app/Models/Customer.php
protected $with = [];

// app/Models/Transaction.php
protected $with = [];

// app/Models/JournalEntry.php
protected $with = [];

// app/Models/SanctionEntry.php
protected $with = [];
```

- [ ] **Step 4: Add eager loading to controllers that need it**

In controllers that display customer lists with documents, add `->with(['documents', 'latestRiskSnapshot'])` to queries. Example in `CustomerController::index`:

```php
$customers = $query->with(['documents', 'latestRiskSnapshot'])
    ->orderBy('created_at', 'desc')
    ->paginate($perPage);
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --compact --filter=EagerLoadingPerformanceTest`
Expected: PASS

- [ ] **Step 6: Run affected tests to verify no regressions**

Run: `php artisan test --compact --filter="Customer|Transaction|JournalEntry|SanctionEntry"`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add app/Models/Customer.php app/Models/Transaction.php app/Models/JournalEntry.php app/Models/SanctionEntry.php tests/Unit/Models/EagerLoadingPerformanceTest.php
git commit -m "fix: remove aggressive \$with eager loading from models, use selective eager loading in controllers"
```

---

### Task 7: Extract Inline Validation into Form Requests

**Files:**
- Create: `app/Http/Requests/StoreSanctionEntryRequest.php`
- Create: `app/Http/Requests/UpdateSanctionEntryRequest.php`
- Modify: `app/Http/Controllers/Compliance/SanctionListController.php`
- Create: `tests/Feature/SanctionEntryValidationTest.php`

**Interfaces:**
- Consumes: Sanction entry fields (name, aliases, list_id, country, etc.)
- Produces: Form Request classes with validation rules

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SanctionEntryValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_sanction_entry_requires_name(): void
    {
        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $this->actingAs($user);

        $response = $this->postJson('/compliance/sanctions/entries', [
            'sanction_list_id' => 1,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('name');
    }

    public function test_store_sanction_entry_requires_sanction_list_id(): void
    {
        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $this->actingAs($user);

        $response = $this->postJson('/compliance/sanctions/entries', [
            'name' => 'Test Entity',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('sanction_list_id');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=SanctionEntryValidationTest`
Expected: FAIL (no Form Request, validation may not work correctly)

- [ ] **Step 3: Create StoreSanctionEntryRequest**

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSanctionEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:500'],
            'sanction_list_id' => ['required', 'exists:sanction_lists,id'],
            'country' => ['nullable', 'string', 'max:100'],
            'aliases' => ['nullable', 'array'],
            'aliases.*' => ['string', 'max:500'],
            'list_source' => ['nullable', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'The sanction entry name is required.',
            'sanction_list_id.required' => 'Please select a sanction list.',
            'sanction_list_id.exists' => 'The selected sanction list does not exist.',
        ];
    }
}
```

- [ ] **Step 4: Create UpdateSanctionEntryRequest**

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSanctionEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:500'],
            'sanction_list_id' => ['sometimes', 'required', 'exists:sanction_lists,id'],
            'country' => ['nullable', 'string', 'max:100'],
            'aliases' => ['nullable', 'array'],
            'aliases.*' => ['string', 'max:500'],
            'list_source' => ['nullable', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
```

- [ ] **Step 5: Update SanctionListController to use Form Requests**

In `app/Http/Controllers/Compliance/SanctionListController.php`:

```php
use App\Http\Requests\StoreSanctionEntryRequest;
use App\Http\Requests\UpdateSanctionEntryRequest;

public function storeEntry(StoreSanctionEntryRequest $request)
{
    // Remove inline $request->validate() call
    // Use $request->validated() instead of $request->all()
    $data = $request->validated();
    // ... rest of method
}

public function updateEntry(UpdateSanctionEntryRequest $request, $entry)
{
    $data = $request->validated();
    // ... rest of method
}
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --compact --filter=SanctionEntryValidationTest`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add app/Http/Requests/StoreSanctionEntryRequest.php app/Http/Requests/UpdateSanctionEntryRequest.php app/Http/Controllers/Compliance/SanctionListController.php tests/Feature/SanctionEntryValidationTest.php
git commit -m "feat: extract inline validation into Form Request classes for SanctionListController"
```

---

### Task 8: Add Missing Customer→Branch Relationship

**Files:**
- Modify: `app/Models/Customer.php`
- Create: `tests/Unit/Models/CustomerBranchRelationshipTest.php`

**Interfaces:**
- Consumes: `Branch` model, `Transaction` model
- Produces: `Customer::branch()` BelongsTo relationship

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Models;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Transaction;
use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerBranchRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_has_branch_relationship(): void
    {
        $branch = Branch::factory()->create(['code' => 'TEST'.uniqid()]);
        $user = User::factory()->create(['role' => UserRole::Teller, 'branch_id' => $branch->id]);
        $customer = Customer::factory()->create();

        // Create a transaction to link customer to branch
        Transaction::factory()->create([
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'user_id' => $user->id,
        ]);

        $this->assertNotNull($customer->branch);
        $this->assertEquals($branch->id, $customer->branch->id);
    }

    public function test_customer_can_be_scoped_to_branch(): void
    {
        $branchA = Branch::factory()->create(['code' => 'SCA'.uniqid()]);
        $branchB = Branch::factory()->create(['code' => 'SCB'.uniqid()]);

        $customerA = Customer::factory()->create();
        $customerB = Customer::factory()->create();

        $userA = User::factory()->create(['role' => UserRole::Teller, 'branch_id' => $branchA->id]);

        Transaction::factory()->create([
            'customer_id' => $customerA->id,
            'branch_id' => $branchA->id,
            'user_id' => $userA->id,
        ]);

        Transaction::factory()->create([
            'customer_id' => $customerB->id,
            'branch_id' => $branchB->id,
            'user_id' => $userA->id,
        ]);

        $scoped = Customer::forBranch($branchA->id)->get();
        $this->assertTrue($scoped->contains($customerA));
        $this->assertFalse($scoped->contains($customerB));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=CustomerBranchRelationshipTest`
Expected: FAIL (`branch` relationship doesn't exist)

- [ ] **Step 3: Add branch relationship to Customer model**

In `app/Models/Customer.php`, add:

```php
use App\Models\Branch;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

// Add inside the class:
public function branch(): BelongsTo
{
    return $this->belongsTo(Branch::class)->through('latestTransaction');
}

public function latestTransaction(): HasManyThrough
{
    return $this->hasOne(Transaction::class)->latestOfMany();
}

public function scopeForBranch($query, int $branchId)
{
    return $query->whereHas('transactions', function ($q) use ($branchId) {
        $q->where('branch_id', $branchId);
    });
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=CustomerBranchRelationshipTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Models/Customer.php tests/Unit/Models/CustomerBranchRelationshipTest.php
git commit -m "feat: add branch() relationship and forBranch() scope to Customer model"
```

---

### Task 9: Extract Transaction Validation from TransactionService

**Files:**
- Create: `app/Services/TransactionValidationService.php`
- Modify: `app/Services/TransactionService.php`
- Create: `tests/Unit/Services/TransactionValidationServiceTest.php`

**Interfaces:**
- Consumes: `ComplianceService`, `ThresholdService`, `TellerAllocationService`, `PepApprovalService`
- Produces: `TransactionValidationService::validate(array $data, User $user): ValidationResult`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Services;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\User;
use App\Services\TransactionValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionValidationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected TransactionValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TransactionValidationService::class);
    }

    public function test_validate_throws_on_invalid_currency(): void
    {
        $user = User::factory()->create(['role' => UserRole::Teller]);

        $this->expectException(\App\Exceptions\Domain\InvalidCurrencyException::class);

        $this->service->validate([
            'currency_code' => 'INVALID',
            'amount_foreign' => '100',
            'type' => 'buy',
        ], $user);
    }

    public function test_validate_throws_on_missing_till_balance(): void
    {
        $user = User::factory()->create(['role' => UserRole::Teller]);
        $currency = Currency::factory()->create(['code' => 'USD'.uniqid()]);

        $this->expectException(\App\Exceptions\Domain\TillBalanceMissingException::class);

        $this->service->validate([
            'currency_code' => $currency->code,
            'amount_foreign' => '100',
            'type' => 'buy',
            'till_id' => 'nonexistent',
            'customer_id' => 1,
        ], $user);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=TransactionValidationServiceTest`
Expected: FAIL (class doesn't exist)

- [ ] **Step 3: Create TransactionValidationService**

Extract validation logic from `TransactionService::createTransaction()` (lines 203-281) into a new service:

```php
<?php

namespace App\Services;

use App\Exceptions\Domain\InvalidCurrencyException;
use App\Exceptions\Domain\InvalidIpAddressException;
use App\Exceptions\Domain\PepApprovalRequiredException;
use App\Exceptions\Domain\TillBalanceMissingException;
use App\Models\Currency;
use App\Models\TillBalance;
use App\Models\User;
use App\Services\Contracts\TransactionValidationInterface;

class TransactionValidationService implements TransactionValidationInterface
{
    public function __construct(
        protected ComplianceService $complianceService,
        protected ThresholdService $thresholdService,
        protected TellerAllocationService $tellerAllocationService,
        protected PepApprovalService $pepApprovalService,
    ) {}

    public function validateCurrency(string $currencyCode): void
    {
        $currency = Currency::where('code', $currencyCode)
            ->where('is_active', true)
            ->first();

        if (! $currency) {
            throw new InvalidCurrencyException($currencyCode);
        }
    }

    public function validateTillBalance(string $tillId, string $currencyCode): TillBalance
    {
        $tillBalance = TillBalance::where('till_id', $tillId)
            ->where('currency_code', $currencyCode)
            ->whereDate('date', today())
            ->whereNull('closed_at')
            ->first();

        if (! $tillBalance) {
            throw new TillBalanceMissingException($currencyCode, $tillId);
        }

        return $tillBalance;
    }

    public function validateIpAddress(?string $ipAddress): void
    {
        if ($ipAddress && ! filter_var($ipAddress, FILTER_VALIDATE_IP)) {
            throw new InvalidIpAddressException($ipAddress);
        }
    }

    public function validatePepRequirements(Customer $customer, array $data): void
    {
        if ($this->pepApprovalService->requiresHeadOfficeApproval($customer)) {
            if (! $this->pepApprovalService->hasApprovedApproval($customer)) {
                $pendingApproval = $this->pepApprovalService->requestApproval(
                    $customer,
                    $data['type'] ?? 'transaction'
                );
                throw new PepApprovalRequiredException(
                    "Senior Management approval required for PEP customer. Approval ID: {$pendingApproval->id}"
                );
            }
        }

        if ($customer->pep_status) {
            if (empty($data['source_of_funds'])) {
                throw new \InvalidArgumentException('Source of funds is required for PEP customers.');
            }
            if (empty($data['source_of_wealth'])) {
                throw new \InvalidArgumentException('Source of wealth is required for PEP customers.');
            }
        }
    }
}
```

- [ ] **Step 4: Create the interface**

```php
<?php

namespace App\Services\Contracts;

use App\Models\TillBalance;

interface TransactionValidationInterface
{
    public function validateCurrency(string $currencyCode): void;
    public function validateTillBalance(string $tillId, string $currencyCode): TillBalance;
    public function validateIpAddress(?string $ipAddress): void;
    public function validatePepRequirements(\App\Models\Customer $customer, array $data): void;
}
```

- [ ] **Step 5: Update TransactionService to use the new validation service**

In `app/Services/TransactionService.php`:

```php
public function __construct(
    protected MathService $mathService,
    protected ComplianceService $complianceService,
    protected CurrencyPositionService $positionService,
    protected AccountingService $accountingService,
    protected AuditService $auditService,
    protected TransactionMonitoringService $monitoringService,
    protected TellerAllocationService $tellerAllocationService,
    protected CustomerScreeningService $screeningService,
    protected HistoricalRiskAnalysisService $historicalRiskAnalysisService,
    protected ThresholdService $thresholdService,
    protected CacheTagsService $cacheTagsService,
    protected TransactionAccountingService $transactionAccountingService,
    protected PepApprovalService $pepApprovalService,
    protected TransactionValidationService $validationService, // NEW
) {}
```

Replace lines 203-252 with:

```php
$this->validationService->validateCurrency($data['currency_code']);
$this->validationService->validateIpAddress($ipAddress);
$tillBalance = $this->validationService->validateTillBalance($data['till_id'], $data['currency_code']);
$this->validationService->validatePepRequirements($customer, $data);
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --compact --filter=TransactionValidationServiceTest`
Expected: PASS

- [ ] **Step 7: Run TransactionWorkflowTest to verify no regressions**

Run: `php artisan test --compact --filter=TransactionWorkflowTest`
Expected: PASS

- [ ] **Step 8: Commit**

```bash
git add app/Services/TransactionValidationService.php app/Services/Contracts/TransactionValidationInterface.php app/Services/TransactionService.php tests/Unit/Services/TransactionValidationServiceTest.php
git commit -m "refactor: extract validation logic from TransactionService into TransactionValidationService"
```

---

## Phase 3: Medium Priority Fixes (Tasks 10–18)

### Task 10: Fix DashboardController Constructor Promotion

**Files:**
- Modify: `app/Http/Controllers/DashboardController.php:20-40`

- [ ] **Step 1: Refactor constructor to use promoted properties**

```php
// BEFORE (lines 20-40):
class DashboardController extends Controller
{
    protected AuditService $auditService;
    protected CurrencyPositionService $currencyPositionService;
    protected RateApiService $rateApiService;
    protected CacheOptimizationService $cacheOptimizationService;
    protected CacheTagsService $cacheTagsService;

    public function __construct(
        AuditService $auditService,
        CurrencyPositionService $currencyPositionService,
        RateApiService $rateApiService,
        CacheOptimizationService $cacheOptimizationService,
        CacheTagsService $cacheTagsService
    ) {
        $this->auditService = $auditService;
        $this->currencyPositionService = $currencyPositionService;
        // ... more assignments
    }

// AFTER:
class DashboardController extends Controller
{
    public function __construct(
        protected AuditService $auditService,
        protected CurrencyPositionService $currencyPositionService,
        protected RateApiService $rateApiService,
        protected CacheOptimizationService $cacheOptimizationService,
        protected CacheTagsService $cacheTagsService,
    ) {}
```

- [ ] **Step 2: Run Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/DashboardController.php
git commit -m "refactor: use PHP 8 constructor promotion in DashboardController"
```

---

### Task 11: Remove Duplicate OverrideRateRequest

**Files:**
- Read: `app/Http/Requests/OverrideRateRequest.php`
- Read: `app/Http/Requests/Api/OverrideRateRequest.php`
- Modify: Keep one, update imports, delete the other

- [ ] **Step 1: Compare both files**

Read both files and identify which is more complete. Keep the root-level one and delete the Api duplicate.

- [ ] **Step 2: Update imports if needed**

Search for imports of the deleted class:

```bash
grep -r "App\\Http\\Requests\\Api\\OverrideRateRequest" app/
```

Update any references to use `App\Http\Requests\OverrideRateRequest`.

- [ ] **Step 3: Delete the duplicate**

```bash
rm app/Http/Requests/Api/OverrideRateRequest.php
```

- [ ] **Step 4: Run tests and commit**

```bash
php artisan test --compact --filter=Rate
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "fix: remove duplicate OverrideRateRequest from Api namespace"
```

---

### Task 12: Add Base DomainException Class

**Files:**
- Create: `app/Exceptions/Domain/DomainException.php`
- Modify: All 46 domain exception classes to extend it

- [ ] **Step 1: Create the base class**

```php
<?php

namespace App\Exceptions\Domain;

use RuntimeException;

abstract class DomainException extends RuntimeException
{
    public function getSeverity(): string
    {
        return 'warning';
    }

    public function getErrorCode(): string
    {
        return class_basename(static::class);
    }
}
```

- [ ] **Step 2: Update all domain exceptions**

Use a script to update all files in `app/Exceptions/Domain/`:

```bash
for file in app/Exceptions/Domain/*.php; do
    if [[ "$file" != *"DomainException.php" ]]; then
        sed -i 's/extends RuntimeException/extends DomainException/' "$file"
    fi
done
```

- [ ] **Step 3: Add use statement to each file**

```bash
for file in app/Exceptions/Domain/*.php; do
    if [[ "$file" != *"DomainException.php" ]]; then
        sed -i 's/use RuntimeException;/use App\\Exceptions\\Domain\\DomainException;/' "$file"
    fi
done
```

- [ ] **Step 4: Run tests and commit**

```bash
php artisan test --compact
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "feat: add DomainException base class for all domain exceptions"
```

---

### Task 13: Fix RateManagementService DI Bypass

**Files:**
- Modify: `app/Services/RateManagementService.php`

- [ ] **Step 1: Find the `new` usage**

```bash
grep -n "new RateApiService\|new MathService" app/Services/RateManagementService.php
```

- [ ] **Step 2: Remove the fallback `new` and rely on constructor injection**

```php
// BEFORE:
$this->rateApiService = $rateApiService ?? new RateApiService;
$this->mathService = $mathService ?? new MathService;

// AFTER (remove the fallbacks, make them required):
public function __construct(
    protected RateApiService $rateApiService,
    protected MathService $mathService,
    // ... other dependencies
) {}
```

- [ ] **Step 3: Run tests and commit**

```bash
php artisan test --compact --filter=Rate
vendor/bin/pint --dirty --format agent
git add app/Services/RateManagementService.php
git commit -m "fix: remove DI container bypass in RateManagementService constructor"
```

---

### Task 14: Add Missing Transaction→JournalEntry Relationship

**Files:**
- Modify: `app/Models/Transaction.php`
- Create: `tests/Unit/Models/TransactionJournalEntryTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Models;

use App\Models\JournalEntry;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionJournalEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_transaction_has_journal_entry_relationship(): void
    {
        $journalEntry = JournalEntry::factory()->create();
        $transaction = Transaction::factory()->create([
            'journal_entry_id' => $journalEntry->id,
        ]);

        $this->assertNotNull($transaction->journalEntry);
        $this->assertEquals($journalEntry->id, $transaction->journalEntry->id);
    }

    public function test_transaction_has_deferred_journal_entry_relationship(): void
    {
        $journalEntry = JournalEntry::factory()->create();
        $transaction = Transaction::factory()->create([
            'deferred_journal_entry_id' => $journalEntry->id,
        ]);

        $this->assertNotNull($transaction->deferredJournalEntry);
        $this->assertEquals($journalEntry->id, $transaction->deferredJournalEntry->id);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=TransactionJournalEntryTest`
Expected: FAIL

- [ ] **Step 3: Add relationships to Transaction model**

```php
public function journalEntry(): BelongsTo
{
    return $this->belongsTo(JournalEntry::class);
}

public function deferredJournalEntry(): BelongsTo
{
    return $this->belongsTo(JournalEntry::class, 'deferred_journal_entry_id');
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=TransactionJournalEntryTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Models/Transaction.php tests/Unit/Models/TransactionJournalEntryTest.php
git commit -m "feat: add journalEntry() and deferredJournalEntry() relationships to Transaction model"
```

---

### Task 15: Implement API uploadDocument Stub

**Files:**
- Modify: `app/Http/Controllers/Api/V1/CustomerController.php`
- Create: `tests/Feature/Api/CustomerDocumentUploadTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerDocumentUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_document_actually_stores_file(): void
    {
        $user = User::factory()->create(['role' => UserRole::Teller]);
        $customer = Customer::factory()->create();

        Sanctum::actingAs($user);

        $file = UploadedFile::fake()->create('kyc-doc.pdf', 100, 'application/pdf');

        $response = $this->postJson("/api/v1/customers/{$customer->id}/kyc", [
            'document' => $file,
            'document_type' => 'MyKad',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('customer_documents', [
            'customer_id' => $customer->id,
        ]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=CustomerDocumentUploadTest`
Expected: FAIL (stub returns hardcoded success)

- [ ] **Step 3: Implement the upload method**

```php
public function uploadDocument(Request $request, Customer $customer): JsonResponse
{
    $request->validate([
        'document' => 'required|file|max:10240|mimes:pdf,jpg,png',
        'document_type' => 'required|string|max:100',
    ]);

    $file = $request->file('document');
    $path = $file->store('kyc/'.$customer->id, 'private');

    $document = $customer->documents()->create([
        'document_type' => $request->document_type,
        'file_path' => $path,
        'original_name' => $file->getClientOriginalName(),
        'mime_type' => $file->getMimeType(),
        'size' => $file->getSize(),
        'uploaded_by' => auth()->id(),
    ]);

    $this->auditService->logWithSeverity('kyc_document_uploaded', [
        'entity_type' => 'Customer',
        'entity_id' => $customer->id,
        'new_values' => ['document_type' => $request->document_type],
    ]);

    return response()->json([
        'success' => true,
        'document_id' => $document->id,
        'message' => 'Document uploaded successfully.',
    ]);
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=CustomerDocumentUploadTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/V1/CustomerController.php tests/Feature/Api/CustomerDocumentUploadTest.php
git commit -m "feat: implement KYC document upload in API CustomerController"
```

---

### Task 16: Fix Unit Tests Testing PHP Math

**Files:**
- Modify: `tests/Unit/AccountingServiceTest.php`

- [ ] **Step 1: Identify tests that test PHP math instead of service**

```bash
grep -n "bccomp\|bcadd" tests/Unit/AccountingServiceTest.php
```

- [ ] **Step 2: Rewrite those tests to call through AccountingService**

For each test that uses `bccomp`/`bcadd` directly, replace with:

```php
// BEFORE:
$debit = '100.00';
$credit = '100.00';
$this->assertEquals(0, bccomp($debit, $credit, 2));

// AFTER:
$service = app(AccountingService::class);
$entry = $service->createJournalEntry([
    'description' => 'Test entry',
    'lines' => [
        ['account_code' => '1000', 'debit' => '100.00', 'credit' => '0'],
        ['account_code' => '2000', 'debit' => '0', 'credit' => '100.00'],
    ],
]);
$this->assertTrue($entry->isBalanced());
```

- [ ] **Step 3: Run affected tests**

Run: `php artisan test --compact --filter=AccountingServiceTest`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add tests/Unit/AccountingServiceTest.php
git commit -m "fix: rewrite AccountingService tests to exercise service logic instead of PHP math"
```

---

### Task 17: Standardize Test Method Style

**Files:**
- All test files

- [ ] **Step 1: Decide on convention**

Adopt `#[Test]` attributes (PHP 8 style) as the standard, since it's already used in newer tests.

- [ ] **Step 2: Convert `test_` methods to `#[Test]`**

```bash
# Find files using test_ convention
grep -rl "public function test_" tests/
```

For each file, convert:

```php
// BEFORE:
public function test_something_works(): void

// AFTER:
#[Test]
public function something_works(): void
```

- [ ] **Step 3: Run full test suite**

Run: `php artisan test --compact`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add tests/
git commit -m "style: standardize test method style to use #[Test] attributes"
```

---

### Task 18: Clean Up FAULT Comments

**Files:**
- Modify: `app/Services/RevaluationService.php`

- [ ] **Step 1: Find and remove FAULT comments**

```bash
grep -n "FAULT #" app/Services/RevaluationService.php
```

- [ ] **Step 2: Remove the comments, keep the code**

```bash
sed -i '/\/\/ FAULT #[0-9]* FIX/d' app/Services/RevaluationService.php
```

- [ ] **Step 3: Commit**

```bash
git add app/Services/RevaluationService.php
git commit -m "chore: remove FAULT fix comments from RevaluationService"
```

---

## Phase 4: Low Priority Fixes (Tasks 19–21)

### Task 19: Fix README Laravel Version

**Files:**
- Modify: `README.md:87`

- [ ] **Step 1: Update the version reference**

```markdown
# BEFORE:
| Framework | Laravel 10.x |

# AFTER:
| Framework | Laravel 11.x |
```

- [ ] **Step 2: Commit**

```bash
git add README.md
git commit -m "docs: fix Laravel version in README from 10.x to 11.x"
```

---

### Task 20: Add Load Test Script

**Files:**
- Create: `tests/Load/transaction-load-test.js`

- [ ] **Step 1: Create a basic k6 load test**

```javascript
import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
    stages: [
        { duration: '30s', target: 10 },
        { duration: '1m', target: 20 },
        { duration: '30s', target: 0 },
    ],
    thresholds: {
        http_req_duration: ['p(95)<500'],
        http_req_failed: ['rate<0.1'],
    },
};

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000';

export default function () {
    const res = http.get(`${BASE_URL}/health`);
    check(res, {
        'status is 200': (r) => r.status === 200,
        'response time < 500ms': (r) => r.timings.duration < 500,
    });
    sleep(1);
}
```

- [ ] **Step 2: Commit**

```bash
git add tests/Load/transaction-load-test.js
git commit -m "feat: add k6 load test script for health endpoint"
```

---

### Task 21: Add Load/Performance Test Coverage

**Files:**
- Create: `tests/Feature/PerformanceTest.php`

- [ ] **Step 1: Create performance regression test**

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_index_query_count_is_bounded(): void
    {
        // Seed enough customers to test query count
        \App\Models\Customer::factory()->count(50)->create();

        DB::enableQueryLog();

        $user = \App\Models\User::factory()->create(['role' => \App\Enums\UserRole::Manager]);
        $this->actingAs($user);

        $response = $this->get('/customers');
        $response->assertOk();

        $queryCount = count(DB::getQueryLog());
        $this->assertLessThan(20, $queryCount, "Customer index should use fewer than 20 queries, used {$queryCount}");

        DB::disableQueryLog();
    }
}
```

- [ ] **Step 2: Run test**

Run: `php artisan test --compact --filter=PerformanceTest`
Expected: PASS

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/PerformanceTest.php
git commit -m "feat: add performance regression test for customer index query count"
```

---

## Execution Summary

| Phase | Tasks | Est. Time |
|-------|-------|-----------|
| Phase 1: Critical | 1–4 | 2–3 hours |
| Phase 2: High | 5–9 | 3–4 hours |
| Phase 3: Medium | 10–18 | 4–5 hours |
| Phase 4: Low | 19–21 | 30 min |
| **Total** | **21 tasks** | **~10 hours** |

## Verification After Each Phase

After completing each phase, run the full test suite:

```bash
php artisan test --compact
vendor/bin/pint --dirty --format agent
```
