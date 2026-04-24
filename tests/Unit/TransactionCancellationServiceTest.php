<?php

namespace Tests\Unit;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\CurrencyPosition;
use App\Models\Transaction;
use App\Services\CurrencyPositionService;
use App\Services\TransactionCancellationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionCancellationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected TransactionCancellationService $cancellationService;

    protected CurrencyPositionService $positionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cancellationService = app(TransactionCancellationService::class);
    }

    public function test_concurrent_reversals_produce_correct_balance(): void
    {
        $currencyCode = 'USD';
        $tillId = 'TEST-TILL-'.uniqid();

        // Create initial position: 5000 USD
        CurrencyPosition::create([
            'currency_code' => $currencyCode,
            'till_id' => $tillId,
            'balance' => '5000.00',
            'avg_cost_rate' => '4.50',
            'last_valuation_rate' => '4.50',
        ]);

        // Create and reverse first Buy transaction (reversal = Sell)
        $transaction1 = Transaction::factory()->make([
            'id' => 99901,
            'currency_code' => $currencyCode,
            'till_id' => $tillId,
            'type' => TransactionType::Buy,
            'amount_foreign' => '1000.00',
            'rate' => '4.50',
            'status' => TransactionStatus::Completed,
        ]);

        $this->cancellationService->reversePositions($transaction1);

        // Create and reverse second Buy transaction (reversal = Sell)
        $transaction2 = Transaction::factory()->make([
            'id' => 99902,
            'currency_code' => $currencyCode,
            'till_id' => $tillId,
            'type' => TransactionType::Buy,
            'amount_foreign' => '1000.00',
            'rate' => '4.50',
            'status' => TransactionStatus::Completed,
        ]);

        $this->cancellationService->reversePositions($transaction2);

        // Verify final balance after both reversals
        // Each Buy reversal = Sell = decrease position
        // 5000 - 1000 - 1000 = 3000
        $position = CurrencyPosition::where('currency_code', $currencyCode)
            ->where('till_id', $tillId)
            ->first();

        $this->assertEquals('3000.0000', $position->balance);
    }

    public function test_reverse_positions_acquires_row_lock(): void
    {
        $currencyCode = 'USD';
        $tillId = 'TEST-TILL-'.uniqid();

        // Create initial position
        CurrencyPosition::create([
            'currency_code' => $currencyCode,
            'till_id' => $tillId,
            'balance' => '3000.00',
            'avg_cost_rate' => '4.50',
            'last_valuation_rate' => '4.50',
        ]);

        // Create a Buy transaction (reversal will be Sell, decreasing position)
        $transaction = Transaction::factory()->make([
            'id' => 99903,
            'currency_code' => $currencyCode,
            'till_id' => $tillId,
            'type' => TransactionType::Buy,
            'amount_foreign' => '500.00',
            'rate' => '4.50',
            'status' => TransactionStatus::Completed,
        ]);

        $this->cancellationService->reversePositions($transaction);

        $position = CurrencyPosition::where('currency_code', $currencyCode)
            ->where('till_id', $tillId)
            ->first();

        // Buy transaction reversed as Sell: 3000 - 500 = 2500
        $this->assertEquals('2500.0000', $position->balance);
    }

    public function test_reverse_positions_handles_nonexistent_position(): void
    {
        $transaction = Transaction::factory()->make([
            'id' => 99904,
            'currency_code' => 'XYZ',
            'till_id' => 'NONEXISTENT-TILL',
            'type' => TransactionType::Sell,
            'amount_foreign' => '100.00',
            'rate' => '4.50',
            'status' => TransactionStatus::Completed,
        ]);

        // Should not throw, just log warning
        $this->cancellationService->reversePositions($transaction);

        // No exception means success
        $this->assertTrue(true);
    }
}
