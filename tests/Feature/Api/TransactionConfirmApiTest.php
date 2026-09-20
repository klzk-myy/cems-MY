<?php

namespace Tests\Feature\Api;

use App\Enums\TransactionConfirmationStatus;
use App\Enums\TransactionStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\Customer;
use App\Models\Transaction;
use App\Models\TransactionConfirmation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransactionConfirmApiTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;

    protected Counter $counter;

    protected Customer $customer;

    protected User $teller;

    protected User $manager;

    protected User $compliance;

    protected function setUp(): void
    {
        parent::setUp();

        // Force every fixture amount over the confirmation threshold —
        // requiresConfirmation and the escalation path both read
        // cdd.large_transaction (T11: one shared getter).
        config(['thresholds.cdd.large_transaction' => '100']);

        $this->branch = Branch::factory()->create();
        $this->counter = Counter::factory()->create(['branch_id' => $this->branch->id]);
        $this->customer = Customer::factory()->create();
        $this->teller = User::factory()->create([
            'role' => UserRole::Teller,
            'branch_id' => $this->branch->id,
            'mfa_enabled' => false,
        ]);
        $this->manager = User::factory()->create([
            'role' => UserRole::Manager,
            'branch_id' => $this->branch->id,
            'mfa_enabled' => false,
        ]);
        $this->compliance = User::factory()->create([
            'role' => UserRole::ComplianceOfficer,
            'branch_id' => $this->branch->id,
            'mfa_enabled' => false,
        ]);
    }

    protected function largeTransaction(): Transaction
    {
        return Transaction::factory()->create([
            'customer_id' => $this->customer->id,
            'user_id' => $this->teller->id,
            'branch_id' => $this->branch->id,
            'till_id' => $this->counter->code,
            'amount_myr' => '1000.00',
            'status' => TransactionStatus::PendingApproval,
        ]);
    }

    #[Test]
    public function compliance_officer_can_confirm_large_transaction_via_api(): void
    {
        $transaction = $this->largeTransaction();

        TransactionConfirmation::factory()->create([
            'transaction_id' => $transaction->id,
            'status' => TransactionConfirmationStatus::Pending,
            'expires_at' => now()->addHour(),
        ]);

        $response = $this->actingAs($this->compliance, 'sanctum')
            ->postJson("/api/v1/transactions/{$transaction->id}/confirm", [
                'confirmation_action' => 'confirm',
                'notes' => 'verified with customer',
            ]);

        $response->assertStatus(200);

        $this->assertSame(
            TransactionConfirmationStatus::Confirmed,
            TransactionConfirmation::where('transaction_id', $transaction->id)->first()->status
        );
    }

    #[Test]
    public function teller_cannot_confirm_via_api(): void
    {
        $transaction = $this->largeTransaction();

        TransactionConfirmation::factory()->create([
            'transaction_id' => $transaction->id,
            'status' => TransactionConfirmationStatus::Pending,
            'expires_at' => now()->addHour(),
        ]);

        $response = $this->actingAs($this->teller, 'sanctum')
            ->postJson("/api/v1/transactions/{$transaction->id}/confirm", [
                'confirmation_action' => 'confirm',
            ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function confirm_rejects_when_no_pending_confirmation_exists(): void
    {
        $transaction = $this->largeTransaction();

        $response = $this->actingAs($this->compliance, 'sanctum')
            ->postJson("/api/v1/transactions/{$transaction->id}/confirm", [
                'confirmation_action' => 'confirm',
            ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function confirm_rejects_self_confirmation(): void
    {
        $transaction = Transaction::factory()->create([
            'customer_id' => $this->customer->id,
            'user_id' => $this->manager->id,
            'branch_id' => $this->branch->id,
            'till_id' => $this->counter->code,
            'amount_myr' => '1000.00',
            'status' => TransactionStatus::PendingApproval,
        ]);

        TransactionConfirmation::factory()->create([
            'transaction_id' => $transaction->id,
            'status' => TransactionConfirmationStatus::Pending,
            'expires_at' => now()->addHour(),
        ]);

        $response = $this->actingAs($this->manager, 'sanctum')
            ->postJson("/api/v1/transactions/{$transaction->id}/confirm", [
                'confirmation_action' => 'confirm',
            ]);

        // Self-approval is blocked by the 'approve' policy before the handler
        // runs; 403 is the enforcement signal here.
        $response->assertForbidden();
    }

    #[Test]
    public function confirm_rejects_expired_confirmation(): void
    {
        $transaction = $this->largeTransaction();

        TransactionConfirmation::factory()->create([
            'transaction_id' => $transaction->id,
            'status' => TransactionConfirmationStatus::Pending,
            'expires_at' => now()->subHour(),
        ]);

        $response = $this->actingAs($this->compliance, 'sanctum')
            ->postJson("/api/v1/transactions/{$transaction->id}/confirm", [
                'confirmation_action' => 'confirm',
            ]);

        $response->assertStatus(422);
    }
}
