<?php

namespace Tests\Feature\Web;

use App\Enums\TransactionStatus;
use App\Enums\UserRole;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransactionApprovalControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    #[Test]
    public function manager_approving_transaction_from_another_branch_receives_403(): void
    {
        $managerBranch = Branch::factory()->create();
        $transactionBranch = Branch::factory()->create();

        $manager = User::factory()->create([
            'role' => UserRole::Manager,
            'branch_id' => $managerBranch->id,
        ]);

        $teller = User::factory()->create([
            'role' => UserRole::Teller,
            'branch_id' => $transactionBranch->id,
        ]);

        $customer = Customer::factory()->create();
        $currency = Currency::factory()->create(['code' => 'USD', 'is_active' => true]);

        $transaction = Transaction::factory()->create([
            'user_id' => $teller->id,
            'branch_id' => $transactionBranch->id,
            'customer_id' => $customer->id,
            'currency_code' => $currency->code,
            'status' => TransactionStatus::PendingApproval,
            'amount_local' => '1000.00',
            'amount_foreign' => '250.00',
            'rate' => '4.000000',
        ]);

        $this->actingAs($manager);
        $this->setMfaVerification($manager);

        $response = $this->post(route('transactions.approve', $transaction));

        $response->assertStatus(403);
    }
}
