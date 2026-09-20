<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CustomerSearchControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $teller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->teller = User::factory()->create(['role' => UserRole::Teller]);
    }

    #[Test]
    public function search_requires_authentication(): void
    {
        $this->getJson('/customers/search?query=test')
            ->assertUnauthorized();
    }

    #[Test]
    public function search_requires_query_parameter(): void
    {
        $this->actingAs($this->teller)
            ->get('/customers/search')
            ->assertSessionHasErrors('query');
    }

    #[Test]
    public function search_query_must_be_at_least_two_characters(): void
    {
        $this->actingAs($this->teller)
            ->get('/customers/search?query=a')
            ->assertSessionHasErrors('query');
    }

    #[Test]
    public function search_returns_empty_results_when_no_match(): void
    {
        $this->actingAs($this->teller)
            ->get('/customers/search?query=nonexistentuser123')
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.count', 0);
    }

    #[Test]
    public function quick_create_requires_authentication(): void
    {
        $this->postJson('/customers/quick-create', $this->validPayload())
            ->assertUnauthorized();
    }

    #[Test]
    public function quick_create_validates_required_fields(): void
    {
        $this->actingAs($this->teller)
            ->post('/customers/quick-create', [])
            ->assertSessionHasErrors('full_name');
    }

    #[Test]
    public function quick_create_creates_customer_with_valid_payload(): void
    {
        $this->actingAs($this->teller)
            ->postJson('/customers/quick-create', $this->validPayload())
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.customer.full_name', 'Test User')
            ->assertJsonPath('data.customer.nationality', 'MY');
    }

    #[Test]
    public function quick_create_loads_existing_customer_when_id_number_is_registered(): void
    {
        $first = $this->actingAs($this->teller)
            ->postJson('/customers/quick-create', $this->validPayload())
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.existing', false);

        $this->actingAs($this->teller)
            ->postJson('/customers/quick-create', $this->validPayload())
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.existing', true)
            ->assertJsonPath('data.customer.id', $first->json('data.customer.id'));

        $this->assertSame(1, Customer::count(), 'Duplicate ID must not create a second customer');
    }

    #[Test]
    public function quick_create_finds_customer_registered_at_another_branch(): void
    {
        // Factory assigns each user a fresh branch — the registering teller
        // and the searching teller are on different branches by construction.
        $otherBranchTeller = User::factory()->create(['role' => UserRole::Teller]);
        $this->assertNotSame($otherBranchTeller->branch_id, $this->teller->branch_id);

        $first = $this->actingAs($otherBranchTeller)
            ->postJson('/customers/quick-create', $this->validPayload())
            ->assertJsonPath('data.existing', false);

        $this->actingAs($this->teller)
            ->postJson('/customers/quick-create', $this->validPayload())
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.existing', true)
            ->assertJsonPath('data.customer.id', $first->json('data.customer.id'));
    }

    private function validPayload(): array
    {
        return [
            'full_name' => 'Test User',
            'id_type' => 'MyKad',
            'id_number' => '999912345678',
            'date_of_birth' => '1990-01-15',
            'nationality' => 'MY',
        ];
    }
}
