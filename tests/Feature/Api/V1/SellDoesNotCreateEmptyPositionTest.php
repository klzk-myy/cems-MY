<?php

namespace Tests\Feature\Api\V1;

use App\Enums\CounterSessionStatus;
use App\Enums\TellerAllocationStatus;
use App\Models\Counter;
use App\Models\CounterSession;
use App\Models\Currency;
use App\Models\CurrencyPosition;
use App\Models\Customer;
use App\Models\TellerAllocation;
use App\Models\TillBalance;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SellDoesNotCreateEmptyPositionTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function sell_without_existing_position_does_not_create_zero_quantity_row(): void
    {
        $teller = User::factory()->create(['role' => 'teller']);
        $branch = $this->createTestBranch();
        $counter = Counter::factory()->create(['branch_id' => $branch->id]);
        $teller->branch_id = $branch->id;
        $teller->save();

        $customer = Customer::factory()->create([
            'risk_rating' => 'Low',
            'sanction_hit' => false,
            'pep_status' => false,
        ]);

        Currency::factory()->create(['code' => 'USD', 'is_active' => true]);

        CounterSession::factory()->create([
            'counter_id' => $counter->id,
            'user_id' => $teller->id,
            'opened_by' => $teller->id,
            'session_date' => today(),
            'opened_at' => now(),
            'status' => CounterSessionStatus::Open,
        ]);

        TillBalance::factory()->create([
            'till_id' => (string) $counter->code,
            'currency_code' => 'MYR',
            'branch_id' => $branch->id,
            'date' => today(),
            'opening_balance' => '100000.00',
            'opened_by' => $teller->id,
        ]);

        TillBalance::factory()->create([
            'till_id' => (string) $counter->code,
            'currency_code' => 'USD',
            'branch_id' => $branch->id,
            'date' => today(),
            'opening_balance' => '0',
            'opened_by' => $teller->id,
        ]);

        TellerAllocation::factory()->create([
            'user_id' => $teller->id,
            'branch_id' => $branch->id,
            'counter_id' => $counter->id,
            'currency_code' => 'USD',
            'allocated_amount' => '0',
            'current_balance' => '0',
            'requested_amount' => '0',
            'daily_limit_myr' => '500000.0000',
            'daily_used_myr' => '0.0000',
            'status' => TellerAllocationStatus::ACTIVE,
            'session_date' => today(),
        ]);

        $this->assertNull(
            CurrencyPosition::where('currency_code', 'USD')
                ->where('branch_id', $branch->id)
                ->first(),
            'No USD position should exist before the sell attempt'
        );

        $response = $this->actingAs($teller)
            ->postJson('/api/v1/transactions', [
                'customer_id' => $customer->id,
                'type' => 'Sell',
                'currency_code' => 'USD',
                'amount_foreign' => '100.00',
                'rate' => '4.50',
                'purpose' => 'Travel',
                'source_of_funds' => 'Savings',
                'till_id' => (string) $counter->code,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Insufficient stock for USD. Requested: 100.00, Available: 0.000000');

        $this->assertNull(
            CurrencyPosition::where('currency_code', 'USD')
                ->where('branch_id', $branch->id)
                ->first(),
            'Sell attempt without an existing position must not create a zero-quantity row'
        );
    }
}
