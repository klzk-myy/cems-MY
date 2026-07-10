<?php

namespace Tests\Unit;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Models\Currency;
use App\Models\CurrencyPosition;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Branch\TillBalanceManager;
use App\Services\Transaction\TransactionReversalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransactionReversalServiceTest extends TestCase
{
    use RefreshDatabase;

    protected TransactionReversalService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTestBranch();
        $this->service = app(TransactionReversalService::class);
    }

    #[Test]
    public function can_reverse_completed_transaction_within_window(): void
    {
        $transaction = Transaction::factory()->create([
            'status' => TransactionStatus::Completed,
            'created_at' => now(),
        ]);

        $this->assertTrue($this->service->canReverse($transaction));
    }

    #[Test]
    public function cannot_reverse_non_completed_transaction(): void
    {
        $transaction = Transaction::factory()->create([
            'status' => TransactionStatus::PendingApproval,
        ]);

        $this->assertFalse($this->service->canReverse($transaction));
    }

    #[Test]
    public function cannot_reverse_already_reversed_transaction(): void
    {
        $transaction = Transaction::factory()->create([
            'status' => TransactionStatus::Reversed,
        ]);

        $this->assertFalse($this->service->canReverse($transaction));
    }

    #[Test]
    public function cannot_reverse_refund_transaction(): void
    {
        $transaction = Transaction::factory()->create([
            'status' => TransactionStatus::Completed,
            'is_refund' => true,
        ]);

        $this->assertFalse($this->service->canReverse($transaction));
    }

    #[Test]
    public function is_within_cancellation_window(): void
    {
        $transaction = Transaction::factory()->create([
            'created_at' => now()->subHours(12),
        ]);

        $this->assertTrue($this->service->isWithinCancellationWindow($transaction));
    }

    #[Test]
    public function is_outside_cancellation_window(): void
    {
        $transaction = Transaction::factory()->create([
            'created_at' => now()->subHours(25),
        ]);

        $this->assertFalse($this->service->isWithinCancellationWindow($transaction));
    }

    #[Test]
    public function manager_can_reverse_any_transaction(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $transaction = Transaction::factory()->create();

        $this->assertTrue($this->service->canUserReverse($manager, $transaction));
    }

    #[Test]
    public function teller_can_reverse_own_transaction(): void
    {
        $teller = User::factory()->create(['role' => UserRole::Teller]);
        $transaction = Transaction::factory()->create(['user_id' => $teller->id]);

        $this->assertTrue($this->service->canUserReverse($teller, $transaction));
    }

    #[Test]
    public function teller_cannot_reverse_other_user_transaction(): void
    {
        $teller = User::factory()->create(['role' => UserRole::Teller]);
        $otherTeller = User::factory()->create(['role' => UserRole::Teller]);
        $transaction = Transaction::factory()->create(['user_id' => $otherTeller->id]);

        $this->assertFalse($this->service->canUserReverse($teller, $transaction));
    }

    #[Test]
    public function create_refund_transaction(): void
    {
        $original = Transaction::factory()->create([
            'type' => TransactionType::Buy,
            'amount_foreign' => '100.00',
            'rate' => '4.50',
        ]);

        $refund = $this->service->createRefundTransaction($original, User::factory()->create()->id);

        $this->assertEquals(TransactionType::Sell, $refund->type);
        $this->assertEquals($original->amount_foreign, $refund->amount_foreign);
        $this->assertEquals($original->id, $refund->original_transaction_id);
        $this->assertTrue($refund->is_refund);
    }

    #[Test]
    public function reverse_positions_updates_currency_position(): void
    {
        $currencyCode = 'USD';
        $tillId = 'TEST-TILL-'.uniqid();
        $branch = $this->createTestBranch();

        CurrencyPosition::factory()->create([
            'currency_code' => $currencyCode,
            'branch_id' => $branch->id,
            'till_id' => $tillId,
            'balance' => '5000.00',
            'avg_cost_rate' => '4.50',
            'last_valuation_rate' => '4.50',
        ]);

        $transaction = Transaction::factory()->make([
            'id' => 99901,
            'currency_code' => $currencyCode,
            'branch_id' => $branch->id,
            'till_id' => $tillId,
            'type' => TransactionType::Buy,
            'amount_foreign' => '1000.00',
            'rate' => '4.50',
            'status' => TransactionStatus::Completed,
        ]);

        $this->service->reversePositions($transaction);

        $position = CurrencyPosition::where('currency_code', $currencyCode)
            ->where('branch_id', $branch->id)
            ->first();

        $this->assertEquals('4000.0000', $position->balance);
    }

    #[Test]
    public function reverse_positions_handles_nonexistent_position(): void
    {
        $transaction = Transaction::factory()->make([
            'id' => 99904,
            'currency_code' => 'XYZ',
            'branch_id' => 99999,
            'till_id' => 'NONEXISTENT-TILL',
            'type' => TransactionType::Sell,
            'amount_foreign' => '100.00',
            'rate' => '4.50',
            'status' => TransactionStatus::Completed,
        ]);

        $this->service->reversePositions($transaction);

        $position = CurrencyPosition::where('currency_code', 'XYZ')
            ->where('branch_id', 'NONEXISTENT-BRANCH')
            ->first();

        $this->assertNull($position);
    }

    #[Test]
    public function get_cancellation_window_hours(): void
    {
        $hours = $this->service->getCancellationWindowHours();

        $this->assertIsInt($hours);
        $this->assertGreaterThan(0, $hours);
    }

    #[Test]
    public function reversing_sell_transaction_restores_till_balance(): void
    {
        $branch = $this->createTestBranch();
        $till = $this->createTestCounter(['branch_id' => $branch->id]);
        $user = User::factory()->create();
        $manager = app(TillBalanceManager::class);
        $currencyCode = 'USD';
        $amountForeign = '500.00';
        $amountLocal = '2250.00';
        $rate = '4.50';

        Currency::factory()->create(['code' => $currencyCode]);

        // Simulate the till state after the original Sell transaction.
        $foreignBalance = $manager->openBalance($till, $currencyCode, $user->id);
        $manager->adjustBalance($foreignBalance, 'sell_total_foreign', $amountForeign, 'add');

        $myrBalance = $manager->openBalance($till, 'MYR', $user->id);
        $manager->adjustBalance($myrBalance, 'transaction_total', $amountLocal, 'add');

        $transaction = Transaction::factory()->create([
            'branch_id' => $branch->id,
            'till_id' => $till->code,
            'type' => TransactionType::Sell,
            'currency_code' => $currencyCode,
            'amount_foreign' => $amountForeign,
            'amount_local' => $amountLocal,
            'rate' => $rate,
            'status' => TransactionStatus::Completed,
            'created_at' => now(),
        ]);

        $this->service->reverse($transaction, $user, 'Regression test reversal');

        $foreignBalance->refresh();
        $myrBalance->refresh();

        $this->assertEquals('0.0000', (string) $foreignBalance->sell_total_foreign);
        $this->assertEquals('500.0000', (string) $foreignBalance->foreign_total);
        $this->assertEquals('0.0000', (string) $myrBalance->transaction_total);
    }
}
