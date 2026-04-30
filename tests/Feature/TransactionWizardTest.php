<?php

namespace Tests\Feature;

use App\Enums\CddLevel;
use App\Enums\UserRole;
use App\Models\Counter;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionWizardTest extends TestCase
{
    use RefreshDatabase;

    protected User $teller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teller = User::factory()->create(['role' => UserRole::Teller]);
        Currency::factory()->create(['code' => 'USD', 'is_active' => true]);
        Counter::factory()->create(['id' => '1', 'code' => 'T1']);
    }

    public function test_step1_returns_cdd_level_and_required_documents(): void
    {
        $customer = Customer::factory()->create(['risk_rating' => 'Low']);

        $response = $this->actingAs($this->teller)
            ->postJson('/api/v1/wizard/transactions/step1', [
                'customer_id' => $customer->id,
                'type' => 'Buy',
                'currency_code' => 'USD',
                'amount_foreign' => '100.00',
                'rate' => '4.50',
                'till_id' => '1',
                'purpose' => 'Travel',
                'source_of_funds' => 'Salary',
            ]);

        if ($response->status() !== 200) {
            dump($response->json());
        }

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'cdd_level' => CddLevel::Simplified->value,
                'hold_required' => false,
            ])
            ->assertJsonPath('required_documents', function ($docs) {
                return count($docs) === 2; // MyKad front/back only
            });
    }

    public function test_step1_blocks_sanctioned_customers(): void
    {
        $customer = Customer::factory()->create(['sanction_hit' => true]);

        $response = $this->actingAs($this->teller)
            ->postJson('/api/v1/wizard/transactions/step1', [
                'customer_id' => $customer->id,
                'type' => 'Buy',
                'currency_code' => 'USD',
                'amount_foreign' => '100.00',
                'rate' => '4.50',
                'till_id' => '1',
                'purpose' => 'Travel',
                'source_of_funds' => 'Salary',
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'status' => 'blocked',
                'reason' => 'sanctions',
            ]);
    }

    public function test_step1_detects_velocity_risk(): void
    {
        $customer = Customer::factory()->create();

        // Create 3 recent transactions
        Transaction::factory()->count(3)->create([
            'customer_id' => $customer->id,
            'created_at' => now()->subHours(2),
        ]);

        $response = $this->actingAs($this->teller)
            ->postJson('/api/v1/wizard/transactions/step1', [
                'customer_id' => $customer->id,
                'type' => 'Buy',
                'currency_code' => 'USD',
                'amount_foreign' => '100.00',
                'rate' => '4.50',
                'till_id' => '1',
                'purpose' => 'Travel',
                'source_of_funds' => 'Salary',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('risk_flags', function ($flags) {
                return count($flags) > 0;
            });
    }

    public function test_teller_can_override_to_collect_additional_details(): void
    {
        $customer = Customer::factory()->create(['risk_rating' => 'Low']);

        $response = $this->actingAs($this->teller)
            ->postJson('/api/v1/wizard/transactions/step1', [
                'customer_id' => $customer->id,
                'type' => 'Buy',
                'currency_code' => 'USD',
                'amount_foreign' => '100.00',
                'rate' => '4.50',
                'till_id' => '1',
                'purpose' => 'Travel',
                'source_of_funds' => 'Salary',
                'collect_additional_details' => true,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'cdd_level' => CddLevel::Standard->value,
            ]);
    }

    public function test_enhanced_cdd_requires_hold(): void
    {
        $customer = Customer::factory()->create(['pep_status' => true]);

        $response = $this->actingAs($this->teller)
            ->postJson('/api/v1/wizard/transactions/step1', [
                'customer_id' => $customer->id,
                'type' => 'Buy',
                'currency_code' => 'USD',
                'amount_foreign' => '60000.00',
                'rate' => '4.50',
                'till_id' => '1',
                'purpose' => 'Investment',
                'source_of_funds' => 'Business',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'cdd_level' => CddLevel::Enhanced->value,
                'hold_required' => true,
            ]);
    }
}
