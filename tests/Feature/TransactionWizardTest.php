<?php

namespace Tests\Feature;

use App\Enums\CddLevel;
use App\Enums\CounterSessionStatus;
use App\Enums\TellerAllocationStatus;
use App\Enums\UserRole;
use App\Models\Counter;
use App\Models\CounterSession;
use App\Models\Currency;
use App\Models\CurrencyPosition;
use App\Models\Customer;
use App\Models\TellerAllocation;
use App\Models\TillBalance;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransactionWizardTest extends TestCase
{
    use DatabaseTransactions;

    protected User $teller;

    protected Counter $counter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teller = User::factory()->create(['role' => UserRole::Teller]);
        Currency::factory()->create(['code' => 'USD', 'is_active' => true]);
        $this->counter = Counter::factory()->create([
            'code' => 'T1',
            'branch_id' => $this->teller->branch_id,
        ]);
        // The teller is seated at T1 — step 1 resolves the booking till from
        // this session, so every test below also exercises the session path.
        CounterSession::factory()->create([
            'counter_id' => $this->counter->id,
            'user_id' => $this->teller->id,
            'opened_by' => $this->teller->id,
            'session_date' => today(),
            'opened_at' => now(),
            'status' => CounterSessionStatus::Open,
        ]);
        TillBalance::factory()->create([
            'till_id' => $this->counter->code,
            'branch_id' => $this->teller->branch_id,
            'currency_code' => 'USD',
            'opening_balance' => '100000.00',
            'date' => today(),
        ]);
        TillBalance::factory()->create([
            'till_id' => $this->counter->code,
            'branch_id' => $this->teller->branch_id,
            'currency_code' => 'MYR',
            'opening_balance' => '500000.00',
            'date' => today(),
        ]);
        TellerAllocation::factory()->create([
            'user_id' => $this->teller->id,
            'branch_id' => $this->teller->branch_id,
            'counter_id' => $this->counter->id,
            'currency_code' => 'USD',
            'allocated_quantity' => '100000.0000',
            'current_quantity' => '100000.0000',
            'daily_limit_myr' => '500000.0000',
            'daily_used_myr' => '0.0000',
            'status' => TellerAllocationStatus::Active,
            'session_date' => today(),
        ]);
        CurrencyPosition::factory()->create([
            'currency_code' => 'USD',
            'branch_id' => (string) $this->teller->branch_id,
            'quantity' => '10000.00',
        ]);
    }

    #[Test]
    public function step1_returns_cdd_level_and_required_documents(): void
    {
        $customer = Customer::factory()->create(['risk_rating' => 'Low']);

        $response = $this->actingAs($this->teller)
            ->postJson('/api/v1/wizard/transactions/step1', [
                'customer_id' => $customer->id,
                'type' => 'Buy',
                'currency_code' => 'USD',
                'quantity' => '100.00',
                'rate' => '4.50',
                'till_id' => $this->counter->code,
                'purpose' => 'Travel',
                'source_of_funds' => 'Salary',
            ]);

        if ($response->status() !== 200) {
            dump($response->json());
        }

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.cdd_level', CddLevel::Simplified->value)
            ->assertJsonPath('data.hold_required', false)
            ->assertJsonPath('data.required_documents', function ($docs) {
                return count($docs) === 2; // MyKad front/back only
            });
    }

    #[Test]
    public function step1_blocks_sanctioned_customers(): void
    {
        $customer = Customer::factory()->create(['sanction_hit' => true]);

        $response = $this->actingAs($this->teller)
            ->postJson('/api/v1/wizard/transactions/step1', [
                'customer_id' => $customer->id,
                'type' => 'Buy',
                'currency_code' => 'USD',
                'quantity' => '100.00',
                'rate' => '4.50',
                'till_id' => $this->counter->code,
                'purpose' => 'Travel',
                'source_of_funds' => 'Salary',
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'blocked' => true,
                'reason' => 'sanctions',
            ]);
    }

    #[Test]
    public function step1_detects_velocity_risk(): void
    {
        $customer = Customer::factory()->create();

        // Create 3 recent transactions in the acting teller's branch so the
        // customer passes branch isolation (CustomerPolicy view rule) and the
        // velocity window sees them.
        Transaction::factory()->count(3)->create([
            'customer_id' => $customer->id,
            'branch_id' => $this->teller->branch_id,
            'created_at' => now()->subHours(2),
        ]);

        $response = $this->actingAs($this->teller)
            ->postJson('/api/v1/wizard/transactions/step1', [
                'customer_id' => $customer->id,
                'type' => 'Buy',
                'currency_code' => 'USD',
                'quantity' => '100.00',
                'rate' => '4.50',
                'till_id' => $this->counter->code,
                'purpose' => 'Travel',
                'source_of_funds' => 'Salary',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.risk_flags', function ($flags) {
                return count($flags) > 0;
            });
    }

    #[Test]
    public function teller_can_override_to_collect_additional_details(): void
    {
        $customer = Customer::factory()->create(['risk_rating' => 'Low']);

        $response = $this->actingAs($this->teller)
            ->postJson('/api/v1/wizard/transactions/step1', [
                'customer_id' => $customer->id,
                'type' => 'Buy',
                'currency_code' => 'USD',
                'quantity' => '100.00',
                'rate' => '4.50',
                'till_id' => $this->counter->code,
                'purpose' => 'Travel',
                'source_of_funds' => 'Salary',
                'collect_additional_details' => true,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.cdd_level', CddLevel::Standard->value);
    }

    #[Test]
    public function enhanced_cdd_requires_hold(): void
    {
        $customer = Customer::factory()->create(['pep_status' => true]);

        $response = $this->actingAs($this->teller)
            ->postJson('/api/v1/wizard/transactions/step1', [
                'customer_id' => $customer->id,
                'type' => 'Buy',
                'currency_code' => 'USD',
                'quantity' => '60000.00',
                'rate' => '4.50',
                'till_id' => $this->counter->code,
                'purpose' => 'Investment',
                'source_of_funds' => 'Business',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.cdd_level', CddLevel::Enhanced->value)
            ->assertJsonPath('data.hold_required', true);
    }

    #[Test]
    public function wizard_page_renders_for_authorized_teller(): void
    {
        $response = $this->actingAs($this->teller)
            ->get('/transactions/wizard');

        $response->assertStatus(200)
            ->assertSee('Transaction Wizard');
    }

    #[Test]
    public function wizard_page_is_forbidden_for_compliance(): void
    {
        $compliance = User::factory()->create(['role' => UserRole::ComplianceOfficer]);

        $response = $this->actingAs($compliance)
            ->get('/transactions/wizard');

        $response->assertStatus(403);
    }

    #[Test]
    public function step2_returns_transaction_summary_for_simplified_cdd(): void
    {
        $customer = Customer::factory()->create(['risk_rating' => 'Low']);

        $step1 = $this->actingAs($this->teller)
            ->postJson('/api/v1/wizard/transactions/step1', [
                'customer_id' => $customer->id,
                'type' => 'Buy',
                'currency_code' => 'USD',
                'quantity' => '100.00',
                'rate' => '4.50',
                'till_id' => $this->counter->code,
                'purpose' => 'Travel',
                'source_of_funds' => 'Salary',
            ]);

        $step1->assertStatus(200);
        $sessionId = $step1->json('data.wizard_session_id');
        $cddLevel = $step1->json('data.cdd_level');

        $response = $this->actingAs($this->teller)
            ->postJson('/api/v1/wizard/transactions/step2', [
                'wizard_session_id' => $sessionId,
                'cdd_level' => $cddLevel,
                'customer' => [
                    'occupation' => 'Engineer',
                    'employer_name' => 'Acme Corp',
                ],
            ]);

        if ($response->status() !== 200) {
            dump($response->json());
        }

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.next_step', 'review_confirm')
            ->assertJsonPath('data.transaction_summary.customer_name', $customer->full_name)
            ->assertJsonPath('data.transaction_summary.currency', 'USD');
    }

    #[Test]
    public function step2_requires_source_of_wealth_for_enhanced_cdd(): void
    {
        $customer = Customer::factory()->create(['pep_status' => true]);

        $step1 = $this->actingAs($this->teller)
            ->postJson('/api/v1/wizard/transactions/step1', [
                'customer_id' => $customer->id,
                'type' => 'Buy',
                'currency_code' => 'USD',
                'quantity' => '60000.00',
                'rate' => '4.50',
                'till_id' => $this->counter->code,
                'purpose' => 'Investment',
                'source_of_funds' => 'Business',
            ]);

        $step1->assertStatus(200);
        $sessionId = $step1->json('data.wizard_session_id');
        $cddLevel = $step1->json('data.cdd_level');
        $this->assertEquals(CddLevel::Enhanced->value, $cddLevel);

        // Missing source_of_wealth should fail validation.
        $response = $this->actingAs($this->teller)
            ->postJson('/api/v1/wizard/transactions/step2', [
                'wizard_session_id' => $sessionId,
                'cdd_level' => $cddLevel,
                'customer' => [
                    'occupation' => 'Business Owner',
                ],
            ]);

        $response->assertStatus(422);

        // With source_of_wealth + required documents provided, it should succeed.
        $response = $this->actingAs($this->teller)
            ->postJson('/api/v1/wizard/transactions/step2', [
                'wizard_session_id' => $sessionId,
                'cdd_level' => $cddLevel,
                'customer' => [
                    'occupation' => 'Business Owner',
                    'source_of_wealth' => 'Business profits and investments',
                    'beneficial_owner' => 'Self',
                    'proof_of_address' => UploadedFile::fake()->create('address.pdf', 10, 'application/pdf'),
                    'passport' => UploadedFile::fake()->create('passport.pdf', 10, 'application/pdf'),
                ],
                'transaction' => [
                    'expected_frequency' => 'quarterly',
                ],
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    #[Test]
    public function step3_creates_transaction_and_clears_session(): void
    {
        $customer = Customer::factory()->create(['risk_rating' => 'Low']);

        $step1 = $this->actingAs($this->teller)
            ->postJson('/api/v1/wizard/transactions/step1', [
                'customer_id' => $customer->id,
                'type' => 'Buy',
                'currency_code' => 'USD',
                'quantity' => '100.00',
                'rate' => '4.50',
                'till_id' => $this->counter->code,
                'purpose' => 'Travel',
                'source_of_funds' => 'Salary',
            ]);

        $sessionId = $step1->json('data.wizard_session_id');
        $cddLevel = $step1->json('data.cdd_level');

        $step2 = $this->actingAs($this->teller)
            ->postJson('/api/v1/wizard/transactions/step2', [
                'wizard_session_id' => $sessionId,
                'cdd_level' => $cddLevel,
                'customer' => [
                    'occupation' => 'Engineer',
                ],
            ]);

        $step2->assertStatus(200);

        $idempotencyKey = (string) Str::uuid();

        $response = $this->actingAs($this->teller)
            ->postJson('/api/v1/wizard/transactions/step3', [
                'wizard_session_id' => $sessionId,
                'confirm_details' => true,
                'idempotency_key' => $idempotencyKey,
            ]);

        if ($response->status() !== 200) {
            dump($response->json());
        }

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'data' => [
                    'transaction_id',
                    'transaction_number',
                    'transaction_status',
                ],
            ]);

        $transactionNumber = $response->json('data.transaction_number');
        $transactionId = $response->json('data.transaction_id');

        $this->assertNotNull($transactionId);
        $this->assertDatabaseHas('transactions', [
            'id' => $transactionId,
            'customer_id' => $customer->id,
        ]);
        $this->assertNotNull($transactionNumber);
    }

    #[Test]
    public function step3_rejects_customer_frozen_after_step1(): void
    {
        $customer = Customer::factory()->create(['risk_rating' => 'Low']);

        $step1 = $this->actingAs($this->teller)
            ->postJson('/api/v1/wizard/transactions/step1', [
                'customer_id' => $customer->id,
                'type' => 'Buy',
                'currency_code' => 'USD',
                'quantity' => '100.00',
                'rate' => '4.50',
                'till_id' => $this->counter->code,
                'purpose' => 'Travel',
                'source_of_funds' => 'Salary',
            ]);
        $step1->assertStatus(200);
        $sessionId = $step1->json('data.wizard_session_id');
        $cddLevel = $step1->json('data.cdd_level');

        $step2 = $this->actingAs($this->teller)
            ->postJson('/api/v1/wizard/transactions/step2', [
                'wizard_session_id' => $sessionId,
                'cdd_level' => $cddLevel,
                'customer' => ['occupation' => 'Engineer'],
            ]);
        $step2->assertStatus(200);

        // Freeze mid-wizard — the submit gate must re-check customer state.
        $customer->freeze('BNM freeze order');

        $response = $this->actingAs($this->teller)
            ->postJson('/api/v1/wizard/transactions/step3', [
                'wizard_session_id' => $sessionId,
                'confirm_details' => true,
                'idempotency_key' => (string) Str::uuid(),
            ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('transactions', [
            'customer_id' => $customer->id,
        ]);
    }

    #[Test]
    public function full_wizard_happy_path_end_to_end(): void
    {
        $customer = Customer::factory()->create(['risk_rating' => 'Low']);

        // Step 1: CDD assessment.
        $step1 = $this->actingAs($this->teller)
            ->postJson('/api/v1/wizard/transactions/step1', [
                'customer_id' => $customer->id,
                'type' => 'Sell',
                'currency_code' => 'USD',
                'quantity' => '500.00',
                'rate' => '4.70',
                'till_id' => $this->counter->code,
                'purpose' => 'Education',
                'source_of_funds' => 'Savings',
            ]);
        $step1->assertStatus(200);
        $sessionId = $step1->json('data.wizard_session_id');
        $cddLevel = $step1->json('data.cdd_level');

        // Step 2: Customer details.
        $step2 = $this->actingAs($this->teller)
            ->postJson('/api/v1/wizard/transactions/step2', [
                'wizard_session_id' => $sessionId,
                'cdd_level' => $cddLevel,
                'customer' => [
                    'occupation' => 'Teacher',
                    'employer_name' => 'Springfield High',
                ],
            ]);
        $step2->assertStatus(200);
        $step2->assertJsonPath('data.transaction_summary.type', 'Sell');

        // Step 3: Confirm and create.
        $step3 = $this->actingAs($this->teller)
            ->postJson('/api/v1/wizard/transactions/step3', [
                'wizard_session_id' => $sessionId,
                'confirm_details' => true,
                'idempotency_key' => (string) Str::uuid(),
            ]);
        $step3->assertStatus(200);
        $this->assertNotNull($step3->json('data.transaction_id'));

        // Session should be cleared after successful creation.
        $status = $this->actingAs($this->teller)
            ->getJson("/api/v1/wizard/transactions/{$sessionId}/status");
        $status->assertStatus(404);
    }

    /**
     * The counter is transparent: step 1 accepts no till_id at all — the
     * open session supplies it — and step 3 books on the session counter.
     */
    #[Test]
    public function session_counter_supplies_till_across_wizard(): void
    {
        $customer = Customer::factory()->create(['risk_rating' => 'Low']);

        $step1 = $this->actingAs($this->teller)
            ->postJson('/api/v1/wizard/transactions/step1', [
                'customer_id' => $customer->id,
                'type' => 'Buy',
                'currency_code' => 'USD',
                'quantity' => '100.00',
                'rate' => '4.50',
                'purpose' => 'Travel',
                'source_of_funds' => 'Salary',
            ]);

        $step1->assertStatus(200);
        $sessionId = $step1->json('data.wizard_session_id');
        $cddLevel = $step1->json('data.cdd_level');

        $this->actingAs($this->teller)
            ->postJson('/api/v1/wizard/transactions/step2', [
                'wizard_session_id' => $sessionId,
                'cdd_level' => $cddLevel,
                'customer' => ['occupation' => 'Engineer'],
            ])
            ->assertStatus(200);

        $step3 = $this->actingAs($this->teller)
            ->postJson('/api/v1/wizard/transactions/step3', [
                'wizard_session_id' => $sessionId,
                'confirm_details' => true,
                'idempotency_key' => (string) Str::uuid(),
            ]);

        $step3->assertStatus(200);
        $this->assertDatabaseHas('transactions', [
            'id' => $step3->json('data.transaction_id'),
            'till_id' => $this->counter->code,
            'counter_id' => $this->counter->id,
        ]);
    }

    #[Test]
    public function step3_requires_confirm_details(): void
    {
        $customer = Customer::factory()->create(['risk_rating' => 'Low']);

        $step1 = $this->actingAs($this->teller)
            ->postJson('/api/v1/wizard/transactions/step1', [
                'customer_id' => $customer->id,
                'type' => 'Buy',
                'currency_code' => 'USD',
                'quantity' => '100.00',
                'rate' => '4.50',
                'till_id' => $this->counter->code,
                'purpose' => 'Travel',
                'source_of_funds' => 'Salary',
            ]);

        $sessionId = $step1->json('data.wizard_session_id');
        $cddLevel = $step1->json('data.cdd_level');

        $this->actingAs($this->teller)
            ->postJson('/api/v1/wizard/transactions/step2', [
                'wizard_session_id' => $sessionId,
                'cdd_level' => $cddLevel,
                'customer' => ['occupation' => 'Engineer'],
            ])
            ->assertStatus(200);

        $response = $this->actingAs($this->teller)
            ->postJson('/api/v1/wizard/transactions/step3', [
                'wizard_session_id' => $sessionId,
                'confirm_details' => false,
                'idempotency_key' => (string) Str::uuid(),
            ]);

        $response->assertStatus(422);
    }
}
