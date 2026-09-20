<?php

namespace Tests\Unit;

use App\Enums\TellerAllocationStatus;
use App\Exceptions\Domain\AllocationValidationException;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\Currency;
use App\Models\TellerAllocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TellerAllocationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function can_create_teller_allocation(): void
    {
        $currency = Currency::factory()->create();
        $branch = Branch::factory()->create();
        $user = User::factory()->create();

        $allocation = TellerAllocation::factory()->create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'currency_code' => $currency->code,
            'session_date' => now()->toDateString(),
            'status' => TellerAllocationStatus::Pending,
        ]);

        $this->assertDatabaseHas('teller_allocations', [
            'id' => $allocation->id,
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'currency_code' => $currency->code,
        ]);
    }

    #[Test]
    public function has_pending_status_check(): void
    {
        /** @var TellerAllocation $allocation */
        $allocation = TellerAllocation::factory()->pending()->create();

        $this->assertTrue($allocation->isPending());
        $this->assertFalse($allocation->isApproved());
        $this->assertFalse($allocation->isActive());
        $this->assertFalse($allocation->isReturned());
    }

    #[Test]
    public function has_available_uses_bccomp(): void
    {
        /** @var TellerAllocation $allocation */
        $allocation = TellerAllocation::factory()->create([
            'current_quantity' => '5000.0000',
        ]);

        $this->assertTrue($allocation->hasAvailable('5000.0000'));
        $this->assertTrue($allocation->hasAvailable('4999.9999'));
        $this->assertFalse($allocation->hasAvailable('5000.0001'));
    }

    #[Test]
    public function deduct_reduces_balance(): void
    {
        /** @var TellerAllocation $allocation */
        $allocation = TellerAllocation::factory()->create([
            'current_quantity' => '10000.0000',
        ]);

        $allocation->deduct('2500.0000');

        $this->assertEquals('7500.0000', $allocation->current_quantity);
    }

    #[Test]
    public function add_increases_balance(): void
    {
        /** @var TellerAllocation $allocation */
        $allocation = TellerAllocation::factory()->create([
            'current_quantity' => '10000.0000',
        ]);

        $allocation->add('1500.0000');

        $this->assertEquals('11500.0000', $allocation->current_quantity);
    }

    #[Test]
    public function add_daily_used(): void
    {
        /** @var TellerAllocation $allocation */
        $allocation = TellerAllocation::factory()->create([
            'daily_used_myr' => '0.0000',
        ]);

        $allocation->addDailyUsed('5000.0000');

        $this->assertEquals('5000.0000', $allocation->daily_used_myr);
    }

    #[Test]
    public function has_daily_limit_remaining(): void
    {
        /** @var TellerAllocation $allocation */
        $allocation = TellerAllocation::factory()->create([
            'daily_limit_myr' => '50000.0000',
            'daily_used_myr' => '20000.0000',
        ]);

        $this->assertTrue($allocation->hasDailyLimitRemaining('30000.0000'));
        $this->assertTrue($allocation->hasDailyLimitRemaining('29999.9999'));
        $this->assertFalse($allocation->hasDailyLimitRemaining('30000.0001'));
    }

    #[Test]
    public function has_daily_limit_remaining_returns_true_when_no_limit(): void
    {
        // daily_limit_myr is NOT NULL in the DB schema, so test the null-branch
        // via an unsaved model instance to avoid the constraint violation.
        $allocation = new TellerAllocation([
            'daily_limit_myr' => null,
            'daily_used_myr' => '0.0000',
        ]);

        $this->assertTrue($allocation->hasDailyLimitRemaining('999999.0000'));
    }

    #[Test]
    public function approve_updates_status_and_amounts(): void
    {
        $approver = User::factory()->create();
        /** @var TellerAllocation $allocation */
        $allocation = TellerAllocation::factory()->pending()->create([
            'allocated_quantity' => '0.0000',
            'current_quantity' => '0.0000',
        ]);

        $allocation->approve($approver, '50000.0000', '100000.0000');

        $this->assertEquals(TellerAllocationStatus::Approved, $allocation->status);
        $this->assertEquals('50000.0000', $allocation->allocated_quantity);
        $this->assertEquals('50000.0000', $allocation->current_quantity);
        $this->assertEquals('100000.0000', $allocation->daily_limit_myr);
        $this->assertEquals($approver->id, $allocation->approved_by);
        $this->assertNotNull($allocation->approved_at);
    }

    #[Test]
    public function activate_updates_status_and_timestamp(): void
    {
        /** @var TellerAllocation $allocation */
        $allocation = TellerAllocation::factory()->approved()->create();

        $allocation->activate();

        $this->assertEquals(TellerAllocationStatus::Active, $allocation->status);
        $this->assertNotNull($allocation->opened_at);
    }

    #[Test]
    public function return_to_pool(): void
    {
        /** @var TellerAllocation $allocation */
        $allocation = TellerAllocation::factory()->active()->create();

        $allocation->returnToPool();

        $this->assertEquals(TellerAllocationStatus::Returned, $allocation->status);
        $this->assertNotNull($allocation->closed_at);
    }

    #[Test]
    public function belongs_to_user(): void
    {
        $allocation = TellerAllocation::factory()->create();

        $this->assertInstanceOf(User::class, $allocation->user);
    }

    #[Test]
    public function belongs_to_branch(): void
    {
        $allocation = TellerAllocation::factory()->create();

        $this->assertInstanceOf(Branch::class, $allocation->branch);
    }

    #[Test]
    public function belongs_to_counter(): void
    {
        // Factory defaults counter_id to a new Counter; explicitly null it out.
        $allocation = TellerAllocation::factory()->create(['counter_id' => null]);

        $this->assertNull($allocation->counter);

        $counter = Counter::factory()->create();
        $allocationWithCounter = TellerAllocation::factory()->create(['counter_id' => $counter->id]);

        $this->assertInstanceOf(Counter::class, $allocationWithCounter->counter);
    }

    #[Test]
    public function belongs_to_approver(): void
    {
        $allocation = TellerAllocation::factory()->create(['approved_by' => null]);

        $this->assertNull($allocation->approver);

        $approver = User::factory()->create();
        $allocationWithApprover = TellerAllocation::factory()->create(['approved_by' => $approver->id]);

        $this->assertInstanceOf(User::class, $allocationWithApprover->approver);
    }

    #[Test]
    public function pending_state(): void
    {
        $allocation = TellerAllocation::factory()->pending()->create();

        $this->assertEquals(TellerAllocationStatus::Pending, $allocation->status);
        $this->assertNull($allocation->approved_by);
        $this->assertNull($allocation->opened_at);
        $this->assertNull($allocation->closed_at);
    }

    #[Test]
    public function active_state(): void
    {
        $allocation = TellerAllocation::factory()->active()->create();

        $this->assertEquals(TellerAllocationStatus::Active, $allocation->status);
        $this->assertNotNull($allocation->approved_by);
        $this->assertNotNull($allocation->approved_at);
        $this->assertNotNull($allocation->opened_at);
        $this->assertNull($allocation->closed_at);
    }

    #[Test]
    public function returned_state(): void
    {
        $allocation = TellerAllocation::factory()->returned()->create();

        $this->assertEquals(TellerAllocationStatus::Returned, $allocation->status);
        $this->assertNotNull($allocation->approved_by);
        $this->assertNotNull($allocation->approved_at);
        $this->assertNotNull($allocation->opened_at);
        $this->assertNotNull($allocation->closed_at);
    }

    #[Test]
    public function add_daily_used_within_limit_enforces_cap_atomically(): void
    {
        $allocation = TellerAllocation::factory()->create([
            'daily_limit_myr' => '1000.0000',
            'daily_used_myr' => '600.0000',
        ]);

        $allocation->addDailyUsedWithinLimit('400.0000');
        $this->assertEquals('1000.0000', $allocation->fresh()->daily_used_myr);

        $this->expectException(AllocationValidationException::class);
        $allocation->addDailyUsedWithinLimit('0.0001');
    }

    #[Test]
    public function add_daily_used_within_limit_matches_preflight_semantics(): void
    {
        // daily_limit_myr is NOT NULL default 0 — a zero limit blocks any spend,
        // same as hasDailyLimitRemaining().
        $allocation = TellerAllocation::factory()->create([
            'daily_limit_myr' => '0.0000',
            'daily_used_myr' => '0.0000',
        ]);

        $this->expectException(AllocationValidationException::class);
        $allocation->addDailyUsedWithinLimit('0.0001');
    }

    #[Test]
    public function sequential_amounts_preserve_decimal_precision(): void
    {
        $allocation = TellerAllocation::factory()->create([
            'current_quantity' => '0.0000',
            'daily_used_myr' => '0.0000',
        ]);

        // Ten additions of a 4-dp amount must land exactly on the decimal
        // total — a float cast inside increment/decrement would drift.
        for ($i = 0; $i < 10; $i++) {
            $allocation->add('0.0001');
            $allocation->addDailyUsed('1234.5678');
        }

        $fresh = $allocation->fresh();
        $this->assertEquals('0.0010', $fresh->current_quantity);
        $this->assertEquals('12345.6780', $fresh->daily_used_myr);
    }
}
