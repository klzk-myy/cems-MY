<?php

namespace Tests\Feature\Views;

use App\Models\Customer;
use App\Models\ExchangeRate;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BrokenFormsFixTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function customer_note_form_has_action_and_method(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create();

        $response = $this->actingAs($user)->get(route('customers.show', $customer));

        $response->assertStatus(200);
        $response->assertSee('action="'.e(route('customers.notes.store', $customer)).'"', false);
        $response->assertSee('method="POST"', false);
    }

    #[Test]
    public function customer_note_can_be_stored(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create();

        $response = $this->actingAs($user)->post(route('customers.notes.store', $customer), [
            'note' => 'Test note content',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('customer_notes', [
            'customer_id' => $customer->id,
            'note' => 'Test note content',
            'created_by' => $user->id,
        ]);
    }

    #[Test]
    public function rate_override_form_has_action_and_method(): void
    {
        $user = User::factory()->create(['role' => 'manager']);

        $response = $this->actingAs($user)->get(route('rates.index'));

        $response->assertStatus(200);
        $response->assertSee('id="override-form"', false);
        $response->assertSee('action="'.e(route('rates.override')).'"', false);
        $response->assertSee('method="POST"', false);
    }

    #[Test]
    public function rate_override_can_be_stored(): void
    {
        $user = User::factory()->create(['role' => 'manager']);
        ExchangeRate::factory()->create([
            'currency_code' => 'USD',
            'rate_buy' => '4.2000',
            'rate_sell' => '4.3000',
        ]);

        $response = $this->actingAs($user)->post(route('rates.override'), [
            'currency_code' => 'USD',
            'rate_buy' => '4.2500',
            'rate_sell' => '4.3500',
            'reason' => 'Market adjustment',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('exchange_rates', [
            'currency_code' => 'USD',
            'rate_buy' => '4.2500',
            'rate_sell' => '4.3500',
        ]);
    }

    #[Test]
    public function customer_note_is_displayed_after_creation(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create();

        $this->actingAs($user)->post(route('customers.notes.store', $customer), [
            'note' => 'Displayed note',
        ]);

        $response = $this->actingAs($user)->get(route('customers.show', $customer));
        $response->assertSee('Displayed note', false);
        $response->assertSee($user->name, false);
    }
}
