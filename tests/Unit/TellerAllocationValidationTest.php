<?php

namespace Tests\Unit;

use App\Models\TellerAllocation;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Branch\BranchPoolService;
use App\Services\Branch\TellerAllocationService;
use App\Services\Branch\TillService;
use App\Services\System\CacheOptimizationService;
use App\Services\System\MathService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TellerAllocationValidationTest extends TestCase
{
    use DatabaseTransactions;

    protected TellerAllocationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new TellerAllocationService(
            new BranchPoolService(new AuditService(new CacheOptimizationService), new MathService),
            new MathService,
            app(AuditService::class), app(TillService::class));
    }

    private function createActiveAllocation(User $teller, array $overrides = []): TellerAllocation
    {
        return TellerAllocation::factory()->active()->create(array_merge([
            'user_id' => $teller->id,
            'currency_code' => 'USD',
            'current_balance' => '1000.0000',
            'allocated_amount' => '1000.0000',
            'daily_limit_myr' => '50000.0000',
            'daily_used_myr' => '0.0000',
            'session_date' => now()->toDateString(),
        ], $overrides));
    }

    #[Test]
    public function buy_is_not_blocked_by_foreign_float_unit_mismatch(): void
    {
        $teller = User::factory()->create();
        $this->createActiveAllocation($teller); // Small foreign float, generous MYR daily limit

        // Buying USD worth RM 21,000 exceeds the USD 1,000 float, but a Buy ADDS
        // to the float - it must not be rejected on a MYR-vs-foreign comparison.
        $result = $this->service->validateTransaction($teller, 'USD', '21000.0000', true);

        $this->assertTrue($result->valid);
    }

    #[Test]
    public function buy_exceeding_daily_limit_is_rejected(): void
    {
        $teller = User::factory()->create();
        $this->createActiveAllocation($teller, ['daily_limit_myr' => '5000.0000']);

        $result = $this->service->validateTransaction($teller, 'USD', '6000.0000', true);

        $this->assertFalse($result->valid);
        $this->assertSame('Daily limit exceeded', $result->reason);
    }

    #[Test]
    public function sell_is_checked_against_foreign_float(): void
    {
        $teller = User::factory()->create();
        $this->createActiveAllocation($teller, ['current_balance' => '1000.0000']);

        // Selling USD 2,000 - exceeds the USD 1,000 foreign float
        $result = $this->service->validateTransaction($teller, 'USD', '8400.0000', false, '2000.0000');

        $this->assertFalse($result->valid);
        $this->assertSame('No USD balance available to sell', $result->reason);
    }
}
