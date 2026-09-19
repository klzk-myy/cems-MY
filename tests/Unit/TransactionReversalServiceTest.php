<?php

namespace Tests\Unit;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Models\Currency;
use App\Models\CurrencyPosition;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\CurrencyPositionService;
use App\Services\Branch\TillBalanceManager;
use App\Services\Transaction\TransactionReversalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
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
    public function reverse_positions_restores_average_cost_for_buy_reversal(): void
    {
        $branch = $this->createTestBranch();

        // Position after a Buy of 1000 @ 4.60 layered onto 1000 @ 4.40:
        // qty 2000, avg 4.50. Reversing that Buy must restore qty 1000 @ 4.40.
        CurrencyPosition::factory()->create([
            'currency_code' => 'USD',
            'branch_id' => $branch->id,
            'balance' => '2000.0000',
            'avg_cost_rate' => '4.500000',
            'last_valuation_rate' => '4.50',
        ]);

        $buy = Transaction::factory()->make([
            'id' => 99911,
            'currency_code' => 'USD',
            'branch_id' => $branch->id,
            'type' => TransactionType::Buy,
            'quantity' => '1000.00',
            'rate' => '4.60',
            'status' => TransactionStatus::Completed,
        ]);

        $this->service->reversePositions($buy);

        $position = CurrencyPosition::where('currency_code', 'USD')
            ->where('branch_id', $branch->id)
            ->first();

        $this->assertEquals('1000.0000', $position->balance);
        $this->assertEquals('4.4000', bcadd($position->average_cost, '0', 4));
    }

    #[Test]
    public function reversal_restores_snapshot_average_cost_after_intervening_buys(): void
    {
        $branch = $this->createTestBranch();
        $service = app(CurrencyPositionService::class);

        // 1. Establish initial position 1000 @ 4.40.
        $service->updatePosition('USD', '1000.00', '4.40', 'Buy', (string) $branch->id);

        // 2. Target Buy of 1000 @ 4.60 → qty 2000 @ 4.50; snapshot (1000, 4.40) recorded.
        $target = Transaction::factory()->create([
            'currency_code' => 'USD',
            'branch_id' => $branch->id,
            'type' => TransactionType::Buy,
            'quantity' => '1000.00',
            'rate' => '4.60',
            'status' => TransactionStatus::Completed,
        ]);
        $service->updatePosition('USD', '1000.00', '4.60', 'Buy', (string) $branch->id, $target);

        $target->refresh();
        $this->assertEquals('1000.0000', (string) $target->prev_quantity);
        $this->assertNotNull($target->prev_average_cost);

        // 3. Reversing the target Buy must restore exactly the snapshot (1000, 4.40).
        $this->service->reversePositions($target);

        $position = CurrencyPosition::where('currency_code', 'USD')
            ->where('branch_id', $branch->id)
            ->first();

        $this->assertEquals('1000.0000', $position->balance);
        $this->assertEquals('4.4000', bcadd($position->average_cost, '0', 4));

        // Derived columns must track the restored quantity/cost — stale
        // total_cost/current_value described the pre-reversal position.
        // current_rate stays at the last market rate (4.60), so the restored
        // 1000 units carry 200 unrealized gain over the 4.40 cost basis.
        $this->assertEquals('4400.0000', $position->total_cost);
        $this->assertEquals('4600.0000', $position->current_value);
        $this->assertEquals('200.0000', $position->unrealized_gain_loss);
    }

    #[Test]
    public function reversing_buy_after_position_partially_depleted_does_not_throw(): void
    {
        $branch = $this->createTestBranch();

        // Only 400 left, but the original Buy was for 1000 — the guard-based
        // path used to abort the whole cancellation here.
        CurrencyPosition::factory()->create([
            'currency_code' => 'USD',
            'branch_id' => $branch->id,
            'balance' => '400.0000',
            'avg_cost_rate' => '4.50',
            'last_valuation_rate' => '4.50',
        ]);

        $buy = Transaction::factory()->make([
            'id' => 99912,
            'currency_code' => 'USD',
            'branch_id' => $branch->id,
            'type' => TransactionType::Buy,
            'quantity' => '1000.00',
            'rate' => '4.50',
            'status' => TransactionStatus::Completed,
        ]);

        $this->service->reversePositions($buy);

        $position = CurrencyPosition::where('currency_code', 'USD')
            ->where('branch_id', $branch->id)
            ->first();

        $this->assertEquals('-600.0000', $position->balance);
    }

    #[Test]
    public function reverse_positions_keeps_average_cost_for_sell_reversal(): void
    {
        $branch = $this->createTestBranch();

        CurrencyPosition::factory()->create([
            'currency_code' => 'USD',
            'branch_id' => $branch->id,
            'balance' => '500.0000',
            'avg_cost_rate' => '4.45',
            'last_valuation_rate' => '4.50',
        ]);

        $sell = Transaction::factory()->make([
            'id' => 99913,
            'currency_code' => 'USD',
            'branch_id' => $branch->id,
            'type' => TransactionType::Sell,
            'quantity' => '200.00',
            'rate' => '4.70',
            'status' => TransactionStatus::Completed,
        ]);

        $this->service->reversePositions($sell);

        $position = CurrencyPosition::where('currency_code', 'USD')
            ->where('branch_id', $branch->id)
            ->first();

        $this->assertEquals('700.0000', $position->balance);
        $this->assertEquals('4.4500', bcadd($position->average_cost, '0', 4));
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
    public function compliance_officer_can_reverse_any_transaction(): void
    {
        $compliance = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $transaction = Transaction::factory()->create();

        $this->assertTrue($this->service->canUserReverse($compliance, $transaction));
    }

    #[Test]
    public function manager_cannot_reverse_transactions(): void
    {
        // Reversal of completed transactions is compliance-only.
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $transaction = Transaction::factory()->create();

        $this->assertFalse($this->service->canUserReverse($manager, $transaction));
    }

    #[Test]
    public function teller_cannot_reverse_own_transaction(): void
    {
        $teller = User::factory()->create(['role' => UserRole::Teller]);
        $transaction = Transaction::factory()->create(['user_id' => $teller->id]);

        $this->assertFalse($this->service->canUserReverse($teller, $transaction));
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
            'quantity' => '100.00',
            'rate' => '4.50',
        ]);

        $refund = $this->service->createRefundTransaction($original, User::factory()->create()->id);

        $this->assertEquals(TransactionType::Sell, $refund->type);
        $this->assertEquals($original->quantity, $refund->quantity);
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
            'quantity' => '1000.00',
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
    public function reverse_positions_throws_on_nonexistent_position(): void
    {
        $transaction = Transaction::factory()->make([
            'id' => 99904,
            'currency_code' => 'XYZ',
            'branch_id' => 99999,
            'till_id' => 'NONEXISTENT-TILL',
            'type' => TransactionType::Sell,
            'quantity' => '100.00',
            'rate' => '4.50',
            'status' => TransactionStatus::Completed,
        ]);

        $this->expectException(RuntimeException::class);

        $this->service->reversePositions($transaction);
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
        $quantity = '500.00';
        $amountMyr = '2250.00';
        $rate = '4.50';

        Currency::factory()->create(['code' => $currencyCode]);

        // The Sell reversal path refunds foreign currency into this position.
        CurrencyPosition::factory()->create([
            'currency_code' => $currencyCode,
            'branch_id' => $branch->id,
            'balance' => '1000.00',
            'avg_cost_rate' => $rate,
            'last_valuation_rate' => $rate,
        ]);

        // Simulate the till state after the original Sell transaction.
        $foreignBalance = $manager->openBalance($till, $currencyCode, $user->id);
        $manager->adjustBalance($foreignBalance, 'sell_quantity', $quantity, 'add');

        $myrBalance = $manager->openBalance($till, 'MYR', $user->id);
        $manager->adjustBalance($myrBalance, 'transaction_total_myr', $amountMyr, 'add');

        $transaction = Transaction::factory()->create([
            'branch_id' => $branch->id,
            'till_id' => $till->code,
            'type' => TransactionType::Sell,
            'currency_code' => $currencyCode,
            'quantity' => $quantity,
            'amount_myr' => $amountMyr,
            'rate' => $rate,
            'status' => TransactionStatus::Completed,
            'created_at' => now(),
        ]);

        $this->service->reverse($transaction, $user, 'Regression test reversal');

        $foreignBalance->refresh();
        $myrBalance->refresh();

        $this->assertEquals('0.0000', (string) $foreignBalance->sell_quantity);
        $this->assertEquals('500.0000', (string) $foreignBalance->total_quantity);
        $this->assertEquals('0.0000', (string) $myrBalance->transaction_total_myr);
    }
}
