# Branch Stock & Cash Management Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement branch-level stock and cash management where tellers have personal allocations, manager approves/modifies, and EOD returns full balance to branch pool.

**Architecture:** 
- New BranchPool model tracks branch-level available/allocated balances per currency
- New TellerAllocation model tracks teller's personal allocation per currency per day
- Transaction flow validates against teller's current_balance and daily_limit
- CounterSession linked to TellerAllocation
- Full return to BranchPool at EOD

**Tech Stack:** Laravel 10, BCMath for precision, existing CounterSession/TillBalance infrastructure

---

## File Structure

### New Files
```
app/Models/BranchPool.php
app/Models/TellerAllocation.php
app/Services/BranchPoolService.php
app/Services/TellerAllocationService.php
app/Http/Controllers/Api/V1/BranchPoolController.php
app/Http/Controllers/Api/V1/TellerAllocationController.php
database/migrations/2026_04_16_000001_create_branch_pools_table.php
database/migrations/2026_04_16_000002_create_teller_allocations_table.php
database/migrations/2026_04_16_000003_add_fields_to_till_balances_table.php
database/migrations/2026_04_16_000004_add_fields_to_counter_sessions_table.php
database/migrations/2026_04_16_000005_add_assigned_teller_to_counters_table.php
database/factories/BranchPoolFactory.php
database/factories/TellerAllocationFactory.php
tests/Unit/BranchPoolServiceTest.php
tests/Unit/TellerAllocationServiceTest.php
tests/Feature/BranchAllocationWorkflowTest.php
```

### Modified Files
```
app/Models/TillBalance.php - add teller_allocation_id FK
app/Models/CounterSession.php - add allocation fields
app/Models/Counter.php - add assigned_teller_id
app/Services/CounterService.php - integrate with allocation
app/Services/TransactionService.php - validate against allocation
app/Providers/EventServiceProvider.php - add events
app/Http/Controllers/Api/V1/CounterController.php - add allocation params
config/compliance.php - add allocation thresholds
routes/api_v1.php - add new routes
```

---

## Implementation Phases

### Phase 1: Data Model & Migrations
### Phase 2: BranchPoolService
### Phase 3: TellerAllocationService
### Phase 4: Opening Workflow Integration
### Phase 5: Transaction Integration
### Phase 6: EOD & Handover
### Phase 7: Permissions & Reporting

---

## Phase 1: Data Model & Migrations

### Task 1: Create BranchPool Migration

**Files:**
- Create: `database/migrations/2026_04_16_000001_create_branch_pools_table.php`
- Create: `app/Models/BranchPool.php`
- Create: `database/factories/BranchPoolFactory.php`

- [ ] **Step 1: Create migration**

```php
<?php
// database/migrations/2026_04_16_000001_create_branch_pools_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_pools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->onDelete('cascade');
            $table->string('currency_code', 3);
            $table->decimal('available_balance', 20, 4)->default(0);
            $table->decimal('allocated_balance', 20, 4)->default(0);
            $table->decimal('total_balance', 20, 4)->virtualAs('available_balance + allocated_balance');
            $table->timestamps();
            
            $table->unique(['branch_id', 'currency_code']);
            $table->index(['branch_id', 'currency_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_pools');
    }
};
```

- [ ] **Step 2: Create BranchPool model**

```php
<?php
// app/Models/BranchPool.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchPool extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'currency_code',
        'available_balance',
        'allocated_balance',
    ];

    protected $casts = [
        'available_balance' => 'decimal:4',
        'allocated_balance' => 'decimal:4',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function getTotalBalanceAttribute(): string
    {
        return bcadd($this->available_balance, $this->allocated_balance, 4);
    }

    public function hasAvailable(float|string $amount): bool
    {
        return bccomp($this->available_balance, (string) $amount, 4) >= 0;
    }

    public function allocate(float|string $amount): bool
    {
        if (!$this->hasAvailable($amount)) {
            return false;
        }
        
        $this->decrement('available_balance', $amount);
        $this->increment('allocated_balance', $amount);
        return true;
    }

    public function deallocate(float|string $amount): bool
    {
        if (bccomp($this->allocated_balance, (string) $amount, 4) < 0) {
            return false;
        }
        
        $this->decrement('allocated_balance', $amount);
        $this->increment('available_balance', $amount);
        return true;
    }
}
```

- [ ] **Step 3: Create BranchPoolFactory**

```php
<?php
// database/factories/BranchPoolFactory.php
namespace Database\Factories;

use App\Models\Branch;
use App\Models\BranchPool;
use Illuminate\Database\Eloquent\Factories\Factory;

class BranchPoolFactory extends Factory
{
    protected $model = BranchPool::class;

    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'currency_code' => 'MYR',
            'available_balance' => $this->faker->randomFloat(4, 10000, 100000),
            'allocated_balance' => 0,
        ];
    }

    public function myr(): static
    {
        return $this->state(fn (array $attributes) => [
            'currency_code' => 'MYR',
        ]);
    }

    public function usd(): static
    {
        return $this->state(fn (array $attributes) => [
            'currency_code' => 'USD',
        ]);
    }

    public function empty(): static
    {
        return $this->state(fn (array $attributes) => [
            'available_balance' => 0,
        ]);
    }
}
```

- [ ] **Step 4: Create BranchPoolService**

```php
<?php
// app/Services/BranchPoolService.php
namespace App\Services;

use App\Models\Branch;
use App\Models\BranchPool;
use Illuminate\Support\Collection;

class BranchPoolService
{
    public function getOrCreateForBranch(Branch $branch, string $currencyCode): BranchPool
    {
        return BranchPool::firstOrCreate(
            ['branch_id' => $branch->id, 'currency_code' => $currencyCode],
            ['available_balance' => 0, 'allocated_balance' => 0]
        );
    }

    public function getPoolBalance(Branch $branch, string $currencyCode): array
    {
        $pool = $this->getOrCreateForBranch($branch, $currencyCode);
        
        return [
            'currency_code' => $currencyCode,
            'available' => $pool->available_balance,
            'allocated' => $pool->allocated_balance,
            'total' => $pool->total_balance,
        ];
    }

    public function allocateToTeller(Branch $branch, string $currencyCode, float|string $amount): bool
    {
        $pool = $this->getOrCreateForBranch($branch, $currencyCode);
        return $pool->allocate($amount);
    }

    public function deallocateFromTeller(Branch $branch, string $currencyCode, float|string $amount): bool
    {
        $pool = $this->getOrCreateForBranch($branch, $currencyCode);
        return $pool->deallocate($amount);
    }

    public function replenish(Branch $branch, string $currencyCode, float|string $amount, int $approvedBy): BranchPool
    {
        $pool = $this->getOrCreateForBranch($branch, $currencyCode);
        $pool->increment('available_balance', $amount);
        
        return $pool;
    }

    public function getAllPoolsForBranch(Branch $branch): Collection
    {
        return BranchPool::where('branch_id', $branch->id)->get();
    }

    public function getAvailablePoolsForBranch(Branch $branch): Collection
    {
        return BranchPool::where('branch_id', $branch->id)
            ->where('available_balance', '>', 0)
            ->get();
    }
}
```

- [ ] **Step 5: Create failing test**

```php
<?php
// tests/Unit/BranchPoolServiceTest.php
namespace Tests\Unit;

use App\Models\Branch;
use App\Models\BranchPool;
use App\Services\BranchPoolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchPoolServiceTest extends TestCase
{
    use RefreshDatabase;

    protected BranchPoolService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BranchPoolService();
    }

    public function test_get_or_create_for_branch(): void
    {
        $branch = Branch::factory()->create();
        
        $pool = $this->service->getOrCreateForBranch($branch, 'MYR');
        
        $this->assertInstanceOf(BranchPool::class, $pool);
        $this->assertEquals($branch->id, $pool->branch_id);
        $this->assertEquals('MYR', $pool->currency_code);
    }

    public function test_allocate_to_teller_reduces_available(): void
    {
        $branch = Branch::factory()->create();
        $pool = BranchPool::factory()->for($branch)->myr()->create([
            'available_balance' => '50000.0000',
            'allocated_balance' => '0.0000',
        ]);
        
        $result = $this->service->allocateToTeller($branch, 'MYR', '10000.0000');
        
        $this->assertTrue($result);
        $pool->refresh();
        $this->assertEquals('40000.0000', $pool->available_balance);
        $this->assertEquals('10000.0000', $pool->allocated_balance);
    }

    public function test_allocate_fails_when_insufficient(): void
    {
        $branch = Branch::factory()->create();
        $pool = BranchPool::factory()->for($branch)->myr()->create([
            'available_balance' => '5000.0000',
            'allocated_balance' => '0.0000',
        ]);
        
        $result = $this->service->allocateToTeller($branch, 'MYR', '10000.0000');
        
        $this->assertFalse($result);
        $pool->refresh();
        $this->assertEquals('5000.0000', $pool->available_balance);
    }

    public function test_deallocate_returns_to_available(): void
    {
        $branch = Branch::factory()->create();
        $pool = BranchPool::factory()->for($branch)->myr()->create([
            'available_balance' => '40000.0000',
            'allocated_balance' => '10000.0000',
        ]);
        
        $result = $this->service->deallocateFromTeller($branch, 'MYR', '10000.0000');
        
        $this->assertTrue($result);
        $pool->refresh();
        $this->assertEquals('50000.0000', $pool->available_balance);
        $this->assertEquals('0.0000', $pool->allocated_balance);
    }
}
```

- [ ] **Step 6: Run test to verify it fails**

```bash
php artisan test --filter=BranchPoolServiceTest
```

- [ ] **Step 7: Run migrations**

```bash
php artisan migrate
```

- [ ] **Step 8: Run test to verify it passes**

```bash
php artisan test --filter=BranchPoolServiceTest
```

- [ ] **Step 9: Commit**

```bash
git add app/Models/BranchPool.php app/Services/BranchPoolService.php database/migrations/2026_04_16_000001_create_branch_pools_table.php database/factories/BranchPoolFactory.php tests/Unit/BranchPoolServiceTest.php
git commit -m "feat: add BranchPool model and service"
```

---

### Task 2: Create TellerAllocation Migration

**Files:**
- Create: `database/migrations/2026_04_16_000002_create_teller_allocations_table.php`
- Create: `app/Models/TellerAllocation.php`
- Create: `database/factories/TellerAllocationFactory.php`

- [ ] **Step 1: Create migration**

```php
<?php
// database/migrations/2026_04_16_000002_create_teller_allocations_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teller_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('branch_id')->constrained()->onDelete('cascade');
            $table->foreignId('counter_id')->nullable()->constrained()->onDelete('set null');
            $table->string('currency_code', 3);
            $table->decimal('allocated_amount', 20, 4);
            $table->decimal('current_balance', 20, 4);
            $table->decimal('requested_amount', 20, 4)->nullable();
            $table->decimal('daily_limit_myr', 20, 4)->nullable();
            $table->decimal('daily_used_myr', 20, 4)->default(0);
            $table->enum('status', ['pending', 'approved', 'active', 'returned', 'closed', 'auto_returned'])->default('pending');
            $table->date('session_date');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'currency_code', 'session_date', 'status']);
            $table->index(['counter_id', 'session_date']);
            $table->index(['branch_id', 'session_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teller_allocations');
    }
};
```

- [ ] **Step 2: Create TellerAllocation model**

```php
<?php
// app/Models/TellerAllocation.php
namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TellerAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'branch_id',
        'counter_id',
        'currency_code',
        'allocated_amount',
        'current_balance',
        'requested_amount',
        'daily_limit_myr',
        'daily_used_myr',
        'status',
        'session_date',
        'approved_by',
        'approved_at',
        'opened_at',
        'closed_at',
    ];

    protected $casts = [
        'allocated_amount' => 'decimal:4',
        'current_balance' => 'decimal:4',
        'requested_amount' => 'decimal:4',
        'daily_limit_myr' => 'decimal:4',
        'daily_used_myr' => 'decimal:4',
        'session_date' => 'date',
        'approved_at' => 'datetime',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function counter(): BelongsTo
    {
        return $this->belongsTo(Counter::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isReturned(): bool
    {
        return in_array($this->status, ['returned', 'closed', 'auto_returned']);
    }

    public function hasAvailable(float|string $amount): bool
    {
        return bccomp($this->current_balance, (string) $amount, 4) >= 0;
    }

    public function deduct(float|string $amount): bool
    {
        if (!$this->hasAvailable($amount)) {
            return false;
        }
        
        $this->decrement('current_balance', $amount);
        return true;
    }

    public function add(float|string $amount): void
    {
        $this->increment('current_balance', $amount);
    }

    public function addDailyUsed(float|string $amountMyr): void
    {
        $this->increment('daily_used_myr', $amountMyr);
    }

    public function hasDailyLimitRemaining(float|string $amountMyr): bool
    {
        if ($this->daily_limit_myr === null) {
            return true;
        }
        
        $remaining = bcsub($this->daily_limit_myr, $this->daily_used_myr, 4);
        return bccomp($remaining, (string) $amountMyr, 4) >= 0;
    }

    public function approve(User $approver, float|string $allocatedAmount, ?float|string $dailyLimitMyr = null): void
    {
        $this->update([
            'status' => 'approved',
            'allocated_amount' => $allocatedAmount,
            'current_balance' => $allocatedAmount,
            'daily_limit_myr' => $dailyLimitMyr,
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);
    }

    public function activate(): void
    {
        $this->update([
            'status' => 'active',
            'opened_at' => now(),
        ]);
    }

    public function returnToPool(): void
    {
        $this->update([
            'status' => 'returned',
            'closed_at' => now(),
        ]);
    }

    public function forceReturn(): void
    {
        $this->update([
            'status' => 'auto_returned',
            'closed_at' => now(),
        ]);
    }
}
```

- [ ] **Step 3: Create TellerAllocationFactory**

```php
<?php
// database/factories/TellerAllocationFactory.php
namespace Database\Factories;

use App\Models\Branch;
use App\Models\Counter;
use App\Models\TellerAllocation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TellerAllocationFactory extends Factory
{
    protected $model = TellerAllocation::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['role' => 'teller']),
            'branch_id' => Branch::factory(),
            'counter_id' => Counter::factory(),
            'currency_code' => 'MYR',
            'allocated_amount' => $this->faker->randomFloat(4, 10000, 50000),
            'current_balance' => $this->faker->randomFloat(4, 10000, 50000),
            'requested_amount' => null,
            'daily_limit_myr' => null,
            'daily_used_myr' => 0,
            'status' => 'active',
            'session_date' => now()->toDateString(),
            'approved_by' => User::factory()->state(['role' => 'manager']),
            'approved_at' => now(),
            'opened_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'approved_by' => null,
            'approved_at' => null,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    public function returned(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'returned',
            'closed_at' => now(),
        ]);
    }
}
```

- [ ] **Step 4: Add TillBalance FK**

**Files:**
- Modify: `database/migrations/2026_04_16_000003_add_fields_to_till_balances_table.php`

```php
<?php
// database/migrations/2026_04_16_000003_add_fields_to_till_balances_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('till_balances', function (Blueprint $table) {
            $table->foreignId('teller_allocation_id')->nullable()->after('id')->constrained('teller_allocations')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('till_balances', function (Blueprint $table) {
            $table->dropForeign(['teller_allocation_id']);
            $table->dropColumn('teller_allocation_id');
        });
    }
};
```

- [ ] **Step 5: Add CounterSession fields**

**Files:**
- Modify: `database/migrations/2026_04_16_000004_add_fields_to_counter_sessions_table.php`

```php
<?php
// database/migrations/2026_04_16_000004_add_fields_to_counter_sessions_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('counter_sessions', function (Blueprint $table) {
            $table->foreignId('teller_allocation_id')->nullable()->after('id')->constrained('teller_allocations')->onDelete('set null');
            $table->decimal('requested_amount_myr', 20, 4)->nullable()->after('status');
            $table->decimal('daily_limit_myr', 20, 4)->nullable()->after('requested_amount_myr');
        });
    }

    public function down(): void
    {
        Schema::table('counter_sessions', function (Blueprint $table) {
            $table->dropForeign(['teller_allocation_id']);
            $table->dropColumn(['teller_allocation_id', 'requested_amount_myr', 'daily_limit_myr']);
        });
    }
};
```

- [ ] **Step 6: Add Counter assigned_teller_id**

**Files:**
- Modify: `database/migrations/2026_04_16_000005_add_assigned_teller_to_counters_table.php`

```php
<?php
// database/migrations/2026_04_16_000005_add_assigned_teller_to_counters_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('counters', function (Blueprint $table) {
            $table->foreignId('assigned_teller_id')->nullable()->after('branch_id')->constrained('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('counters', function (Blueprint $table) {
            $table->dropForeign(['assigned_teller_id']);
            $table->dropColumn('assigned_teller_id');
        });
    }
};
```

- [ ] **Step 7: Run migrations**

```bash
php artisan migrate
```

- [ ] **Step 8: Commit**

```bash
git add database/migrations/ app/Models/TellerAllocation.php database/factories/TellerAllocationFactory.php
git commit -m "feat: add TellerAllocation model and migrations"
```

---

## Phase 2: BranchPoolService

### Task 3: TellerAllocationService

**Files:**
- Create: `app/Services/TellerAllocationService.php`
- Test: `tests/Unit/TellerAllocationServiceTest.php`

- [ ] **Step 1: Create TellerAllocationService**

```php
<?php
// app/Services/TellerAllocationService.php
namespace App\Services;

use App\Models\Branch;
use App\Models\BranchPool;
use App\Models\Counter;
use App\Models\CounterSession;
use App\Models\TellerAllocation;
use App\Models\User;
use Illuminate\Support\Collection;
use Exception;

class TellerAllocationService
{
    public function __construct(
        protected BranchPoolService $branchPoolService,
        protected MathService $mathService,
    ) {}

    public function requestAllocation(User $teller, User $approver, string $currencyCode, float|string $requestedAmount, ?float|string $dailyLimitMyr = null, ?Counter $counter = null): TellerAllocation
    {
        $branch = $teller->branch;
        
        if (!$branch) {
            throw new Exception('Teller must be assigned to a branch');
        }

        $pool = $this->branchPoolService->getOrCreateForBranch($branch, $currencyCode);

        if (!$pool->hasAvailable($requestedAmount)) {
            throw new Exception('Insufficient available balance in branch pool');
        }

        $allocation = TellerAllocation::create([
            'user_id' => $teller->id,
            'branch_id' => $branch->id,
            'counter_id' => $counter?->id,
            'currency_code' => $currencyCode,
            'requested_amount' => $requestedAmount,
            'allocated_amount' => $requestedAmount,
            'current_balance' => 0,
            'daily_limit_myr' => $dailyLimitMyr,
            'daily_used_myr' => 0,
            'status' => 'pending',
            'session_date' => now()->toDateString(),
        ]);

        return $allocation;
    }

    public function approveAllocation(TellerAllocation $allocation, User $approver, float|string $approvedAmount, ?float|string $dailyLimitMyr = null): TellerAllocation
    {
        $branch = $allocation->branch;
        
        if (!$this->branchPoolService->allocateToTeller($branch, $allocation->currency_code, $approvedAmount)) {
            throw new Exception('Failed to allocate from branch pool');
        }

        $allocation->approve($approver, $approvedAmount, $dailyLimitMyr);
        
        return $allocation;
    }

    public function activateAllocation(TellerAllocation $allocation): TellerAllocation
    {
        if (!$allocation->isApproved()) {
            throw new Exception('Can only activate approved allocation');
        }

        $allocation->activate();
        
        return $allocation;
    }

    public function modifyAllocation(TellerAllocation $allocation, User $modifier, float|string $newAmount, bool $isIncrease): TellerAllocation
    {
        $branch = $allocation->branch;
        
        if ($isIncrease) {
            if (!$this->branchPoolService->allocateToTeller($branch, $allocation->currency_code, $newAmount)) {
                throw new Exception('Failed to allocate additional amount from branch pool');
            }
            $allocation->current_balance = bcadd($allocation->current_balance, $newAmount, 4);
            $allocation->allocated_amount = bcadd($allocation->allocated_amount, $newAmount, 4);
        } else {
            $availableToReturn = bcsub($allocation->allocated_amount, $allocation->current_balance, 4);
            $returnAmount = min((float) $newAmount, (float) $availableToReturn);
            
            if ($returnAmount > 0) {
                $this->branchPoolService->deallocateFromTeller($branch, $allocation->currency_code, $returnAmount);
            }
            
            $allocation->allocated_amount = bcsub($allocation->allocated_amount, $newAmount, 4);
            $allocation->current_balance = bcsub($allocation->current_balance, bcsub($newAmount, $returnAmount, 4), 4);
        }
        
        $allocation->save();
        
        return $allocation;
    }

    public function returnToPool(TellerAllocation $allocation): TellerAllocation
    {
        $branch = $allocation->branch;
        
        $returnAmount = $allocation->current_balance;
        
        if ($this->mathService->compare($returnAmount, '0') > 0) {
            $this->branchPoolService->deallocateFromTeller($branch, $allocation->currency_code, $returnAmount);
        }
        
        $allocation->returnToPool();
        
        return $allocation;
    }

    public function forceReturnAllOpen(): int
    {
        $openAllocations = TellerAllocation::where('status', 'active')
            ->where('session_date', '<', now()->toDateString())
            ->get();
        
        foreach ($openAllocations as $allocation) {
            $this->returnToPool($allocation);
            $allocation->forceReturn();
        }
        
        return $openAllocations->count();
    }

    public function getActiveAllocation(User $teller, string $currencyCode): ?TellerAllocation
    {
        return TellerAllocation::where('user_id', $teller->id)
            ->where('currency_code', $currencyCode)
            ->where('status', 'active')
            ->where('session_date', now()->toDateString())
            ->first();
    }

    public function getPendingAllocationsForBranch(Branch $branch): Collection
    {
        return TellerAllocation::where('branch_id', $branch->id)
            ->where('status', 'pending')
            ->where('session_date', now()->toDateString())
            ->with('user')
            ->get();
    }

    public function getActiveAllocationsForBranch(Branch $branch): Collection
    {
        return TellerAllocation::where('branch_id', $branch->id)
            ->where('status', 'active')
            ->where('session_date', now()->toDateString())
            ->with('user')
            ->get();
    }

    public function transferToTeller(TellerAllocation $allocation, User $toTeller): TellerAllocation
    {
        $allocation->update([
            'user_id' => $toTeller->id,
        ]);
        
        return $allocation;
    }

    public function validateTransaction(User $teller, string $currencyCode, float|string $amountMyr, bool $isBuy): array
    {
        $allocation = $this->getActiveAllocation($teller, $currencyCode);
        
        if (!$allocation) {
            return ['valid' => false, 'reason' => 'No active allocation for this currency'];
        }
        
        if ($isBuy && !$allocation->hasAvailable($amountMyr)) {
            return ['valid' => false, 'reason' => 'Insufficient allocation balance'];
        }
        
        if (!$allocation->hasDailyLimitRemaining($amountMyr)) {
            return ['valid' => false, 'reason' => 'Daily limit exceeded'];
        }
        
        return ['valid' => true, 'allocation' => $allocation];
    }
}
```

- [ ] **Step 2: Create failing test**

```php
<?php
// tests/Unit/TellerAllocationServiceTest.php
namespace Tests\Unit;

use App\Models\Branch;
use App\Models\BranchPool;
use App\Models\Counter;
use App\Models\TellerAllocation;
use App\Models\User;
use App\Services\BranchPoolService;
use App\Services\MathService;
use App\Services\TellerAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TellerAllocationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected TellerAllocationService $service;
    protected BranchPoolService $branchPoolService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->branchPoolService = new BranchPoolService();
        $this->service = new TellerAllocationService($this->branchPoolService, new MathService());
    }

    public function test_request_allocation_creates_pending(): void
    {
        $branch = Branch::factory()->create();
        $pool = BranchPool::factory()->for($branch)->myr()->create([
            'available_balance' => '50000.0000',
        ]);
        $teller = User::factory()->create(['role' => 'teller', 'branch_id' => $branch->id]);
        $manager = User::factory()->create(['role' => 'manager', 'branch_id' => $branch->id]);
        
        $allocation = $this->service->requestAllocation($teller, $manager, 'MYR', '10000.0000');
        
        $this->assertInstanceOf(TellerAllocation::class, $allocation);
        $this->assertEquals('pending', $allocation->status);
        $this->assertEquals('10000.0000', $allocation->requested_amount);
    }

    public function test_approve_allocation_deducts_from_pool(): void
    {
        $branch = Branch::factory()->create();
        $pool = BranchPool::factory()->for($branch)->myr()->create([
            'available_balance' => '50000.0000',
        ]);
        $teller = User::factory()->create(['role' => 'teller', 'branch_id' => $branch->id]);
        $manager = User::factory()->create(['role' => 'manager', 'branch_id' => $branch->id]);
        $allocation = $this->service->requestAllocation($teller, $manager, 'MYR', '10000.0000');
        
        $this->service->approveAllocation($allocation, $manager, '10000.0000', '50000.0000');
        
        $pool->refresh();
        $allocation->refresh();
        $this->assertEquals('40000.0000', $pool->available_balance);
        $this->assertEquals('approved', $allocation->status);
        $this->assertEquals('10000.0000', $allocation->allocated_amount);
        $this->assertEquals('10000.0000', $allocation->current_balance);
    }

    public function test_activate_allocation(): void
    {
        $branch = Branch::factory()->create();
        $teller = User::factory()->create(['role' => 'teller', 'branch_id' => $branch->id]);
        $manager = User::factory()->create(['role' => 'manager', 'branch_id' => $branch->id]);
        $allocation = TellerAllocation::factory()->pending()->create([
            'user_id' => $teller->id,
            'branch_id' => $branch->id,
            'status' => 'approved',
            'allocated_amount' => '10000.0000',
            'current_balance' => '10000.0000',
        ]);
        
        $this->service->activateAllocation($allocation);
        
        $allocation->refresh();
        $this->assertEquals('active', $allocation->status);
        $this->assertNotNull($allocation->opened_at);
    }

    public function test_return_to_pool_returns_balance(): void
    {
        $branch = Branch::factory()->create();
        $pool = BranchPool::factory()->for($branch)->myr()->create([
            'available_balance' => '40000.0000',
            'allocated_balance' => '10000.0000',
        ]);
        $teller = User::factory()->create(['role' => 'teller', 'branch_id' => $branch->id]);
        $allocation = TellerAllocation::factory()->active()->create([
            'user_id' => $teller->id,
            'branch_id' => $branch->id,
            'current_balance' => '8000.0000',
            'allocated_amount' => '10000.0000',
        ]);
        
        $this->service->returnToPool($allocation);
        
        $pool->refresh();
        $allocation->refresh();
        $this->assertEquals('48000.0000', $pool->available_balance);
        $this->assertEquals('returned', $allocation->status);
    }

    public function test_validate_transaction_buy_insufficient_balance(): void
    {
        $branch = Branch::factory()->create();
        $teller = User::factory()->create(['role' => 'teller', 'branch_id' => $branch->id]);
        $allocation = TellerAllocation::factory()->active()->create([
            'user_id' => $teller->id,
            'branch_id' => $branch->id,
            'current_balance' => '5000.0000',
        ]);
        
        $result = $this->service->validateTransaction($teller, 'MYR', '10000.0000', true);
        
        $this->assertFalse($result['valid']);
        $this->assertEquals('Insufficient allocation balance', $result['reason']);
    }

    public function test_validate_transaction_daily_limit_exceeded(): void
    {
        $branch = Branch::factory()->create();
        $teller = User::factory()->create(['role' => 'teller', 'branch_id' => $branch->id]);
        $allocation = TellerAllocation::factory()->active()->create([
            'user_id' => $teller->id,
            'branch_id' => $branch->id,
            'current_balance' => '50000.0000',
            'daily_limit_myr' => '10000.0000',
            'daily_used_myr' => '9000.0000',
        ]);
        
        $result = $this->service->validateTransaction($teller, 'MYR', '2000.0000', true);
        
        $this->assertFalse($result['valid']);
        $this->assertEquals('Daily limit exceeded', $result['reason']);
    }
}
```

- [ ] **Step 3: Run test to verify it fails**

```bash
php artisan test --filter=TellerAllocationServiceTest
```

- [ ] **Step 4: Run test to verify it passes**

```bash
php artisan test --filter=TellerAllocationServiceTest
```

- [ ] **Step 5: Commit**

```bash
git add app/Services/TellerAllocationService.php tests/Unit/TellerAllocationServiceTest.php
git commit -m "feat: add TellerAllocationService"
```

---

## Phase 3: Opening Workflow Integration

### Task 4: Modify CounterService to Integrate Allocation

**Files:**
- Modify: `app/Services/CounterService.php`

- [ ] **Step 1: Add allocation integration to openSession**

```php
// In CounterService, modify openSession method to accept allocation_id
public function openSession(Counter $counter, User $user, array $openingFloats, ?TellerAllocation $allocation = null): CounterSession
{
    // ... existing validation ...
    
    $session = CounterSession::create([
        'counter_id' => $counter->id,
        'user_id' => $user->id,
        'session_date' => $today,
        'opened_at' => $now,
        'opened_by' => $user->id,
        'status' => CounterSessionStatus::Open,
        'teller_allocation_id' => $allocation?->id,
    ]);
    
    // ... rest unchanged ...
}
```

- [ ] **Step 2: Add daily_limit_myr and requested_amount_myr to session**

- [ ] **Step 3: Create CounterOpeningWorkflowService**

```php
<?php
// app/Services/CounterOpeningWorkflowService.php
namespace App\Services;

use App\Models\Branch;
use App\Models\BranchPool;
use App\Models\Counter;
use App\Models\CounterSession;
use App\Models\TellerAllocation;
use App\Models\User;
use Exception;

class CounterOpeningWorkflowService
{
    public function __construct(
        protected BranchPoolService $branchPoolService,
        protected TellerAllocationService $tellerAllocationService,
        protected CounterService $counterService,
    ) {}

    public function initiateOpeningRequest(User $teller, Counter $counter, array $requestedAmounts): array
    {
        $branch = $teller->branch;
        
        $requests = [];
        foreach ($requestedAmounts as $currency => $amount) {
            $pool = $this->branchPoolService->getOrCreateForBranch($branch, $currency);
            
            if (!$pool->hasAvailable($amount)) {
                throw new Exception("Insufficient {$currency} balance in branch pool. Available: {$pool->available_balance}");
            }
            
            $allocation = $this->tellerAllocationService->requestAllocation(
                $teller,
                $teller,
                $currency,
                $amount,
                null,
                $counter
            );
            
            $requests[] = $allocation;
        }
        
        return $requests;
    }

    public function approveAndOpen(User $manager, Counter $counter, User $teller, array $approvedAmounts, array $dailyLimits = []): CounterSession
    {
        $branch = $teller->branch;
        $today = now()->toDateString();
        
        $tellerAllocations = [];
        foreach ($approvedAmounts as $currency => $amount) {
            $allocation = TellerAllocation::where('user_id', $teller->id)
                ->where('currency_code', $currency)
                ->where('session_date', $today)
                ->where('status', 'pending')
                ->first();
            
            if (!$allocation) {
                throw new Exception("No pending allocation found for {$currency}");
            }
            
            $dailyLimit = $dailyLimits[$currency] ?? null;
            $this->tellerAllocationService->approveAllocation($allocation, $manager, $amount, $dailyLimit);
            $this->tellerAllocationService->activateAllocation($allocation);
            
            $tellerAllocations[] = $allocation;
        }
        
        $openingFloats = collect($tellerAllocations)->mapWithKeys(fn ($a) => [$a->currency_code => [
            'currency_id' => $a->currency_code,
            'amount' => $a->current_balance,
        ]])->toArray();
        
        $session = $this->counterService->openSession($counter, $teller, $openingFloats);
        
        foreach ($tellerAllocations as $allocation) {
            $allocation->update(['counter_id' => $counter->id]);
        }
        
        return $session;
    }

    public function getPendingRequestsForBranch(Branch $branch): array
    {
        return $this->tellerAllocationService->getPendingAllocationsForBranch($branch)
            ->groupBy(fn ($a) => $a->user_id);
    }
}
```

- [ ] **Step 4: Commit**

```bash
git add app/Services/CounterOpeningWorkflowService.php
git commit -m "feat: add CounterOpeningWorkflowService"
```

---

## Phase 4: Transaction Integration

### Task 5: Modify TransactionService to Validate Against Allocation

**Files:**
- Modify: `app/Services/TransactionService.php`

- [ ] **Step 1: Add allocation validation**

```php
// In TransactionService, add before transaction creation:
protected function validateAgainstAllocation(Transaction $transaction, bool $isBuy): void
{
    $teller = $transaction->user;
    $currencyCode = $transaction->currency_code;
    $amountMyr = $transaction->amount_local;
    
    $validator = app(TellerAllocationService::class);
    $result = $validator->validateTransaction($teller, $currencyCode, $amountMyr, $isBuy);
    
    if (!$result['valid']) {
        throw new Exception($result['reason']);
    }
}
```

- [ ] **Step 2: Update transaction recording to update allocation balance**

- [ ] **Step 3: Commit**

---

## Phase 5: EOD & Handover

### Task 6: EOD Close Workflow

**Files:**
- Modify: `app/Services/CounterService.php` - add return to pool

- [ ] **Step 1: Add EOD close with pool return**

```php
public function closeSessionAndReturn(CounterSession $session, User $user, array $closingFloats, ?string $notes = null, ?User $supervisor = null): CounterSession
{
    $allocationService = app(TellerAllocationService::class);
    
    // Get the allocation linked to this session
    $allocation = $allocationService->getActiveAllocation($user, 'MYR');
    
    // Perform variance calculation (existing logic)
    $session = $this->closeSession($session, $user, $closingFloats, $notes, $supervisor);
    
    // Return balance to pool
    if ($allocation) {
        $allocationService->returnToPool($allocation);
    }
    
    return $session;
}
```

### Task 7: Handover Workflow

**Files:**
- Modify: `app/Services/CounterService.php` - modify initiateHandover

- [ ] **Step 1: Update handover to transfer allocation**

```php
// In CounterService, modify initiateHandover to transfer allocation
public function initiateHandover(...): array
{
    $allocationService = app(TellerAllocationService::class);
    
    // ... existing validation ...
    
    // Get old allocation
    $allocation = TellerAllocation::where('user_id', $fromUser->id)
        ->where('status', 'active')
        ->where('session_date', now()->toDateString())
        ->first();
    
    // ... existing handover logic ...
    
    // Transfer allocation to new user
    if ($allocation) {
        $allocationService->transferToTeller($allocation, $toUser);
    }
    
    // ... rest unchanged ...
}
```

---

## Phase 6: Permissions & Reporting

### Task 8: Add Role-Based View Permissions

**Files:**
- Modify: `app/Models/User.php` - add view permissions
- Create: `app/Services/BranchStockReportingService.php`

- [ ] **Step 1: Add permission methods to User model**

```php
public function canViewTellerAllocation(User $teller): bool
{
    if ($this->isAdmin()) return true;
    if ($this->isManager() && $this->branch_id === $teller->branch_id) return true;
    if ($this->id === $teller->id) return true;
    return false;
}

public function canViewBranchPools(Branch $branch): bool
{
    if ($this->isAdmin()) return true;
    if ($this->isManager() && $this->branch_id === $branch->id) return true;
    return false;
}
```

- [ ] **Step 2: Create BranchStockReportingService**

```php
<?php
// app/Services/BranchStockReportingService.php
namespace App\Services;

use App\Models\Branch;
use App\Models\BranchPool;
use App\Models\Counter;
use App\Models\CounterSession;
use App\Models\TellerAllocation;
use Illuminate\Support\Collection;

class BranchStockReportingService
{
    public function getBranchPoolSummary(Branch $branch): array
    {
        $pools = BranchPool::where('branch_id', $branch->id)->get();
        
        return [
            'branch' => $branch,
            'pools' => $pools->map(fn ($p) => [
                'currency' => $p->currency_code,
                'available' => $p->available_balance,
                'allocated' => $p->allocated_balance,
                'total' => $p->total_balance,
            ]),
            'total_myriad' => $pools->sum('total_balance'),
        ];
    }

    public function getTellerAllocationsSummary(Branch $branch): array
    {
        $allocations = TellerAllocation::where('branch_id', $branch->id)
            ->where('session_date', now()->toDateString())
            ->with('user')
            ->get();
        
        return [
            'pending' => $allocations->where('status', 'pending')->count(),
            'active' => $allocations->where('status', 'active')->count(),
            'returned' => $allocations->whereIn('status', ['returned', 'closed'])->count(),
            'total_allocated' => $allocations->sum('allocated_amount'),
            'total_outstanding' => $allocations->where('status', 'active')->sum('current_balance'),
        ];
    }

    public function getEodReport(Branch $branch, string $date): array
    {
        return [
            'branch' => $branch,
            'date' => $date,
            'pool_summary' => $this->getBranchPoolSummary($branch),
            'allocation_summary' => $this->getTellerAllocationsSummary($branch),
            'sessions' => CounterSession::whereHas('counter', fn ($q) => $q->where('branch_id', $branch->id))
                ->where('session_date', $date)
                ->with('user')
                ->get(),
        ];
    }
}
```

---

## Phase 7: Testing

### Task 9: Integration Test

**Files:**
- Create: `tests/Feature/BranchAllocationWorkflowTest.php`

- [ ] **Step 1: Create full workflow test**

```php
<?php
// tests/Feature/BranchAllocationWorkflowTest.php
namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchPool;
use App\Models\Counter;
use App\Models\CounterSession;
use App\Models\TellerAllocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchAllocationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_teller_opening_workflow(): void
    {
        $branch = Branch::factory()->create();
        $pool = BranchPool::factory()->for($branch)->myr()->create([
            'available_balance' => '100000.0000',
        ]);
        $manager = User::factory()->create(['role' => 'manager', 'branch_id' => $branch->id]);
        $teller = User::factory()->create(['role' => 'teller', 'branch_id' => $branch->id]);
        $counter = Counter::factory()->for($branch)->create(['assigned_teller_id' => $teller->id]);
        
        // Teller requests allocation
        $response = $this->actingAs($teller)->postJson('/api/v1/teller/allocation/request', [
            'counter_id' => $counter->id,
            'currency_code' => 'MYR',
            'amount' => '50000.0000',
        ]);
        
        $response->assertStatus(201);
        $this->assertDatabaseHas('teller_allocations', [
            'user_id' => $teller->id,
            'status' => 'pending',
        ]);
        
        // Manager approves
        $allocation = TellerAllocation::first();
        $response = $this->actingAs($manager)->patchJson("/api/v1/manager/allocation/{$allocation->id}/approve", [
            'approved_amount' => '50000.0000',
            'daily_limit_myr' => '100000.0000',
        ]);
        
        $response->assertStatus(200);
        $allocation->refresh();
        $this->assertEquals('approved', $allocation->status);
        
        // Manager activates
        $this->actingAs($manager)->postJson("/api/v1/manager/allocation/{$allocation->id}/activate");
        $allocation->refresh();
        $this->assertEquals('active', $allocation->status);
        
        // Teller opens counter
        $response = $this->actingAs($teller)->postJson("/api/v1/counter/{$counter->code}/open", [
            'opening_floats' => [
                ['currency_id' => 'MYR', 'amount' => '50000.0000'],
            ],
        ]);
        
        $response->assertStatus(200);
        
        // Verify pool was deducted
        $pool->refresh();
        $this->assertEquals('50000.0000', $pool->available_balance);
        $this->assertEquals('50000.0000', $pool->allocated_balance);
    }

    public function test_eod_return_workflow(): void
    {
        $branch = Branch::factory()->create();
        $pool = BranchPool::factory()->for($branch)->myr()->create([
            'available_balance' => '50000.0000',
            'allocated_balance' => '50000.0000',
        ]);
        $manager = User::factory()->create(['role' => 'manager', 'branch_id' => $branch->id]);
        $teller = User::factory()->create(['role' => 'teller', 'branch_id' => $branch->id]);
        
        $allocation = TellerAllocation::factory()->active()->create([
            'user_id' => $teller->id,
            'branch_id' => $branch->id,
            'current_balance' => '48000.0000',
            'allocated_amount' => '50000.0000',
        ]);
        
        // Teller returns to pool
        $this->actingAs($teller)->postJson("/api/v1/teller/allocation/return");
        
        $allocation->refresh();
        $pool->refresh();
        
        $this->assertEquals('returned', $allocation->status);
        $this->assertEquals('98000.0000', $pool->available_balance);
        $this->assertEquals('0.0000', $pool->allocated_balance);
    }
}
```

- [ ] **Step 2: Run all tests**

```bash
php artisan test --filter=BranchAllocationWorkflowTest
```

---

## Verification Checklist

After implementation, verify:

- [ ] Teller can request allocation
- [ ] Manager can approve/modify allocation
- [ ] Manager can modify allocation anytime (add/reduce)
- [ ] Counter opens with allocation linked
- [ ] Transaction validates against allocation balance
- [ ] Transaction validates against daily limit
- [ ] EOD return returns full balance to pool
- [ ] Handover transfers allocation to new teller
- [ ] Manager can force-close absent teller
- [ ] Auto-settlement at midnight returns open allocations
- [ ] Role-based view permissions work correctly

---

## Spec Coverage

| Spec Section | Tasks |
|--------------|-------|
| 2.1 BranchPool | Task 1 |
| 2.2 TellerAllocation | Task 2 |
| 3.1 Role Hierarchy | Task 8 |
| 3.2 Permission Matrix | Task 8 |
| 4.1 Branch Opening | Task 3, 4 |
| 4.2 Intraday Transaction | Task 5 |
| 4.2 Manager Modification | Task 3 |
| 4.3 Handover | Task 7 |
| 4.4 EOD Close | Task 6 |
| 4.4 Period Auto-Settlement | Task 3 |
| 5.1 Opening Rules | Task 3, 4 |
| 5.2 Transaction Rules | Task 5 |
| 5.3 Allocation Rules | Task 3, 6 |
| 5.4 Variance Rules | Existing CounterService |
| 6. Views & Reporting | Task 8 |
| 7. API Endpoints | Task 4, 8 |

**Plan complete.** Two execution options:

**1. Subagent-Driven (recommended)** - I dispatch a fresh subagent per task, review between tasks, fast iteration

**2. Inline Execution** - Execute tasks in this session using executing-plans, batch execution with checkpoints

**Which approach?**
