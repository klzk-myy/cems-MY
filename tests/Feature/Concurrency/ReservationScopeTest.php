<?php

namespace Tests\Feature\Concurrency;

use App\Enums\CounterSessionStatus;
use App\Enums\TellerAllocationStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\CounterSession;
use App\Models\Currency;
use App\Models\CurrencyPosition;
use App\Models\Customer;
use App\Models\TellerAllocation;
use App\Models\TillBalance;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Transaction\TransactionApprovalService;
use PHPUnit\Framework\Attributes\Test;

/**
 * Regression tests for T4 (branch-scoped reservations) and T5 (MYR/FCY
 * floors on till booking).
 */
class ReservationScopeTest extends ConcurrentTestCase
{
    protected Customer $customer;

    protected User $compliance;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = Customer::factory()->create([
            'sanction_hit' => false,
            'pep_status' => false,
            'risk_rating' => 'Low',
        ]);
        $this->compliance = User::factory()->create(['role' => UserRole::ComplianceOfficer]);

        Currency::factory()->create(['code' => 'USD', 'is_active' => true]);
    }

    /**
     * T4: a pending Sell's reservation holds stock against the shared branch
     * position — a second till on the same branch must see it as unavailable.
     */
    #[Test]
    public function reservation_on_one_till_blocks_oversell_from_another(): void
    {
        $branch = Branch::factory()->create();
        $counterA = Counter::factory()->create(['branch_id' => $branch->id]);
        $counterB = Counter::factory()->create(['branch_id' => $branch->id]);
        $tellerA = $this->tellerAt($counterA, $branch);
        $tellerB = $this->tellerAt($counterB, $branch);
        $this->compliance->forceFill(['branch_id' => $branch->id])->save();

        CurrencyPosition::factory()->create([
            'currency_code' => 'USD',
            'branch_id' => (string) $branch->id,
            'quantity' => '5000.00',
            'average_cost' => '4.50',
            'current_rate' => '4.50',
        ]);

        // Teller A books a pending Sell of 3000 USD (3000*4.50 = 13500 MYR →
        // PendingApproval, reserving 3000 of the branch position).
        $pendingSell = $this->postTransaction($tellerA, $counterA, '3000.00');
        $this->assertEquals(TransactionStatus::PendingApproval, $pendingSell->status);

        // Teller B on a different till tries to sell 2500 USD — under
        // till-scoped reservations this passed (own till had no reservation),
        // overselling the 5000-unit branch position.
        $response = $this->actingAs($tellerB)
            ->postJson('/api/v1/transactions', [
                'customer_id' => $this->customer->id,
                'type' => TransactionType::Sell->value,
                'currency_code' => 'USD',
                'quantity' => '2500.00',
                'rate' => '4.50',
                'till_id' => (string) $counterB->code,
                'purpose' => 'Test transaction',
                'source_of_funds' => 'Salary',
            ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Insufficient stock for USD. Requested: 2500.00, Available: 2000.0000']);
    }

    /**
     * T5: approving a pending Buy whose MYR leg exceeds the till's ringgit
     * float must fail instead of driving the float negative.
     */
    #[Test]
    public function pending_buy_approval_fails_when_myr_insufficient(): void
    {
        $branch = Branch::factory()->create();
        $counter = Counter::factory()->create(['branch_id' => $branch->id]);
        // Only 5000 MYR in the drawer.
        $teller = $this->tellerAt($counter, $branch, '10000.00', '5000.00');
        $this->compliance->forceFill(['branch_id' => $branch->id])->save();

        // 2500 USD @ 4.50 = 11250 MYR ≥ auto-approve → PendingApproval.
        $buy = $this->postTransaction($teller, $counter, '2500.00', TransactionType::Buy);
        $this->assertEquals(TransactionStatus::PendingApproval, $buy->status);

        $result = $this->app->make(TransactionApprovalService::class)
            ->approve($buy->fresh(), $this->compliance->id);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Insufficient stock', $result->message);
        $this->assertEquals(TransactionStatus::PendingApproval, $buy->fresh()->status);
    }

    /**
     * T5: two serialized Buy approvals sharing one MYR float — the second
     * must fail once the first commits, never both booking.
     */
    #[Test]
    public function serialized_buy_approvals_cannot_drive_myr_negative(): void
    {
        $branch = Branch::factory()->create();
        $counter = Counter::factory()->create(['branch_id' => $branch->id]);
        // Float covers the first buy (11250 MYR) but not the second
        // (10800 MYR) — 3750 left over. Different quantities avoid the
        // recent-duplicate submission guard.
        $teller = $this->tellerAt($counter, $branch, '10000.00', '15000.00');
        $this->compliance->forceFill(['branch_id' => $branch->id])->save();

        $buyA = $this->postTransaction($teller, $counter, '2500.00', TransactionType::Buy);
        $buyB = $this->postTransaction($teller, $counter, '2400.00', TransactionType::Buy);
        $this->assertEquals(TransactionStatus::PendingApproval, $buyA->status);
        $this->assertEquals(TransactionStatus::PendingApproval, $buyB->status);

        $service = $this->app->make(TransactionApprovalService::class);

        // First approval commits, leaving 3750 MYR.
        $resultA = $service->approve($buyA->fresh(), $this->compliance->id);
        $this->assertTrue($resultA->success, $resultA->message);

        // Second approval re-reads under the lock and hits the MYR floor.
        $resultB = $service->approve($buyB->fresh(), $this->compliance->id);
        $this->assertFalse($resultB->success);
        $this->assertStringContainsString('Insufficient stock', $resultB->message);

        $myr = TillBalance::where('till_id', (string) $counter->code)
            ->where('currency_code', 'MYR')
            ->first();
        $this->assertEquals('3750.0000', $myr->getExpectedBalance());
        $this->assertEquals(TransactionStatus::PendingApproval, $buyB->fresh()->status);
    }

    private function tellerAt(Counter $counter, Branch $branch, string $usdOpening = '50000.00', string $myrOpening = '100000.00'): User
    {
        $teller = User::factory()->create([
            'role' => UserRole::Teller,
            'branch_id' => $branch->id,
        ]);

        CounterSession::factory()->create([
            'counter_id' => $counter->id,
            'user_id' => $teller->id,
            'opened_by' => $teller->id,
            'session_date' => today(),
            'opened_at' => now(),
            'status' => CounterSessionStatus::Open,
        ]);

        foreach ([['USD', $usdOpening], ['MYR', $myrOpening]] as [$code, $opening]) {
            TillBalance::factory()->create([
                'till_id' => (string) $counter->code,
                'currency_code' => $code,
                'branch_id' => $branch->id,
                'date' => today(),
                'opening_balance' => $opening,
                'opened_by' => $teller->id,
            ]);
        }

        TellerAllocation::factory()->create([
            'user_id' => $teller->id,
            'branch_id' => $branch->id,
            'counter_id' => $counter->id,
            'currency_code' => 'USD',
            'allocated_quantity' => $usdOpening,
            'current_quantity' => $usdOpening,
            'requested_quantity' => $usdOpening,
            'daily_limit_myr' => '500000.0000',
            'daily_used_myr' => '0.0000',
            'status' => TellerAllocationStatus::Active,
            'session_date' => today(),
        ]);

        return $teller;
    }

    private function postTransaction(User $teller, Counter $counter, string $quantity, TransactionType $type = TransactionType::Sell): Transaction
    {
        $response = $this->actingAs($teller)
            ->postJson('/api/v1/transactions', [
                'customer_id' => $this->customer->id,
                'type' => $type->value,
                'currency_code' => 'USD',
                'quantity' => $quantity,
                'rate' => '4.50',
                'till_id' => (string) $counter->code,
                'purpose' => 'Test transaction',
                'source_of_funds' => 'Salary',
            ]);

        $response->assertStatus(201);

        return Transaction::query()->whereKey($response->json('data.id'))->firstOrFail();
    }
}
