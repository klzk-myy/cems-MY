<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Exceptions\Domain\TransactionApprovalException;
use App\Exceptions\Domain\TransactionValidationException;
use App\Models\Branch;
use App\Models\CurrencyPosition;
use App\Models\StockTransfer;
use App\Models\User;
use App\Services\AuditService;
use App\Services\System\MathService;
use App\Services\Transaction\StockTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StockTransferServiceTest extends TestCase
{
    use RefreshDatabase;

    protected StockTransferService $stockTransferService;

    protected User $user;

    protected Branch $branchA;

    protected Branch $branchB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->branchA = Branch::factory()->create(['name' => 'Branch A', 'code' => 'BRA']);
        $this->branchB = Branch::factory()->create(['name' => 'Branch B', 'code' => 'BRB']);
        $this->user = User::factory()->create([
            'username' => 'test_user',
            'email' => 'test@example.com',
            'password' => 'password',
            'role' => UserRole::Manager,
            'branch_id' => $this->branchA->id,
        ]);
        $this->stockTransferService = new StockTransferService(new MathService, new AuditService, $this->user);
    }

    #[Test]
    public function create_request_validates_source_and_destination_branches(): void
    {
        $this->assertValidationError('Source and destination branches are required', [
            'source_branch_name' => '',
            'destination_branch_name' => '',
            'items' => [],
        ]);
    }

    #[Test]
    public function create_request_validates_non_manager_cannot_within_branch_transfer(): void
    {
        // Within-branch transfers are allowed for managers/admins only.
        // A teller attempting a same-branch transfer should be rejected.
        $teller = User::factory()->create([
            'role' => UserRole::Teller,
            'branch_id' => $this->branchA->id,
        ]);
        $service = new StockTransferService(new MathService, new AuditService, $teller);

        $this->assertValidationError(
            'within-branch stock transfers',
            [
                'source_branch_name' => 'Branch A',
                'destination_branch_name' => 'Branch A',
                'items' => [],
            ],
            $service
        );
    }

    #[Test]
    public function create_request_validates_maker_sources_from_own_branch(): void
    {
        // Maker rule: a non-admin manager can only create transfers sourcing
        // stock from their own branch.
        $this->assertValidationError('your own branch', [
            'source_branch_name' => 'Branch B',
            'destination_branch_name' => 'Branch A',
            'items' => [],
        ]);
    }

    #[Test]
    public function create_request_validates_items_not_empty(): void
    {
        $this->assertValidationError('At least one item is required', [
            'source_branch_name' => 'Branch A',
            'destination_branch_name' => 'Branch B',
            'items' => [],
        ]);
    }

    #[Test]
    public function create_request_validates_currency_code_required(): void
    {
        $this->assertValidationError('Currency code is required for each item', [
            'source_branch_name' => 'Branch A',
            'destination_branch_name' => 'Branch B',
            'items' => [
                ['quantity' => '1000', 'rate' => '4.5000'],
            ],
        ]);
    }

    #[Test]
    public function create_request_validates_quantity_positive(): void
    {
        $this->assertValidationError('Quantity must be a positive number', [
            'source_branch_name' => 'Branch A',
            'destination_branch_name' => 'Branch B',
            'items' => [
                ['currency_code' => 'USD', 'quantity' => '-100', 'rate' => '4.5000'],
            ],
        ]);
    }

    #[Test]
    public function create_request_validates_rate_positive(): void
    {
        $this->assertValidationError('Rate must be a positive number', [
            'source_branch_name' => 'Branch A',
            'destination_branch_name' => 'Branch B',
            'items' => [
                ['currency_code' => 'USD', 'quantity' => '1000', 'rate' => '-4.5000'],
            ],
        ]);
    }

    #[Test]
    public function create_request_validates_currency_exists(): void
    {
        $this->assertValidationError('Currency XXX does not exist', [
            'source_branch_name' => 'Branch A',
            'destination_branch_name' => 'Branch B',
            'items' => [
                ['currency_code' => 'XXX', 'quantity' => '1000', 'rate' => '4.5000'],
            ],
        ]);
    }

    #[Test]
    public function create_request_validates_total_value_matches_items(): void
    {
        $this->assertValidationError('Total value does not match sum of item values', [
            'source_branch_name' => 'Branch A',
            'destination_branch_name' => 'Branch B',
            'items' => [
                ['currency_code' => 'USD', 'quantity' => '1000', 'rate' => '4.5000'],
            ],
            'total_value_myr' => '5000.00', // Should be 4500.00
        ]);
    }

    /**
     * Assert createRequest() rejects the payload with the given rule detail.
     *
     * TransactionValidationException carries the human-readable rule detail in
     * getMessage(); ->field holds the failing field name when set.
     *
     * @param  array<string, mixed>  $payload
     */
    private function assertValidationError(string $expectedDetail, array $payload, ?StockTransferService $service = null): void
    {
        $service = $service ?? $this->stockTransferService;
        try {
            $service->createRequest($payload);
            $this->fail('Expected TransactionValidationException');
        } catch (TransactionValidationException $e) {
            $this->assertStringContainsString($expectedDetail, $e->getMessage());
        }
    }

    #[Test]
    public function create_request_succeeds_with_valid_data(): void
    {
        $transfer = $this->stockTransferService->createRequest([
            'source_branch_name' => 'Branch A',
            'destination_branch_name' => 'Branch B',
            'items' => [
                ['currency_code' => 'USD', 'quantity' => '1000', 'rate' => '4.5000'],
            ],
        ]);

        $this->assertInstanceOf(StockTransfer::class, $transfer);
        $this->assertEquals('Branch A', $transfer->source_branch_name);
        $this->assertEquals('Branch B', $transfer->destination_branch_name);
        $this->assertEquals('4500.00', $transfer->total_value_myr);
        $this->assertCount(1, $transfer->items);
    }

    #[Test]
    public function create_request_calculates_total_value_correctly(): void
    {
        $transfer = $this->stockTransferService->createRequest([
            'source_branch_name' => 'Branch A',
            'destination_branch_name' => 'Branch B',
            'items' => [
                ['currency_code' => 'USD', 'quantity' => '1000', 'rate' => '4.5000'],
                ['currency_code' => 'EUR', 'quantity' => '500', 'rate' => '4.8000'],
            ],
        ]);

        $this->assertEquals('6900.00', $transfer->total_value_myr); // 4500 + 2400
    }

    /**
     * Build the maker (source) and taker (destination) service instances plus
     * the source position fixture, and return a fresh Requested transfer.
     *
     * @return array{0: StockTransferService, 1: StockTransferService, 2: StockTransfer}
     */
    private function makeMakerTakerContext(): array
    {
        $managerA = $this->user;
        $managerB = User::factory()->create([
            'role' => UserRole::Manager,
            'branch_id' => $this->branchB->id,
        ]);

        // Dispatch decrements the SOURCE branch position, so the source must
        // actually hold the transferred quantity. positionBranchKey() maps the
        // free-text name to branches.id.
        CurrencyPosition::create([
            'branch_id' => (string) $this->branchA->id,
            'currency_code' => 'USD',
            'quantity' => '1000',
        ]);

        $maker = new StockTransferService(new MathService, new AuditService, $managerA);
        $taker = new StockTransferService(new MathService, new AuditService, $managerB);

        $transfer = $maker->createRequest([
            'source_branch_name' => 'Branch A',
            'destination_branch_name' => 'Branch B',
            'items' => [
                ['currency_code' => 'USD', 'quantity' => '1000', 'rate' => '4.5000'],
            ],
        ]);

        return [$maker, $taker, $transfer];
    }

    #[Test]
    public function maker_cannot_approve_own_transfer(): void
    {
        [$maker, $taker, $transfer] = $this->makeMakerTakerContext();

        try {
            $maker->approveByBranchManager($transfer);
            $this->fail('Expected TransactionApprovalException');
        } catch (TransactionApprovalException $e) {
            $this->assertStringContainsString('own transfer', $e->getMessage());
        }
    }

    #[Test]
    public function maker_cannot_approve_transfer_for_other_destination(): void
    {
        [$maker, $taker, $transfer] = $this->makeMakerTakerContext();

        // A different source-branch manager (not the requester) still cannot
        // approve — approval belongs to the destination branch.
        $managerA2 = User::factory()->create([
            'role' => UserRole::Manager,
            'branch_id' => $this->branchA->id,
        ]);
        $otherMaker = new StockTransferService(new MathService, new AuditService, $managerA2);

        try {
            $otherMaker->approveByBranchManager($transfer);
            $this->fail('Expected TransactionApprovalException');
        } catch (TransactionApprovalException $e) {
            $this->assertStringContainsString('destination branch', $e->getMessage());
        }
    }

    #[Test]
    public function full_workflow_approve_dispatch_complete_succeeds(): void
    {
        [$maker, $taker, $transfer] = $this->makeMakerTakerContext();

        // Maker/taker: the destination branch manager approves, then the
        // source branch dispatches — no HQ step.
        $taker->approveByBranchManager($transfer);
        $this->assertTrue($transfer->fresh()->canDispatch());

        $maker->dispatch($transfer->fresh());
        $this->assertTrue($transfer->fresh()->canReceive());

        // Complete directly from in-transit (the enum fix makes this reachable).
        $taker->complete($transfer->fresh());
        $this->assertTrue($transfer->fresh()->isCompleted());
    }

    #[Test]
    public function partial_receipt_then_complete_succeeds(): void
    {
        [$maker, $taker, $transfer] = $this->makeMakerTakerContext();

        $taker->approveByBranchManager($transfer);
        $maker->dispatch($transfer->fresh());

        // Partial receipt leaves the transfer in PartiallyReceived, which is
        // a valid state from which complete() may finalise it.
        $taker->receiveItems($transfer->fresh(), [
            ['id' => $transfer->items->first()->id, 'quantity_received' => '600'],
        ]);

        $partiallyReceived = $transfer->fresh();
        $this->assertTrue($partiallyReceived->canComplete());

        $taker->complete($partiallyReceived);
        $this->assertTrue($transfer->fresh()->isCompleted());
    }

    #[Test]
    public function within_branch_transfer_succeeds_for_manager(): void
    {
        // Managers can create within-branch transfers (source === destination)
        // for teller stock/cash reallocation.
        $transfer = $this->stockTransferService->createRequest([
            'source_branch_name' => 'Branch A',
            'destination_branch_name' => 'Branch A',
            'items' => [
                ['currency_code' => 'USD', 'quantity' => '1000', 'rate' => '4.5000'],
            ],
        ]);

        $this->assertNotNull($transfer->id);
        $this->assertEquals('Branch A', $transfer->source_branch_name);
        $this->assertEquals('Branch A', $transfer->destination_branch_name);
    }

    #[Test]
    public function within_branch_transfer_allows_self_approval(): void
    {
        // Within-branch transfers skip the maker/taker segregation check
        // since the same branch manager creates and approves.
        $transfer = $this->stockTransferService->createRequest([
            'source_branch_name' => 'Branch A',
            'destination_branch_name' => 'Branch A',
            'items' => [
                ['currency_code' => 'USD', 'quantity' => '1000', 'rate' => '4.5000'],
            ],
        ]);

        // The same manager who created the transfer can approve it
        $this->stockTransferService->approveByBranchManager($transfer);
        $this->assertTrue($transfer->fresh()->canDispatch());
    }
}
