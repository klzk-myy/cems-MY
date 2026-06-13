<?php

namespace Tests\Feature\Views;

use App\Models\Customer;
use App\Models\User;
use App\Services\EncryptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SharedComponentFormsTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_create_form_renders_with_shared_components(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('customers.create'));

        $response->assertStatus(200);
        $response->assertSee('for="full_name"', false);
        $response->assertSee('id="full_name"', false);
        $response->assertSee('name="full_name"', false);
        $response->assertSee('name="email"', false);
        $response->assertSee('name="id_type"', false);
        $response->assertSee('name="id_number"', false);
        $response->assertSee('name="nationality"', false);
        $response->assertSee('name="phone"', false);
        $response->assertSee('name="address"', false);
        $response->assertSee('name="date_of_birth"', false);
        $response->assertSee('Create Customer', false);
    }

    public function test_customer_edit_form_renders_with_shared_components(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create([
            'id_number_encrypted' => app(EncryptionService::class)->encrypt('123456789012'),
        ]);

        $response = $this->actingAs($user)->get(route('customers.edit', $customer));

        $response->assertStatus(200);
        $response->assertSee('for="full_name"', false);
        $response->assertSee('id="full_name"', false);
        $response->assertSee('name="full_name"', false);
        $response->assertSee('name="email"', false);
        $response->assertSee('name="id_type"', false);
        $response->assertSee('name="nationality"', false);
        $response->assertSee('name="phone"', false);
        $response->assertSee('name="address"', false);
        $response->assertSee('name="date_of_birth"', false);
        $response->assertSee('name="risk_level"', false);
        $response->assertSee('Update Customer', false);
    }

    public function test_user_create_form_renders_with_shared_components(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('users.create'));

        $response->assertStatus(200);
        $response->assertSee('for="username"', false);
        $response->assertSee('id="username"', false);
        $response->assertSee('name="username"', false);
        $response->assertSee('name="email"', false);
        $response->assertSee('name="password"', false);
        $response->assertSee('name="password_confirmation"', false);
        $response->assertSee('name="role"', false);
        $response->assertSee('name="branch_id"', false);
        $response->assertSee('name="is_active"', false);
        $response->assertSee('name="mfa_enabled"', false);
        $response->assertSee('Create User', false);
    }

    public function test_user_edit_form_renders_with_shared_components(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();

        $response = $this->actingAs($admin)->get(route('users.edit', $user));

        $response->assertStatus(200);
        $response->assertSee('for="username"', false);
        $response->assertSee('id="username"', false);
        $response->assertSee('name="username"', false);
        $response->assertSee('name="email"', false);
        $response->assertSee('name="password"', false);
        $response->assertSee('name="password_confirmation"', false);
        $response->assertSee('name="role"', false);
        $response->assertSee('name="branch_id"', false);
        $response->assertSee('name="is_active"', false);
        $response->assertSee('Update User', false);
    }

    public function test_journal_create_form_renders_with_shared_components(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);

        $response = $this->actingAs($manager)->get(route('accounting.journal.create'));

        $response->assertStatus(200);
        $response->assertSee('for="date"', false);
        $response->assertSee('id="date"', false);
        $response->assertSee('name="date"', false);
        $response->assertSee('name="reference"', false);
        $response->assertSee('name="status"', false);
        $response->assertSee('name="description"', false);
        $response->assertSee('name="lines[0][account]"', false);
        $response->assertSee('name="lines[0][debit]"', false);
        $response->assertSee('name="lines[0][credit]"', false);
        $response->assertSee('Create Entry', false);
    }

    public function test_customer_create_form_shows_validation_errors(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('customers.store'), []);

        $response->assertSessionHasErrors(['full_name', 'id_type', 'id_number', 'date_of_birth', 'nationality']);
    }
}
