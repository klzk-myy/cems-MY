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
        $counterService = new CounterService(
            $tellerAllocationService,
            new ThresholdService
        );

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
    public function open_session_creates_till_balance_with_currency_code(): void
    {
        $currency = Currency::factory()->create(['code' => 'EUR', 'is_active' => true]);
        $branch = Branch::factory()->create();
        $counter = Counter::factory()->create(['branch_id' => $branch->id]);
        $teller = User::factory()->create(['role' => UserRole::Teller, 'branch_id' => $branch->id]);

        $service = new CounterService(
            resolve(TellerAllocationService::class),
            new ThresholdService
        );

        // Pass currency code (string) as identifier
        $session = $service->openSession($counter, $teller, [
            ['currency_id' => $currency->code, 'amount' => '1000.00'],
        ]);

        $this->assertDatabaseHas('till_balances', [
            'till_id' => (string) $counter->id,
            'currency_code' => 'EUR',
            'opening_balance' => '1000.00',
        ]);
    }
}
