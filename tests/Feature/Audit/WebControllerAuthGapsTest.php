<?php

namespace Tests\Feature\Audit;

use App\Models\Branch;
use App\Models\Compliance\FlaggedTransaction;
use App\Models\TillBalance;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Transaction\ReceiptGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebControllerAuthGapsTest extends TestCase
{
    use RefreshDatabase;

    public function test_receipt_is_blocked_for_other_branch_transaction(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();

        $teller = User::factory()->for($branchA)->teller()->create();
        $foreign = Transaction::factory()->completed()->create([
            'branch_id' => $branchB->id,
            'purpose' => 'Foreign branch receipt target',
        ]);

        $this->actingAs($teller)
            ->get(route('transactions.receipt', $foreign))
            ->assertForbidden();
    }

    public function test_receipt_is_allowed_for_own_branch_transaction(): void
    {
        $branch = Branch::factory()->create();

        $teller = User::factory()->for($branch)->teller()->create();
        $own = Transaction::factory()->completed()->create([
            'branch_id' => $branch->id,
            'purpose' => 'Own branch receipt target',
        ]);

        $this->mock(ReceiptGenerationService::class, function ($mock) {
            $mock->shouldReceive('generate')
                ->once()
                ->andReturn(response('fake-pdf', 200, ['Content-Type' => 'application/pdf']));
        });

        $this->actingAs($teller)
            ->get(route('transactions.receipt', $own))
            ->assertOk();
    }

    public function test_dashboard_hides_other_branch_recent_transactions_from_teller(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();

        $teller = User::factory()->for($branchA)->teller()->create();
        Transaction::factory()->completed()->create([
            'branch_id' => $branchB->id,
            'purpose' => 'Foreign branch dashboard leak target',
            'created_at' => now(),
        ]);

        $this->actingAs($teller)
            ->get(route('dashboard'))
            ->assertViewHas('recent_transactions', function ($transactions) use ($branchA) {
                return $transactions->isNotEmpty()
                    ? $transactions->every(fn ($t) => $t->branch_id === $branchA->id)
                    : true;
            });
    }

    public function test_dashboard_hides_open_flag_count_from_teller(): void
    {
        $branch = Branch::factory()->create();

        $teller = User::factory()->for($branch)->teller()->create();
        FlaggedTransaction::factory()->open()->create();

        $this->actingAs($teller)
            ->get(route('dashboard'))
            ->assertViewHas('stats', function (array $stats) {
                return ($stats['flagged'] ?? -1) === 0;
            });
    }

    public function test_stock_cash_index_scopes_today_balances_to_own_branch(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();

        $manager = User::factory()->for($branchA)->manager()->create();
        TillBalance::factory()->create([
            'till_id' => 'T-'.uniqid(),
            'currency_code' => 'MYR',
            'branch_id' => $branchB->id,
            'date' => today(),
            'opening_balance' => '500.00',
            'variance' => null,
        ]);

        $this->actingAs($manager)
            ->get(route('stock-cash.index'))
            ->assertViewHas('todayBalances', function ($balances) use ($branchA) {
                return $balances->every(fn ($b) => $b->branch_id === $branchA->id);
            })
            ->assertViewHas('stats', function (array $stats) {
                // The only till balance belongs to another branch, so the
                // branch manager must not see it as an open till.
                return ($stats['open_tills'] ?? -1) === 0;
            });
    }

    public function test_transaction_index_fails_closed_for_unassigned_teller(): void
    {
        $unassigned = User::factory()->create(['branch_id' => null]);

        $this->actingAs($unassigned)
            ->get(route('transactions.index'))
            ->assertForbidden();
    }
}
