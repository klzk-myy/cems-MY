<?php

namespace Tests\Feature;

use App\Enums\FlagStatus;
use App\Enums\StockTransferStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Branch;
use App\Models\FlaggedTransaction;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\TillBalance;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Branch\BranchClosingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Guards against enum-vs-string comparison bugs in web views.
 *
 * Eloquent enum casts return enum instances, so strict comparisons against
 * string literals (e.g. `$model->status === 'completed'`) always evaluate to
 * false. That silently killed action buttons and rendered every badge with the
 * default variant. Each test renders a page with real data and asserts on the
 * behavior that the comparison controls.
 */
class ViewEnumComparisonTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // Dashboard stats (including recent transactions) are cached per scope.
        Cache::flush();
    }

    private function makeBranch(): Branch
    {
        return Branch::factory()->create([
            'code' => 'BR'.substr(uniqid(), -4),
            'name' => 'Enum Fix Branch',
            'address' => '1 Jalan Test',
            'phone' => '+60312345678',
            'email' => 'branch-'.uniqid().'@localhost.com',
            'is_active' => true,
        ]);
    }

    #[Test]
    public function transaction_show_renders_approve_actions_and_badges(): void
    {
        $admin = User::factory()->admin()->create();

        $transaction = Transaction::factory()->create([
            'status' => TransactionStatus::PendingApproval,
            'type' => TransactionType::Buy,
        ]);

        $this->actingAs($admin)
            ->get(route('transactions.show', $transaction))
            ->assertOk()
            ->assertSee('Pending Approval')
            ->assertSee('Approve')
            ->assertSee('Reject')
            ->assertSee('bg-success-subtle')  // Buy badge must be success, not danger
            ->assertSee('bg-warning-subtle'); // PendingApproval badge must be warning
    }

    #[Test]
    public function branch_closing_show_renders_settle_action_without_error(): void
    {
        $branch = $this->makeBranch();
        $admin = User::factory()->admin()->create(['branch_id' => $branch->id]);

        ($this->app->make(BranchClosingService::class))->initiateClosure($branch, $admin);

        $this->actingAs($admin)
            ->get(route('branches.closing.show', $branch))
            ->assertOk()
            ->assertSee('Mark as Settled');
    }

    #[Test]
    public function stock_transfer_index_renders_completed_and_requested_badges(): void
    {
        $user = User::factory()->manager()->create();

        // The index is branch-scoped; transfers must name the user's branch.
        $branchName = $user->branch->name;
        StockTransfer::factory()->create([
            'status' => StockTransferStatus::Completed->value,
            'source_branch_name' => $branchName,
        ]);
        StockTransfer::factory()->create([
            'status' => StockTransferStatus::Requested->value,
            'source_branch_name' => $branchName,
        ]);

        $this->actingAs($user)
            ->get(route('stock-transfers.index'))
            ->assertOk()
            ->assertSee('bg-success-subtle')
            ->assertSee('bg-warning-subtle');
    }

    #[Test]
    public function stock_transfer_show_renders_item_receipt_status(): void
    {
        $user = User::factory()->manager()->create();

        // StockTransferPolicy scopes view() to the user's branch; the transfer
        // must name the manager's branch as source or destination.
        $transfer = StockTransfer::factory()->create([
            'status' => StockTransferStatus::InTransit->value,
            'requested_by' => $user->id,
            'source_branch_name' => $user->branch->name,
        ]);

        StockTransferItem::factory()->create([
            'stock_transfer_id' => $transfer->id,
            'currency_code' => 'USD',
            'quantity' => 1000,
            'quantity_received' => 0,
            'value_myr' => 50000,
        ]);

        $this->actingAs($user)
            ->get(route('stock-transfers.show', $transfer))
            ->assertOk()
            ->assertSee('In Transit')
            ->assertSee('50,000.00');
    }

    #[Test]
    public function reconciliation_page_renders_transaction_columns(): void
    {
        $branch = $this->makeBranch();
        $manager = User::factory()->manager()->create(['branch_id' => $branch->id]);
        $teller = User::factory()->teller()->create(['branch_id' => $branch->id]);

        TillBalance::factory()->create([
            'till_id' => 'MAIN',
            'currency_code' => 'USD',
            'branch_id' => $branch->id,
            'date' => today()->toDateString(),
            'opening_balance' => '10000.0000',
            'opened_by' => $teller->id,
        ]);

        Transaction::factory()->create([
            'till_id' => 'MAIN',
            'branch_id' => $branch->id,
            'user_id' => $teller->id,
            'type' => TransactionType::Buy->value,
            'status' => TransactionStatus::Completed,
            'currency_code' => 'USD',
            'amount_foreign' => 500,
            'amount_local' => 2000,
            'rate' => 4.0,
        ]);

        Transaction::factory()->create([
            'till_id' => 'MAIN',
            'branch_id' => $branch->id,
            'user_id' => $teller->id,
            'type' => TransactionType::Sell->value,
            'status' => TransactionStatus::Completed,
            'currency_code' => 'USD',
            'amount_foreign' => 300,
            'amount_local' => 1500,
            'rate' => 5.0,
        ]);

        $this->actingAs($manager)
            ->get(route('stock-cash.reconciliation', [
                'till_id' => 'MAIN',
                'date' => today()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Total Buys')
            ->assertSee('Total Sells')
            ->assertSee('bg-info-subtle')    // Buy type badge
            ->assertSee('bg-accent/10')      // Sell type badge (purple variant)
            ->assertSee('500.00')            // Buy FCY (summary + table)
            ->assertSee('2,000.00')          // Buy MYR (summary + table)
            ->assertSee('300.00')            // Sell FCY (summary + table)
            ->assertSee('1,500.00')          // Sell MYR (summary + table)
            ->assertSee('bg-success-subtle'); // Completed status badges
    }

    #[Test]
    public function compliance_index_renders_open_flag_badge(): void
    {
        $user = User::factory()->admin()->create();

        FlaggedTransaction::factory()->open()->create([
            'status' => FlagStatus::Open->value,
        ]);

        $this->actingAs($user)
            ->get(route('compliance'))
            ->assertOk()
            ->assertSee('bg-warning-subtle');
    }

    #[Test]
    public function dashboard_renders_buy_transaction_with_success_badge(): void
    {
        $admin = User::factory()->admin()->create();

        Transaction::factory()->buy()->completed()->create([
            'branch_id' => $admin->branch_id,
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('bg-success-subtle');
    }
}
