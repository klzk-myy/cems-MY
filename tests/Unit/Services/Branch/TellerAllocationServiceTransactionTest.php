<?php

namespace Tests\Unit\Services\Branch;

use App\Enums\TellerAllocationStatus;
use App\Enums\TransactionType;
use App\Models\Branch;
use App\Models\BranchPool;
use App\Models\Customer;
use App\Models\TellerAllocation;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Branch\BranchPoolService;
use App\Services\Branch\TellerAllocationService;
use App\Services\Branch\TillService;
use App\Services\System\MathService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TellerAllocationServiceTransactionTest extends TestCase
{
    use RefreshDatabase;

    protected TellerAllocationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TellerAllocationService(
            new BranchPoolService(new AuditService, new MathService),
            new MathService,
            app(AuditService::class), app(TillService::class));
    }

    private function activeAllocation(User $teller, Branch $branch, string $currencyCode, string $currentBalance, string $dailyLimitMyr = '50000.0000'): TellerAllocation
    {
        return TellerAllocation::factory()->create([
            'user_id' => $teller->id,
            'branch_id' => $branch->id,
            'currency_code' => $currencyCode,
            'status' => TellerAllocationStatus::Active,
            'allocated_amount' => $currentBalance,
            'current_balance' => $currentBalance,
            'daily_limit_myr' => $dailyLimitMyr,
            'daily_used_myr' => '0.0000',
            'session_date' => now()->toDateString(),
        ]);
    }

    private function transaction(User $user, Branch $branch, TransactionType $type, string $currencyCode, string $amountForeign, string $amountLocal): Transaction
    {
        return Transaction::factory()->create([
            'customer_id' => Customer::factory(),
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'type' => $type->value,
            'currency_code' => $currencyCode,
            'amount_foreign' => $amountForeign,
            'amount_local' => $amountLocal,
        ]);
    }

    #[Test]
    public function apply_buy_allocation_increases_foreign_balance_and_daily_used(): void
    {
        $branch = Branch::factory()->create();
        $teller = User::factory()->create(['role' => 'teller', 'branch_id' => $branch->id]);
        $allocation = $this->activeAllocation($teller, $branch, 'USD', '1000.0000');
        $transaction = $this->transaction($teller, $branch, TransactionType::Buy, 'USD', '100.0000', '450.0000');

        $this->service->applyTransactionAllocation($transaction, $allocation);

        $allocation->refresh();
        $this->assertEquals('1100.0000', (string) $allocation->current_balance);
        $this->assertEquals('450.0000', (string) $allocation->daily_used_myr);
    }

    #[Test]
    public function apply_sell_allocation_decreases_foreign_balance_and_daily_used(): void
    {
        $branch = Branch::factory()->create();
        $teller = User::factory()->create(['role' => 'teller', 'branch_id' => $branch->id]);
        $allocation = $this->activeAllocation($teller, $branch, 'USD', '1000.0000');
        $transaction = $this->transaction($teller, $branch, TransactionType::Sell, 'USD', '100.0000', '450.0000');

        $this->service->applyTransactionAllocation($transaction, $allocation);

        $allocation->refresh();
        $this->assertEquals('900.0000', (string) $allocation->current_balance);
        $this->assertEquals('450.0000', (string) $allocation->daily_used_myr);
    }

    #[Test]
    public function apply_allocation_resolves_active_allocation_when_none_provided(): void
    {
        $branch = Branch::factory()->create();
        $teller = User::factory()->create(['role' => 'teller', 'branch_id' => $branch->id]);
        $allocation = $this->activeAllocation($teller, $branch, 'USD', '1000.0000');
        $transaction = $this->transaction($teller, $branch, TransactionType::Buy, 'USD', '100.0000', '450.0000');

        $this->service->applyTransactionAllocation($transaction);

        $allocation->refresh();
        $this->assertEquals('1100.0000', (string) $allocation->current_balance);
    }

    #[Test]
    public function apply_allocation_short_circuits_when_no_active_allocation_exists(): void
    {
        $branch = Branch::factory()->create();
        $teller = User::factory()->create(['role' => 'teller', 'branch_id' => $branch->id]);
        $transaction = $this->transaction($teller, $branch, TransactionType::Buy, 'USD', '100.0000', '450.0000');

        $this->service->applyTransactionAllocation($transaction);

        $this->assertDatabaseCount('teller_allocations', 0);
    }

    #[Test]
    public function reverse_buy_allocation_deducts_foreign_balance_and_daily_used(): void
    {
        $branch = Branch::factory()->create();
        $teller = User::factory()->create(['role' => 'teller', 'branch_id' => $branch->id]);
        $allocation = $this->activeAllocation($teller, $branch, 'USD', '1000.0000');
        $transaction = $this->transaction($teller, $branch, TransactionType::Buy, 'USD', '100.0000', '450.0000');

        $this->service->applyTransactionAllocation($transaction, $allocation);
        $this->service->reverseTransactionAllocation($transaction);

        $allocation->refresh();
        $this->assertEquals('1000.0000', (string) $allocation->current_balance);
        $this->assertEquals('0.0000', (string) $allocation->daily_used_myr);
    }

    #[Test]
    public function reverse_sell_allocation_restores_foreign_balance_and_daily_used(): void
    {
        $branch = Branch::factory()->create();
        $teller = User::factory()->create(['role' => 'teller', 'branch_id' => $branch->id]);
        $allocation = $this->activeAllocation($teller, $branch, 'USD', '1000.0000');
        $transaction = $this->transaction($teller, $branch, TransactionType::Sell, 'USD', '100.0000', '450.0000');

        $this->service->applyTransactionAllocation($transaction, $allocation);
        $this->service->reverseTransactionAllocation($transaction);

        $allocation->refresh();
        $this->assertEquals('1000.0000', (string) $allocation->current_balance);
        $this->assertEquals('0.0000', (string) $allocation->daily_used_myr);
    }

    #[Test]
    public function apply_uses_pinned_allocation_over_a_richer_active_one(): void
    {
        $branch = Branch::factory()->create();
        $teller = User::factory()->create(['role' => 'teller', 'branch_id' => $branch->id]);

        // The allocation validated at creation time is pinned on the
        // transaction; a second, richer allocation for the same day/currency
        // must not divert the balance effect.
        $pinned = $this->activeAllocation($teller, $branch, 'USD', '500.0000');
        $richer = $this->activeAllocation($teller, $branch, 'USD', '9000.0000');

        $transaction = $this->transaction($teller, $branch, TransactionType::Sell, 'USD', '100.0000', '450.0000');
        $transaction->teller_allocation_id = $pinned->id;
        $transaction->save();

        $this->service->applyTransactionAllocation($transaction);

        $pinned->refresh();
        $richer->refresh();
        $this->assertEquals('400.0000', (string) $pinned->current_balance);
        $this->assertEquals('9000.0000', (string) $richer->current_balance);
    }

    #[Test]
    public function reverse_falls_back_to_active_allocation_when_pinned_is_inactive(): void
    {
        $branch = Branch::factory()->create();
        $teller = User::factory()->create(['role' => 'teller', 'branch_id' => $branch->id]);

        $returned = $this->activeAllocation($teller, $branch, 'USD', '500.0000');
        $returned->update(['status' => TellerAllocationStatus::Returned]);
        $active = $this->activeAllocation($teller, $branch, 'USD', '9000.0000');

        $transaction = $this->transaction($teller, $branch, TransactionType::Sell, 'USD', '100.0000', '450.0000');
        $transaction->teller_allocation_id = $returned->id;
        $transaction->save();

        // The pinned allocation was returned to the pool — reversal credits
        // the teller's current active allocation instead.
        $this->service->reverseTransactionAllocation($transaction);

        $active->refresh();
        $returned->refresh();
        $this->assertEquals('9100.0000', (string) $active->current_balance);
        $this->assertEquals('500.0000', (string) $returned->current_balance);
    }

    #[Test]
    public function reverse_allocation_short_circuits_for_non_teller_user(): void
    {
        $branch = Branch::factory()->create();
        $manager = User::factory()->create(['role' => 'manager', 'branch_id' => $branch->id]);
        $transaction = $this->transaction($manager, $branch, TransactionType::Buy, 'USD', '100.0000', '450.0000');

        $this->service->reverseTransactionAllocation($transaction);

        $this->assertDatabaseCount('teller_allocations', 0);
    }

    #[Test]
    public function sell_consumes_pool_earmark_so_stock_paid_out_leaves_allocated(): void
    {
        $branch = Branch::factory()->create();
        $teller = User::factory()->create(['role' => 'teller', 'branch_id' => $branch->id]);
        $allocation = $this->activeAllocation($teller, $branch, 'USD', '1000.0000');
        /** @var BranchPool $pool */
        $pool = BranchPool::factory()->for($branch)->create([
            'currency_code' => 'USD',
            'available_balance' => '9000.0000',
            'allocated_balance' => '1000.0000',
        ]);
        $transaction = $this->transaction($teller, $branch, TransactionType::Sell, 'USD', '100.0000', '450.0000');

        $this->service->applyTransactionAllocation($transaction, $allocation);

        $pool->refresh();
        $this->assertEquals('900.0000', (string) $pool->allocated_balance);
        $this->assertEquals('9000.0000', (string) $pool->available_balance);
    }

    #[Test]
    public function buy_grows_pool_earmark_for_stock_entering_custody(): void
    {
        $branch = Branch::factory()->create();
        $teller = User::factory()->create(['role' => 'teller', 'branch_id' => $branch->id]);
        $allocation = $this->activeAllocation($teller, $branch, 'USD', '1000.0000');
        /** @var BranchPool $pool */
        $pool = BranchPool::factory()->for($branch)->create([
            'currency_code' => 'USD',
            'available_balance' => '9000.0000',
            'allocated_balance' => '1000.0000',
        ]);
        $transaction = $this->transaction($teller, $branch, TransactionType::Buy, 'USD', '100.0000', '450.0000');

        $this->service->applyTransactionAllocation($transaction, $allocation);

        $pool->refresh();
        $this->assertEquals('1100.0000', (string) $pool->allocated_balance);
        $this->assertEquals('9000.0000', (string) $pool->available_balance);
    }

    #[Test]
    public function return_to_pool_after_sell_leaves_no_residual_earmark(): void
    {
        $branch = Branch::factory()->create();
        $teller = User::factory()->create(['role' => 'teller', 'branch_id' => $branch->id]);
        $allocation = $this->activeAllocation($teller, $branch, 'USD', '1000.0000');
        /** @var BranchPool $pool */
        $pool = BranchPool::factory()->for($branch)->create([
            'currency_code' => 'USD',
            'available_balance' => '9000.0000',
            'allocated_balance' => '1000.0000',
        ]);
        $transaction = $this->transaction($teller, $branch, TransactionType::Sell, 'USD', '100.0000', '450.0000');

        $this->service->applyTransactionAllocation($transaction, $allocation);
        $this->service->returnToPool($allocation);

        // The 100 sold left the branch with the customer; only the unspent
        // 900 returns to available and no phantom earmark remains.
        $pool->refresh();
        $this->assertEquals('0.0000', (string) $pool->allocated_balance);
        $this->assertEquals('9900.0000', (string) $pool->available_balance);
    }

    #[Test]
    public function return_to_pool_after_buy_releases_purchased_stock_to_available(): void
    {
        $branch = Branch::factory()->create();
        $teller = User::factory()->create(['role' => 'teller', 'branch_id' => $branch->id]);
        $allocation = $this->activeAllocation($teller, $branch, 'USD', '1000.0000');
        /** @var BranchPool $pool */
        $pool = BranchPool::factory()->for($branch)->create([
            'currency_code' => 'USD',
            'available_balance' => '9000.0000',
            'allocated_balance' => '1000.0000',
        ]);
        $transaction = $this->transaction($teller, $branch, TransactionType::Buy, 'USD', '100.0000', '450.0000');

        $this->service->applyTransactionAllocation($transaction, $allocation);
        $this->service->returnToPool($allocation);

        // The 100 bought is real branch stock in the teller's custody — it
        // returns to available along with the original float.
        $pool->refresh();
        $this->assertEquals('0.0000', (string) $pool->allocated_balance);
        $this->assertEquals('10100.0000', (string) $pool->available_balance);
    }
}
