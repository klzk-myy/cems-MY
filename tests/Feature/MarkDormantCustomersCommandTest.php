<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MarkDormantCustomersCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function stamps_dormant_at_for_customers_inactive_beyond_window(): void
    {
        $dormant = Customer::factory()->create([
            'is_active' => true,
            'last_transaction_at' => now()->subMonths(13),
        ]);

        $this->artisanCommand('customers:mark-dormant', ['--months' => 12])
            ->assertSuccessful();

        $dormant->refresh();
        $this->assertNotNull($dormant->dormant_at);
        $this->assertDatabaseHas('system_logs', [
            'action' => 'customer_marked_dormant',
            'entity_id' => $dormant->id,
        ]);
    }

    #[Test]
    public function stamps_never_transacted_customers_onboarded_before_cutoff(): void
    {
        $old = Customer::factory()->create([
            'is_active' => true,
            'last_transaction_at' => null,
            'created_at' => now()->subMonths(18),
        ]);

        $this->artisanCommand('customers:mark-dormant')
            ->assertSuccessful();

        $this->assertNotNull($old->refresh()->dormant_at);
    }

    #[Test]
    public function skips_recently_active_customers(): void
    {
        $active = Customer::factory()->create([
            'is_active' => true,
            'last_transaction_at' => now()->subDays(30),
        ]);

        Transaction::factory()->for($active)->create();

        $this->artisanCommand('customers:mark-dormant')
            ->assertSuccessful();

        $this->assertNull($active->refresh()->dormant_at);
    }

    #[Test]
    public function skips_already_dormant_and_inactive_customers(): void
    {
        $alreadyDormant = Customer::factory()->create([
            'is_active' => true,
            'dormant_at' => now()->subMonths(2),
            'last_transaction_at' => now()->subYears(3),
        ]);

        $inactive = Customer::factory()->create([
            'is_active' => false,
            'last_transaction_at' => now()->subYears(3),
        ]);

        $this->artisanCommand('customers:mark-dormant')
            ->assertSuccessful();

        $this->assertNull($inactive->refresh()->dormant_at);
        $alreadyDormant->refresh();
        $this->assertTrue($alreadyDormant->dormant_at->lessThan(now()->subMonths(1)));
    }
}
