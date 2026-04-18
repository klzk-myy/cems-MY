<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Models\StockTransfer;
use App\Models\User;
use App\Services\AuditService;
use App\Services\MathService;
use App\Services\StockTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockTransferServiceTest extends TestCase
{
    use RefreshDatabase;

    protected StockTransferService $stockTransferService;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create([
            'username' => 'test_user',
            'email' => 'test@example.com',
            'password' => 'password',
            'role' => UserRole::Manager,
        ]);
        $this->stockTransferService = new StockTransferService(new MathService, new AuditService, $this->user);
    }

    public function test_create_request_validates_source_and_destination_branches(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Source and destination branches are required');

        $this->stockTransferService->createRequest([
            'source_branch_name' => '',
            'destination_branch_name' => '',
            'items' => [],
        ]);
    }

    public function test_create_request_validates_source_and_destination_not_same(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Source and destination branches cannot be the same');

        $this->stockTransferService->createRequest([
            'source_branch_name' => 'Branch A',
            'destination_branch_name' => 'Branch A',
            'items' => [],
        ]);
    }

    public function test_create_request_validates_items_not_empty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one item is required');

        $this->stockTransferService->createRequest([
            'source_branch_name' => 'Branch A',
            'destination_branch_name' => 'Branch B',
            'items' => [],
        ]);
    }

    public function test_create_request_validates_currency_code_required(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Currency code is required for each item');

        $this->stockTransferService->createRequest([
            'source_branch_name' => 'Branch A',
            'destination_branch_name' => 'Branch B',
            'items' => [
                ['quantity' => '1000', 'rate' => '4.5000'],
            ],
        ]);
    }

    public function test_create_request_validates_quantity_positive(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Quantity must be a positive number');

        $this->stockTransferService->createRequest([
            'source_branch_name' => 'Branch A',
            'destination_branch_name' => 'Branch B',
            'items' => [
                ['currency_code' => 'USD', 'quantity' => '-100', 'rate' => '4.5000'],
            ],
        ]);
    }

    public function test_create_request_validates_rate_positive(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Rate must be a positive number');

        $this->stockTransferService->createRequest([
            'source_branch_name' => 'Branch A',
            'destination_branch_name' => 'Branch B',
            'items' => [
                ['currency_code' => 'USD', 'quantity' => '1000', 'rate' => '-4.5000'],
            ],
        ]);
    }

    public function test_create_request_validates_currency_exists(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Currency XXX does not exist');

        $this->stockTransferService->createRequest([
            'source_branch_name' => 'Branch A',
            'destination_branch_name' => 'Branch B',
            'items' => [
                ['currency_code' => 'XXX', 'quantity' => '1000', 'rate' => '4.5000'],
            ],
        ]);
    }

    public function test_create_request_validates_total_value_matches_items(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Total value does not match sum of item values');

        $this->stockTransferService->createRequest([
            'source_branch_name' => 'Branch A',
            'destination_branch_name' => 'Branch B',
            'items' => [
                ['currency_code' => 'USD', 'quantity' => '1000', 'rate' => '4.5000'],
            ],
            'total_value_myr' => '5000.00', // Should be 4500.00
        ]);
    }

    public function test_create_request_succeeds_with_valid_data(): void
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

    public function test_create_request_calculates_total_value_correctly(): void
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
}
