<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Exceptions\Domain\TransactionValidationException;
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

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create([
            'username' => 'test_user',
            'email' => 'test@example.com',
            'password' => 'password',
            'role' => UserRole::Manager,
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
    public function create_request_validates_source_and_destination_not_same(): void
    {
        $this->assertValidationError('Source and destination branches cannot be the same', [
            'source_branch_name' => 'Branch A',
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
     * ->field (the message stays the generic "Transaction validation failed"),
     * so the rule text is asserted on the field rather than the message.
     *
     * @param  array<string, mixed>  $payload
     */
    private function assertValidationError(string $expectedDetail, array $payload): void
    {
        try {
            $this->stockTransferService->createRequest($payload);
            $this->fail('Expected TransactionValidationException');
        } catch (TransactionValidationException $e) {
            $this->assertStringContainsString($expectedDetail, (string) $e->field);
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

    #[Test]
    public function full_workflow_approve_dispatch_complete_succeeds(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);
        $service = new StockTransferService(new MathService, new AuditService, $admin);

        // Dispatch decrements the SOURCE branch position, so the source must
        // actually hold the transferred quantity. positionBranchKey() maps the
        // free-text name to a position key (no Branch row is seeded here, so
        // the raw identifier is used).
        CurrencyPosition::create([
            'branch_id' => 'Branch A',
            'currency_code' => 'USD',
            'quantity' => '1000',
        ]);

        $transfer = $service->createRequest([
            'source_branch_name' => 'Branch A',
            'destination_branch_name' => 'Branch B',
            'items' => [
                ['currency_code' => 'USD', 'quantity' => '1000', 'rate' => '4.5000'],
            ],
        ]);

        // Regression: these steps compared the enum status against ->value
        // strings, so they always threw and the workflow could never progress.
        $service->approveByBranchManager($transfer);
        $this->assertTrue($transfer->fresh()->canApproveHq());

        $service->approveByHQ($transfer);
        $this->assertTrue($transfer->fresh()->canDispatch());

        $service->dispatch($transfer);
        $this->assertTrue($transfer->fresh()->canReceive());

        // Complete directly from in-transit (the enum fix makes this reachable).
        $service->complete($transfer->fresh());
        $this->assertTrue($transfer->fresh()->isCompleted());
    }

    #[Test]
    public function partial_receipt_then_complete_succeeds(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);
        $service = new StockTransferService(new MathService, new AuditService, $admin);

        // Same source-position fixture as full_workflow_approve_dispatch_complete_succeeds.
        CurrencyPosition::create([
            'branch_id' => 'Branch A',
            'currency_code' => 'USD',
            'quantity' => '1000',
        ]);

        $transfer = $service->createRequest([
            'source_branch_name' => 'Branch A',
            'destination_branch_name' => 'Branch B',
            'items' => [
                ['currency_code' => 'USD', 'quantity' => '1000', 'rate' => '4.5000'],
            ],
        ]);

        $service->approveByBranchManager($transfer);
        $service->approveByHQ($transfer->fresh());
        $service->dispatch($transfer->fresh());

        // Partial receipt leaves the transfer in PartiallyReceived, which is
        // a valid state from which complete() may finalise it.
        $service->receiveItems($transfer->fresh(), [
            ['id' => $transfer->items->first()->id, 'quantity_received' => '600'],
        ]);

        $partiallyReceived = $transfer->fresh();
        $this->assertTrue($partiallyReceived->canComplete());

        $service->complete($partiallyReceived);
        $this->assertTrue($transfer->fresh()->isCompleted());
    }
}
