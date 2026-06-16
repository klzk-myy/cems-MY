<?php

namespace Tests\Feature\Api;

use App\Models\Compliance\CustomerRiskProfile;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RiskPortfolioApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_portfolio_returns_distribution_with_integer_counts(): void
    {
        $customerOne = Customer::factory()->create();
        $customerTwo = Customer::factory()->create();
        $customerThree = Customer::factory()->create();

        CustomerRiskProfile::createForCustomer($customerOne->id, 90);
        CustomerRiskProfile::createForCustomer($customerTwo->id, 75);
        CustomerRiskProfile::createForCustomer($customerThree->id, 20);

        $response = $this->actingAs(User::factory()->create(['role' => 'compliance_officer']))
            ->getJson('/api/v1/risk/portfolio');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.by_tier.Critical', 1)
            ->assertJsonPath('data.by_tier.High', 1)
            ->assertJsonPath('data.by_tier.Low', 1);

        $payload = $response->json();

        $this->assertIsInt($payload['data']['total']);
        $this->assertIsInt($payload['data']['by_tier']['Critical']);
        $this->assertIsInt($payload['data']['by_tier']['High']);
        $this->assertIsInt($payload['data']['by_tier']['Low']);
    }

    public function test_portfolio_returns_empty_distribution_when_no_profiles_exist(): void
    {
        $response = $this->actingAs(User::factory()->create(['role' => 'compliance_officer']))
            ->getJson('/api/v1/risk/portfolio');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total', 0)
            ->assertJsonPath('data.by_tier', []);
    }
}
