<?php

namespace Tests\Feature\Audit;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionApprovalControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    /**
     * Regression guard: the approver recorded on the transaction must come
     * from the authenticated session (auth()->id()), not a stray variable.
     */
    public function test_approve_records_authenticated_user_as_approver(): void
    {
        $branch = Branch::factory()->create();

        // Approvals are compliance-gated: only ComplianceOfficer/Admin hold
        // approve_transactions in the default matrix.
        $approver = User::factory()->create([
            'role' => UserRole::ComplianceOfficer,
            'branch_id' => $branch->id,
        ]);

        $teller = User::factory()->create([
            'role' => UserRole::Teller,
            'branch_id' => $branch->id,
        ]);

        $customer = Customer::factory()->create();
        $currency = Currency::factory()->create(['code' => 'USD', 'is_active' => true]);

        $transaction = Transaction::factory()->create([
            'user_id' => $teller->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'currency_code' => $currency->code,
            // Pin to Buy: the factory randomizes type, and approving a Sell
            // requires a branch currency position this test does not seed —
            // the flake source was 50/50 Sell draws failing that check.
            'type' => TransactionType::Buy,
            'status' => TransactionStatus::PendingApproval,
            'amount_myr' => '1000.00',
            'quantity' => '250.00',
            'rate' => '4.000000',
        ]);

        $this->actingAs($approver);
        $this->setMfaVerification($approver);

        $response = $this->post(route('transactions.approve', $transaction));

        $response->assertRedirect();

        $this->assertSame(
            $approver->id,
            $transaction->fresh()->approved_by,
            'Approver must be the authenticated user'
        );
    }
}
