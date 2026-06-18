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

    protected User $user;

    protected User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->manager = User::factory()->create(['role' => 'manager']);
    }

    #[Test]
    public function customer_note_form_has_action_and_method(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->actingAs($this->user)->get(route('customers.show', $customer));

        $response->assertStatus(200);
        $response->assertSee('action="'.e(route('customers.notes.store', $customer)).'"', false);
        $response->assertSee('method="POST"', false);
    }

    #[Test]
    public function customer_note_can_be_stored(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->actingAs($this->user)->post(route('customers.notes.store', $customer), [
            'note' => 'Test note content',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('customer_notes', [
            'customer_id' => $customer->id,
            'note' => 'Test note content',
            'created_by' => $this->user->id,
        ]);
    }

    #[Test]
    public function rate_override_form_has_action_and_method(): void
    {
        $response = $this->actingAs($this->manager)->get(route('rates.index'));

        $response->assertStatus(200);
        $response->assertSee('id="override-form"', false);
        $response->assertSee('action="'.e(route('rates.override')).'"', false);
        $response->assertSee('method="POST"', false);
    }

    #[Test]
    public function rate_override_can_be_stored(): void
    {
        ExchangeRate::factory()->create([
            'currency_code' => 'USD',
            'rate_buy' => '4.2000',
            'rate_sell' => '4.3000',
        ]);

        $response = $this->actingAs($this->manager)->post(route('rates.override'), [
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
        $customer = Customer::factory()->create();

        $this->actingAs($this->user)->post(route('customers.notes.store', $customer), [
            'note' => 'Displayed note',
        ]);

        $response = $this->actingAs($this->user)->get(route('customers.show', $customer));
        $response->assertSee('Displayed note', false);
        $response->assertSee($this->user->name, false);
    }
}
