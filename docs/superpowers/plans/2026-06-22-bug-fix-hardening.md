# Bug-Fix Hardening Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close the authorization gaps, enum/DB mismatches, and defensive weaknesses identified in the 2026-06-22 codebase audit.

**Architecture:** Authorization fixes are applied at route/controller level using existing `role:*` middleware and explicit branch/role checks. Enum fixes normalize application enums to match database constraints, adding migrations where the enum is ahead of the schema. Defensive hardening removes date-boundary fragility and null-dereference risks in workflow services and console commands.

**Tech Stack:** Laravel 10/11, PHP 8.3, PHPUnit, SQLite (tests), Tailwind, Alpine.js

---

## Phase 1: Authorization Fixes

### Task 1.1: Restrict branch-closing API routes to managers/admins

**Files:**
- Modify: `routes/api_v1.php:385-395`
- Test: `tests/Feature/Api/BranchClosingAuthorizationTest.php` (create)

**Context:** The branch-closing API group currently only validates branch membership via `BranchScoped::authorizeBranchAccess`, which allows any authenticated teller in the branch to initiate, settle, and finalize a closure. The equivalent web routes already require `role:admin`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Api/BranchClosingAuthorizationTest.php`:

```php
<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BranchClosingAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function teller_cannot_initiate_branch_closing(): void
    {
        $branch = Branch::factory()->create();
        $teller = User::factory()->create([
            'role' => UserRole::Teller,
            'branch_id' => $branch->id,
        ]);

        $response = $this->actingAs($teller, 'sanctum')
            ->postJson("/api/v1/branches/{$branch->id}/closing/initiate");

        $response->assertForbidden();
    }

    #[Test]
    public function manager_can_initiate_branch_closing(): void
    {
        $branch = Branch::factory()->create();
        $manager = User::factory()->create([
            'role' => UserRole::Manager,
            'branch_id' => $branch->id,
        ]);

        $response = $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/branches/{$branch->id}/closing/initiate");

        $response->assertSuccessful();
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact tests/Feature/Api/BranchClosingAuthorizationTest.php`

Expected: FAIL (`teller_cannot_initiate_branch_closing` returns 2xx/4xx other than 403).

- [ ] **Step 3: Add role middleware to the route group**

In `routes/api_v1.php`, change:

```php
    // Branch Closing Workflow API
    Route::prefix('branches/{branchId}/closing')->group(function () {
```

to:

```php
    // Branch Closing Workflow API
    Route::prefix('branches/{branchId}/closing')->middleware('role:manager,admin')->group(function () {
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --compact tests/Feature/Api/BranchClosingAuthorizationTest.php`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add routes/api_v1.php tests/Feature/Api/BranchClosingAuthorizationTest.php
git commit -m "security: require manager/admin role for branch-closing API routes"
```

---

### Task 1.2: Restrict allocation `show` route to managers/admins

**Files:**
- Modify: `routes/api_v1.php:348-349`
- Test: `tests/Feature/Api/TellerAllocationAuthorizationTest.php` (create)

**Context:** `GET /api/v1/allocations/{allocationId}` returns any allocation by ID without role checks, exposing float amounts and limits to any authenticated user.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Api/TellerAllocationAuthorizationTest.php`:

```php
<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\TellerAllocation;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TellerAllocationAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function teller_cannot_view_other_allocation_details(): void
    {
        $branch = Branch::factory()->create();
        $teller = User::factory()->create([
            'role' => UserRole::Teller,
            'branch_id' => $branch->id,
        ]);
        $allocation = TellerAllocation::factory()->create([
            'branch_id' => $branch->id,
        ]);

        $response = $this->actingAs($teller, 'sanctum')
            ->getJson("/api/v1/allocations/{$allocation->id}");

        $response->assertForbidden();
    }

    #[Test]
    public function manager_can_view_allocation_details(): void
    {
        $branch = Branch::factory()->create();
        $manager = User::factory()->create([
            'role' => UserRole::Manager,
            'branch_id' => $branch->id,
        ]);
        $allocation = TellerAllocation::factory()->create([
            'branch_id' => $branch->id,
        ]);

        $response = $this->actingAs($manager, 'sanctum')
            ->getJson("/api/v1/allocations/{$allocation->id}");

        $response->assertOk();
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact tests/Feature/Api/TellerAllocationAuthorizationTest.php`

Expected: FAIL (`teller_cannot_view_other_allocation_details` does not return 403).

- [ ] **Step 3: Add role middleware to the show route**

In `routes/api_v1.php`, change:

```php
        // Get specific allocation details
        Route::get('/{allocationId}', [TellerAllocationController::class, 'show'])
            ->name('api.v1.allocations.show');
```

to:

```php
        // Get specific allocation details
        Route::get('/{allocationId}', [TellerAllocationController::class, 'show'])
            ->middleware('role:manager,admin')
            ->name('api.v1.allocations.show');
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --compact tests/Feature/Api/TellerAllocationAuthorizationTest.php`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add routes/api_v1.php tests/Feature/Api/TellerAllocationAuthorizationTest.php
git commit -m "security: require manager/admin role for allocation details API"
```

---

### Task 1.3: Enforce manager role on allocation `pending`/`active` branch listings

**Files:**
- Modify: `routes/api_v1.php:324-330`
- Test: reuse `tests/Feature/Api/TellerAllocationAuthorizationTest.php`

**Context:** The comments in `TellerAllocationController` say these endpoints are manager-only, but the routes lack role middleware.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/Api/TellerAllocationAuthorizationTest.php`:

```php
    #[Test]
    public function teller_cannot_list_pending_allocations_for_branch(): void
    {
        $branch = Branch::factory()->create();
        $teller = User::factory()->create([
            'role' => UserRole::Teller,
            'branch_id' => $branch->id,
        ]);

        $this->actingAs($teller, 'sanctum')
            ->getJson('/api/v1/allocations/pending')
            ->assertForbidden();
    }

    #[Test]
    public function teller_cannot_list_active_allocations_for_branch(): void
    {
        $branch = Branch::factory()->create();
        $teller = User::factory()->create([
            'role' => UserRole::Teller,
            'branch_id' => $branch->id,
        ]);

        $this->actingAs($teller, 'sanctum')
            ->getJson('/api/v1/allocations/active')
            ->assertForbidden();
    }
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact tests/Feature/Api/TellerAllocationAuthorizationTest.php`

Expected: FAIL.

- [ ] **Step 3: Add role middleware**

In `routes/api_v1.php`, change:

```php
        // Manager: Get pending allocations for their branch
        Route::get('/pending', [TellerAllocationController::class, 'pendingForBranch'])
            ->name('api.v1.allocations.pending');
        // Manager: Get active allocations for their branch
        Route::get('/active', [TellerAllocationController::class, 'activeForBranch'])
            ->name('api.v1.allocations.active');
```

to:

```php
        // Manager: Get pending allocations for their branch
        Route::get('/pending', [TellerAllocationController::class, 'pendingForBranch'])
            ->middleware('role:manager,admin')
            ->name('api.v1.allocations.pending');
        // Manager: Get active allocations for their branch
        Route::get('/active', [TellerAllocationController::class, 'activeForBranch'])
            ->middleware('role:manager,admin')
            ->name('api.v1.allocations.active');
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --compact tests/Feature/Api/TellerAllocationAuthorizationTest.php`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add routes/api_v1.php tests/Feature/Api/TellerAllocationAuthorizationTest.php
git commit -m "security: require manager/admin role for allocation branch listings"
```

---

### Task 1.4: Enforce same-branch membership on counter handover

**Files:**
- Modify: `app/Http/Controllers/CounterController.php:244-268`
- Test: `tests/Feature/CounterHandoverBranchScopeTest.php` (create)

**Context:** `CounterController::handover()` validates that users exist but does not verify they belong to the counter's branch, allowing cross-branch handovers if IDs are known.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/CounterHandoverBranchScopeTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\CounterSession;
use App\Models\Currency;
use App\Models\TellerAllocation;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CounterHandoverBranchScopeTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function handover_rejects_users_from_different_branch(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();
        $currency = Currency::factory()->create();

        $counter = Counter::factory()->create(['branch_id' => $branchA->id]);
        $fromUser = User::factory()->create([
            'role' => UserRole::Teller,
            'branch_id' => $branchA->id,
        ]);
        $toUser = User::factory()->create([
            'role' => UserRole::Teller,
            'branch_id' => $branchB->id,
        ]);
        $supervisor = User::factory()->create([
            'role' => UserRole::Manager,
            'branch_id' => $branchA->id,
        ]);

        CounterSession::factory()->create([
            'counter_id' => $counter->id,
            'user_id' => $fromUser->id,
            'session_date' => now()->toDateString(),
            'status' => 'open',
        ]);
        TellerAllocation::factory()->create([
            'user_id' => $fromUser->id,
            'branch_id' => $branchA->id,
            'counter_id' => $counter->id,
            'currency_code' => $currency->code,
            'status' => 'active',
        ]);

        $this->actingAs($supervisor)
            ->post(route('counters.handover', $counter), [
                'from_user_id' => $fromUser->id,
                'to_user_id' => $toUser->id,
                'supervisor_id' => $supervisor->id,
                'physical_counts' => [$currency->code => '100'],
            ])
            ->assertSessionHas('error');
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact tests/Feature/CounterHandoverBranchScopeTest.php`

Expected: FAIL (handover succeeds or flashes a different error).

- [ ] **Step 3: Add branch-scope checks in the controller**

In `app/Http/Controllers/CounterController.php`, inside `handover()`, after the user lookups and before calling `initiateHandover`, add:

```php
        $expectedBranchId = $counter->branch_id;

        if ($fromUser->branch_id !== $expectedBranchId || $toUser->branch_id !== $expectedBranchId || $supervisor->branch_id !== $expectedBranchId) {
            return back()->with('error', 'All users must belong to the counter branch.');
        }
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --compact tests/Feature/CounterHandoverBranchScopeTest.php`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/CounterController.php tests/Feature/CounterHandoverBranchScopeTest.php
git commit -m "security: enforce same-branch membership on counter handover"
```

---

## Phase 2: Enum / Database Alignment

### Task 2.1: Align `AccountingPeriodStatus` enum with database values

**Files:**
- Modify: `app/Enums/AccountingPeriodStatus.php`
- Test: `tests/Unit/Enums/AccountingPeriodStatusTest.php` (create)

**Context:** The enum uses lowercase values (`open`, `closed`), but the migration `2026_04_10_000003_create_accounting_tables.php:108` defines `Open`, `Closed`, `Locked`. This will fail on MySQL and the model cannot represent a `Locked` period.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Enums/AccountingPeriodStatusTest.php`:

```php
<?php

namespace Tests\Unit\Enums;

use App\Enums\AccountingPeriodStatus;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccountingPeriodStatusTest extends TestCase
{
    #[Test]
    public function values_match_database_enum(): void
    {
        $this->assertSame('Open', AccountingPeriodStatus::Open->value);
        $this->assertSame('Closed', AccountingPeriodStatus::Closed->value);
        $this->assertSame('Locked', AccountingPeriodStatus::Locked->value);
    }

    #[Test]
    public function locked_status_is_supported(): void
    {
        $this->assertTrue(AccountingPeriodStatus::Locked->isLocked());
        $this->assertSame('dark', AccountingPeriodStatus::Locked->color());
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact tests/Unit/Enums/AccountingPeriodStatusTest.php`

Expected: FAIL (`Locked` missing, values are lowercase).

- [ ] **Step 3: Update the enum to match the database**

Replace `app/Enums/AccountingPeriodStatus.php` with:

```php
<?php

namespace App\Enums;

enum AccountingPeriodStatus: string
{
    case Open = 'Open';
    case Closed = 'Closed';
    case Locked = 'Locked';

    public function isOpen(): bool
    {
        return $this === self::Open;
    }

    public function isClosed(): bool
    {
        return $this === self::Closed;
    }

    public function isLocked(): bool
    {
        return $this === self::Locked;
    }

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Closed => 'Closed',
            self::Locked => 'Locked',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'success',
            self::Closed => 'secondary',
            self::Locked => 'dark',
        };
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --compact tests/Unit/Enums/AccountingPeriodStatusTest.php`

Expected: PASS.

- [ ] **Step 5: Run the accounting-period tests to confirm no regressions**

Run: `php artisan test --compact --filter=AccountingPeriod`

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Enums/AccountingPeriodStatus.php tests/Unit/Enums/AccountingPeriodStatusTest.php
git commit -m "fix: align AccountingPeriodStatus enum with database values"
```

---

### Task 2.2: Add `maintenance` to counters status enum in the database

**Files:**
- Create: `database/migrations/2026_06_22_000001_add_maintenance_to_counters_status.php`
- Test: `tests/Unit/Enums/CounterStatusTest.php` (create)

**Context:** `CounterStatus` enum defines `active`, `inactive`, `maintenance`, but `counters.status` only allows `active`, `inactive`.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Enums/CounterStatusTest.php`:

```php
<?php

namespace Tests\Unit\Enums;

use App\Enums\CounterStatus;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CounterStatusTest extends TestCase
{
    #[Test]
    public function maintenance_value_is_allowed_by_database(): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver !== 'mysql') {
            $this->markTestSkipped('Enum constraint only enforced on MySQL.');
        }

        $this->assertSame('maintenance', CounterStatus::Maintenance->value);
    }
}
```

- [ ] **Step 2: Run the test to verify it is skipped or passes**

Run: `php artisan test --compact tests/Unit/Enums/CounterStatusTest.php`

Expected: SKIP on SQLite (no failure possible on SQLite).

- [ ] **Step 3: Create the migration**

Create `database/migrations/2026_06_22_000001_add_maintenance_to_counters_status.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE counters MODIFY COLUMN status ENUM('active', 'inactive', 'maintenance') DEFAULT 'active'");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE counters MODIFY COLUMN status ENUM('active', 'inactive') DEFAULT 'active'");
    }
};
```

- [ ] **Step 4: Run migrations**

Run: `php artisan migrate`

Expected: success.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_06_22_000001_add_maintenance_to_counters_status.php tests/Unit/Enums/CounterStatusTest.php
git commit -m "fix: add maintenance value to counters status enum"
```

---

### Task 2.3: Add `Escalated` to `flagged_transactions` status enum

**Files:**
- Create: `database/migrations/2026_06_22_000002_add_escalated_to_flagged_transactions_status.php`
- Test: `tests/Unit/Enums/FlagStatusTest.php` (create)

**Context:** `FlagStatus::Escalated` is used in services but is absent from the `flagged_transactions.status` DB enum.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Enums/FlagStatusTest.php`:

```php
<?php

namespace Tests\Unit\Enums;

use App\Enums\FlagStatus;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FlagStatusTest extends TestCase
{
    #[Test]
    public function escalated_value_is_allowed_by_database(): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver !== 'mysql') {
            $this->markTestSkipped('Enum constraint only enforced on MySQL.');
        }

        $this->assertSame('Escalated', FlagStatus::Escalated->value);
    }
}
```

- [ ] **Step 2: Run the test**

Run: `php artisan test --compact tests/Unit/Enums/FlagStatusTest.php`

Expected: SKIP on SQLite.

- [ ] **Step 3: Create the migration**

Create `database/migrations/2026_06_22_000002_add_escalated_to_flagged_transactions_status.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE flagged_transactions MODIFY COLUMN status ENUM('Open', 'Under_Review', 'Resolved', 'Rejected', 'Escalated') DEFAULT 'Open'");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE flagged_transactions MODIFY COLUMN status ENUM('Open', 'Under_Review', 'Resolved', 'Rejected') DEFAULT 'Open'");
    }
};
```

- [ ] **Step 4: Run migrations**

Run: `php artisan migrate`

Expected: success.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_06_22_000002_add_escalated_to_flagged_transactions_status.php tests/Unit/Enums/FlagStatusTest.php
git commit -m "fix: add Escalated to flagged_transactions status enum"
```

---

### Task 2.4: Align `HighRiskCountryRiskLevel` enum and re-enable model cast

**Files:**
- Modify: `app/Enums/HighRiskCountryRiskLevel.php`
- Modify: `app/Models/HighRiskCountry.php:26-29`
- Test: `tests/Unit/Enums/HighRiskCountryRiskLevelTest.php` (create)

**Context:** The DB enum for `high_risk_countries.risk_level` is `High`, `Grey`. The PHP enum has `low`, `medium`, `high`, `critical`, and the model cast is disabled.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Enums/HighRiskCountryRiskLevelTest.php`:

```php
<?php

namespace Tests\Unit\Enums;

use App\Enums\HighRiskCountryRiskLevel;
use App\Models\HighRiskCountry;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HighRiskCountryRiskLevelTest extends TestCase
{
    #[Test]
    public function values_match_database_enum(): void
    {
        $this->assertSame('High', HighRiskCountryRiskLevel::High->value);
        $this->assertSame('Grey', HighRiskCountryRiskLevel::Grey->value);
    }

    #[Test]
    public function model_casts_risk_level_to_enum(): void
    {
        $country = HighRiskCountry::factory()->create([
            'country_code' => 'XX',
            'risk_level' => 'High',
        ]);

        $this->assertInstanceOf(HighRiskCountryRiskLevel::class, $country->risk_level);
        $this->assertTrue($country->risk_level->isHigh());
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact tests/Unit/Enums/HighRiskCountryRiskLevelTest.php`

Expected: FAIL (`HighRiskCountry::factory` may not exist or cast returns string).

- [ ] **Step 3: Update the enum**

Replace `app/Enums/HighRiskCountryRiskLevel.php` with:

```php
<?php

namespace App\Enums;

enum HighRiskCountryRiskLevel: string
{
    case High = 'High';
    case Grey = 'Grey';

    public function isHigh(): bool
    {
        return $this === self::High;
    }

    public function isGrey(): bool
    {
        return $this === self::Grey;
    }

    public function label(): string
    {
        return match ($this) {
            self::High => 'High Risk',
            self::Grey => 'Grey List',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::High => 'danger',
            self::Grey => 'warning',
        };
    }
}
```

- [ ] **Step 4: Re-enable the cast**

In `app/Models/HighRiskCountry.php`, change:

```php
    protected $casts = [
        'list_date' => 'date',
        // 'risk_level' => HighRiskCountryRiskLevel::class, // temporarily disabled for factory test
    ];
```

to:

```php
    protected $casts = [
        'list_date' => 'date',
        'risk_level' => HighRiskCountryRiskLevel::class,
    ];
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test --compact tests/Unit/Enums/HighRiskCountryRiskLevelTest.php`

Expected: PASS. If no factory exists, create `database/factories/HighRiskCountryFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\HighRiskCountry;
use Illuminate\Database\Eloquent\Factories\Factory;

class HighRiskCountryFactory extends Factory
{
    protected $model = HighRiskCountry::class;

    public function definition(): array
    {
        return [
            'country_code' => $this->faker->unique()->countryCode(),
            'country_name' => $this->faker->country(),
            'risk_level' => 'High',
            'source' => 'Test',
            'list_date' => now(),
        ];
    }
}
```

- [ ] **Step 6: Commit**

```bash
git add app/Enums/HighRiskCountryRiskLevel.php app/Models/HighRiskCountry.php tests/Unit/Enums/HighRiskCountryRiskLevelTest.php database/factories/HighRiskCountryFactory.php
git commit -m "fix: align HighRiskCountryRiskLevel enum with database and re-enable cast"
```

---

### Task 2.5: Split `ReportStatus` and remove the mixed-domain enum

**Files:**
- Create: `app/Enums/ReportRunStatus.php`
- Modify: `app/Models/ReportRun.php:5-6,29-36,49-56,81-103`
- Modify: `app/Services/Reporting/ReportSchedulingService.php:5,37,111,306`
- Modify: `app/Services/Compliance/ComplianceReportingService.php:5,371,375`
- Delete: `app/Enums/ReportStatus.php`
- Test: `tests/Unit/Enums/ReportRunStatusTest.php` (create)

**Context:** `ReportStatus` mixes two status domains. `ReportRun` should use a dedicated `ReportRunStatus` enum. `ReportGenerated` already uses `ReportGeneratedStatus`.

- [ ] **Step 1: Create the new enum**

Create `app/Enums/ReportRunStatus.php`:

```php
<?php

namespace App\Enums;

enum ReportRunStatus: string
{
    case Scheduled = 'scheduled';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';

    public function isScheduled(): bool
    {
        return $this === self::Scheduled;
    }

    public function isRunning(): bool
    {
        return $this === self::Running;
    }

    public function isCompleted(): bool
    {
        return $this === self::Completed;
    }

    public function isFailed(): bool
    {
        return $this === self::Failed;
    }

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Scheduled',
            self::Running => 'Running',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Scheduled => 'blue',
            self::Running => 'yellow',
            self::Completed => 'green',
            self::Failed => 'red',
        };
    }
}
```

- [ ] **Step 2: Update `ReportRun` model**

In `app/Models/ReportRun.php`:

```php
use App\Enums\ReportRunStatus;
```

Replace the `status` cast:

```php
        'status' => ReportRunStatus::class,
```

Update scopes and helpers:

```php
    public function scopeSuccessful($query)
    {
        return $query->where('status', ReportRunStatus::Completed);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', ReportRunStatus::Failed);
    }

    public function markAsRunning(): void
    {
        $this->status = ReportRunStatus::Running;
        $this->started_at = now();
        $this->save();
    }

    public function markAsCompleted(string $filePath, int $rowCount): void
    {
        $this->status = ReportRunStatus::Completed;
        $this->completed_at = now();
        $this->file_path = $filePath;
        $this->row_count = $rowCount;
        $this->save();
    }

    public function markAsFailed(string $errorMessage): void
    {
        $this->status = ReportRunStatus::Failed;
        $this->completed_at = now();
        $this->error_message = $errorMessage;
        $this->save();
    }
```

- [ ] **Step 3: Update `ReportSchedulingService`**

In `app/Services/Reporting/ReportSchedulingService.php`:

```php
use App\Enums\ReportRunStatus;
```

Change:

```php
            'status' => ReportStatus::Running,
```

to:

```php
            'status' => ReportRunStatus::Running,
```

Change:

```php
        $scheduledRuns = ReportRun::where('status', ReportStatus::Scheduled)->count();
```

to:

```php
        $scheduledRuns = ReportRun::where('status', ReportRunStatus::Scheduled)->count();
```

Find the other `ReportStatus` usage around line 306 and replace with `ReportRunStatus`.

- [ ] **Step 4: Update `ComplianceReportingService`**

In `app/Services/Compliance/ComplianceReportingService.php`:

Remove `use App\Enums\ReportStatus;` and replace the two references in `getAutoGeneratedReports` with `ReportGeneratedStatus`:

```php
use App\Enums\ReportGeneratedStatus;
```

```php
        $pendingCount = $reports->where('status', ReportGeneratedStatus::Pending)->count();
```

```php
            'pending_reports' => $reports->where('status', ReportGeneratedStatus::Pending->value)->values()->toArray(),
```

- [ ] **Step 5: Delete the old enum**

Run: `rm app/Enums/ReportStatus.php`

- [ ] **Step 6: Write the test**

Create `tests/Unit/Enums/ReportRunStatusTest.php`:

```php
<?php

namespace Tests\Unit\Enums;

use App\Enums\ReportRunStatus;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReportRunStatusTest extends TestCase
{
    #[Test]
    public function values_match_report_runs_column(): void
    {
        $this->assertSame('scheduled', ReportRunStatus::Scheduled->value);
        $this->assertSame('running', ReportRunStatus::Running->value);
        $this->assertSame('completed', ReportRunStatus::Completed->value);
        $this->assertSame('failed', ReportRunStatus::Failed->value);
    }

    #[Test]
    public function label_and_color_cover_all_cases(): void
    {
        foreach (ReportRunStatus::cases() as $status) {
            $this->assertNotEmpty($status->label());
            $this->assertNotEmpty($status->color());
        }
    }
}
```

- [ ] **Step 7: Run tests**

Run: `php artisan test --compact tests/Unit/Enums/ReportRunStatusTest.php`

Expected: PASS.

Run: `php artisan test --compact --filter=Report`

Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Enums/ReportRunStatus.php app/Models/ReportRun.php app/Services/Reporting/ReportSchedulingService.php app/Services/Compliance/ComplianceReportingService.php tests/Unit/Enums/ReportRunStatusTest.php
git rm app/Enums/ReportStatus.php
git commit -m "fix: split ReportStatus into ReportRunStatus and ReportGeneratedStatus"
```

---

### Task 2.6: Add `Archived` to `reports_generated.status` enum

**Files:**
- Create: `database/migrations/2026_06_22_000003_add_archived_to_reports_generated_status.php`
- Test: `tests/Unit/Enums/ReportGeneratedStatusTest.php` (create)

**Context:** `ReportGeneratedStatus::Archived` exists but the DB enum only allows `Generated`, `Submitted`, `Pending`.

- [ ] **Step 1: Write the test**

Create `tests/Unit/Enums/ReportGeneratedStatusTest.php`:

```php
<?php

namespace Tests\Unit\Enums;

use App\Enums\ReportGeneratedStatus;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReportGeneratedStatusTest extends TestCase
{
    #[Test]
    public function archived_value_is_allowed_by_database(): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver !== 'mysql') {
            $this->markTestSkipped('Enum constraint only enforced on MySQL.');
        }

        $this->assertSame('Archived', ReportGeneratedStatus::Archived->value);
    }
}
```

- [ ] **Step 2: Create the migration**

Create `database/migrations/2026_06_22_000003_add_archived_to_reports_generated_status.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE reports_generated MODIFY COLUMN status ENUM('Generated', 'Submitted', 'Pending', 'Archived') DEFAULT 'Generated'");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE reports_generated MODIFY COLUMN status ENUM('Generated', 'Submitted', 'Pending') DEFAULT 'Generated'");
    }
};
```

- [ ] **Step 3: Run migrations and tests**

Run: `php artisan migrate`

Expected: success.

Run: `php artisan test --compact tests/Unit/Enums/ReportGeneratedStatusTest.php`

Expected: SKIP on SQLite.

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_06_22_000003_add_archived_to_reports_generated_status.php tests/Unit/Enums/ReportGeneratedStatusTest.php
git commit -m "fix: add Archived to reports_generated status enum"
```

---

## Phase 3: Defensive Hardening

### Task 3.1: Harden counter opening against date-boundary mismatches

**Files:**
- Modify: `app/Services/Branch/CounterOpeningWorkflowService.php:55-70`
- Test: `tests/Feature/BranchAllocationWorkflowTest.php` (extend)

**Context:** `approveAndOpen()` requires the pending allocation's `session_date` to equal today's date. Requests made near midnight can fail if approval crosses the date boundary.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/BranchAllocationWorkflowTest.php`:

```php
    #[Test]
    public function approve_and_open_finds_pending_allocation_across_date_boundary(): void
    {
        $requestAmount = '50000.0000';

        $requests = $this->workflowService->initiateOpeningRequest(
            $this->tellerA,
            $this->counter,
            ['USD' => $requestAmount]
        );

        $allocation = $requests[0];
        $allocation->update(['session_date' => now()->subDay()->toDateString()]);

        $session = $this->workflowService->approveAndOpen(
            $this->manager,
            $this->counter,
            $this->tellerA,
            ['USD' => '45000.0000'],
            ['USD' => '200000.0000']
        );

        $this->assertNotNull($session);
    }
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact --filter=approve_and_open_finds_pending_allocation_across_date_boundary`

Expected: FAIL (`No pending allocation found for USD`).

- [ ] **Step 3: Make the lookup date-boundary tolerant**

In `app/Services/Branch/CounterOpeningWorkflowService.php`, replace the allocation lookup block:

```php
                $allocation = TellerAllocation::where('user_id', $teller->id)
                    ->where('currency_code', $currency)
                    ->whereDate('session_date', $today)
                    ->where('status', TellerAllocationStatus::PENDING->value)
                    ->first();

                if (! $allocation) {
                    throw new Exception("No pending allocation found for {$currency}");
                }
```

with:

```php
                $allocation = TellerAllocation::where('user_id', $teller->id)
                    ->where('currency_code', $currency)
                    ->where('status', TellerAllocationStatus::PENDING->value)
                    ->whereDate('session_date', '<=', $today)
                    ->orderByDesc('session_date')
                    ->first();

                if (! $allocation) {
                    throw new Exception("No pending allocation found for {$currency}");
                }
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --compact --filter=approve_and_open_finds_pending_allocation_across_date_boundary`

Expected: PASS.

Run: `php artisan test --compact tests/Feature/BranchAllocationWorkflowTest.php`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Branch/CounterOpeningWorkflowService.php tests/Feature/BranchAllocationWorkflowTest.php
git commit -m "fix: make counter opening tolerant to date boundaries"
```

---

### Task 3.2: Null-safety in `TestTransactionScenarios`

**Files:**
- Modify: `app/Console/Commands/TestTransactionScenarios.php:327,351`
- Test: existing console tests or skip if none

**Context:** The command dereferences `Branch::where(...)->first()->id` without checking existence.

- [ ] **Step 1: Add safe lookups**

In `app/Console/Commands/TestTransactionScenarios.php`, add a helper near the top of the class:

```php
    private function branchIdForCode(string $code): int
    {
        $branch = Branch::where('code', $code)->first();

        if (! $branch) {
            throw new \InvalidArgumentException("Branch with code [{$code}] not found.");
        }

        return $branch->id;
    }
```

Replace the two inline lookups with:

```php
            'branch_id' => $this->branchIdForCode($scenario['branch']),
```

- [ ] **Step 2: Run the command smoke test**

Run: `php artisan test:transaction-scenarios --dry-run 2>&1 | head -20` (or inspect the command signature)

If no dry-run option exists, run: `php artisan list | grep transaction`

Expected: no fatal null-pointer error when branches are configured.

- [ ] **Step 3: Commit**

```bash
git add app/Console/Commands/TestTransactionScenarios.php
git commit -m "fix: add null-safe branch lookup in TestTransactionScenarios"
```

---

### Task 3.3: Add `Rejected` status to `journal_entries` on SQLite

**Files:**
- Modify: `database/migrations/2026_04_14_000004_add_rejected_status_to_journal_entries.php`

**Context:** The migration only alters the enum on MySQL, leaving SQLite schema inconsistent with `JournalEntryStatus`.

- [ ] **Step 1: Add SQLite handling**

Replace the migration content with:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("UPDATE journal_entries SET status = 'Draft' WHERE status IS NULL");

        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE journal_entries MODIFY COLUMN status ENUM('Draft', 'Pending', 'Posted', 'Reversed', 'Rejected') DEFAULT 'Draft'");
        } elseif ($driver === 'sqlite') {
            $this->recreateJournalEntriesWithStatus("'Draft', 'Pending', 'Posted', 'Reversed', 'Rejected'", 'Draft');
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE journal_entries MODIFY COLUMN status ENUM('Draft', 'Pending', 'Posted', 'Reversed') DEFAULT 'Posted'");
        } elseif ($driver === 'sqlite') {
            $this->recreateJournalEntriesWithStatus("'Draft', 'Pending', 'Posted', 'Reversed'", 'Posted');
        }
    }

    private function recreateJournalEntriesWithStatus(string $allowedValues, string $default): void
    {
        DB::statement('PRAGMA foreign_keys=OFF');
        DB::statement('CREATE TABLE __journal_entries_backup AS SELECT * FROM journal_entries');
        DB::statement('DROP TABLE journal_entries');
        DB::statement(<<<SQL
            CREATE TABLE journal_entries (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                entry_number VARCHAR(20),
                branch_id BIGINT UNSIGNED NULL,
                entry_date DATE NOT NULL,
                reference_type VARCHAR(50) NOT NULL,
                reference_id BIGINT UNSIGNED NULL,
                description TEXT NOT NULL,
                status VARCHAR(50) CHECK(status IN ({$allowedValues})) DEFAULT '{$default}',
                created_by BIGINT UNSIGNED NULL,
                approved_by BIGINT UNSIGNED NULL,
                approved_at DATETIME NULL,
                approval_notes TEXT NULL,
                posted_by BIGINT UNSIGNED NOT NULL,
                posted_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
                reversed_by BIGINT UNSIGNED NULL,
                reversed_at DATETIME NULL,
                cost_center_id BIGINT UNSIGNED NULL,
                department_id BIGINT UNSIGNED NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                FOREIGN KEY (branch_id) REFERENCES branches (id) ON DELETE SET NULL,
                FOREIGN KEY (created_by) REFERENCES users (id),
                FOREIGN KEY (approved_by) REFERENCES users (id),
                FOREIGN KEY (posted_by) REFERENCES users (id),
                FOREIGN KEY (reversed_by) REFERENCES users (id)
            )
        SQL);
        DB::statement('INSERT INTO journal_entries SELECT * FROM __journal_entries_backup');
        DB::statement('DROP TABLE __journal_entries_backup');
        DB::statement('PRAGMA foreign_keys=ON');
    }
};
```

> **Note:** If the original `journal_entries` schema in your environment differs from the snippet above, copy the exact column definitions from `database/migrations/2026_04_10_000003_create_accounting_tables.php` instead of the template above.

- [ ] **Step 2: Run migrations on a fresh test database**

Run:

```bash
php artisan migrate:fresh --env=testing
php artisan test --compact --filter=JournalEntry
```

Expected: PASS.

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_04_14_000004_add_rejected_status_to_journal_entries.php
git commit -m "fix: apply Rejected journal entry status on SQLite as well as MySQL"
```

---

### Task 3.4: Remove the open TODO in findings view

**Files:**
- Modify: `resources/views/compliance/findings/show.blade.php:22`

**Context:** A `TODO` comment remains about wiring a category field.

- [ ] **Step 1: Decide the minimal action**

If no category model exists yet, replace the TODO comment with a simple static placeholder or remove the comment.

Example change:

```blade
{{-- Category is not yet modeled; display placeholder until a category field is added. --}}
<span class="text-gray-500">—</span>
```

- [ ] **Step 2: Commit**

```bash
git add resources/views/compliance/findings/show.blade.php
git commit -m "docs: resolve open TODO in findings show view"
```

---

## Phase 4: Verification & Polish

### Task 4.1: Run the full test suite

- [ ] **Step 1: Run all tests**

Run: `php artisan test --compact`

Expected: PASS (same or improved count vs. baseline of 1,072).

- [ ] **Step 2: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

Expected: PASS.

- [ ] **Step 3: Commit any style fixes**

```bash
git add -A
git commit -m "style: apply pint formatting"
```

---

### Task 4.2: Route audit and final review

- [ ] **Step 1: Confirm sensitive routes are protected**

Run: `php artisan route:list --path=api/v1/branches --except-vendor`

Expected: branch-closing routes show `role:manager,admin`.

Run: `php artisan route:list --path=api/v1/allocations --except-vendor`

Expected: `pending`, `active`, and `show` routes show `role:manager,admin`.

- [ ] **Step 2: Update documentation**

If `docs/orphaned-code-report.md` or `docs/unfinished-tasks-implementation-plan.md` reference any of these issues, mark them resolved.

- [ ] **Step 3: Final commit**

```bash
git add -A
git commit -m "docs: mark audit findings as resolved"
```

---

## Spec Coverage Checklist

- [x] Branch-closing API authorization gap → Task 1.1
- [x] Allocation `show` authorization gap → Task 1.2
- [x] Allocation `pending`/`active` authorization gap → Task 1.3
- [x] Counter handover cross-branch risk → Task 1.4
- [x] `AccountingPeriodStatus` enum/DB mismatch → Task 2.1
- [x] `CounterStatus` enum/DB mismatch → Task 2.2
- [x] `FlagStatus` enum/DB mismatch → Task 2.3
- [x] `HighRiskCountryRiskLevel` enum/DB mismatch → Task 2.4
- [x] `ReportStatus` mixed-domain enum → Task 2.5
- [x] `ReportGeneratedStatus::Archived` DB mismatch → Task 2.6
- [x] Counter opening date-boundary fragility → Task 3.1
- [x] `TestTransactionScenarios` null dereference → Task 3.2
- [x] `journal_entries` SQLite enum drift → Task 3.3
- [x] Open TODO in findings view → Task 3.4
