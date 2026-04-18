<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\SanctionEntry;
use App\Models\SanctionList;
use App\Models\ScreeningResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScreeningApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_screen_customer_requires_authentication(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->postJson("/api/v1/screening/customer/{$customer->id}");

        $response->assertStatus(401);
    }

    public function test_screen_customer_requires_compliance_role(): void
    {
        $customer = Customer::factory()->create();
        $user = User::factory()->create(['role' => UserRole::Teller]);

        $this->actingAs($user, 'sanctum');

        $response = $this->postJson("/api/v1/screening/customer/{$customer->id}");

        $response->assertStatus(403);
    }

    public function test_screen_customer_returns_flag_for_match(): void
    {
        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $this->actingAs($user, 'sanctum');

        $list = SanctionList::factory()->create();
        $normalizedName = 'test terrorist';
        SanctionEntry::factory()->create([
            'list_id' => $list->id,
            'entity_name' => 'TEST TERRORIST',
            'normalized_name' => $normalizedName,
            'soundex_code' => soundex('test terrorist'),
            'metaphone_code' => metaphone('test terrorist'),
        ]);

        $customer = Customer::factory()->create([
            'full_name' => 'Test Terrorist',
        ]);

        $response = $this->postJson("/api/v1/screening/customer/{$customer->id}");

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertNotEquals('clear', $data['action']);
        $this->assertMatchesRegularExpression('/flag|block/', $data['action']);
    }

    public function test_get_screening_history(): void
    {
        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $this->actingAs($user, 'sanctum');

        $customer = Customer::factory()->create();

        ScreeningResult::factory()->count(3)->create([
            'customer_id' => $customer->id,
        ]);

        $response = $this->getJson("/api/v1/screening/customer/{$customer->id}/history");

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_get_screening_status(): void
    {
        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $this->actingAs($user, 'sanctum');

        $customer = Customer::factory()->create();

        $response = $this->getJson("/api/v1/screening/customer/{$customer->id}/status");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'customer_id',
                    'last_screened_at',
                    'last_result',
                    'last_match_score',
                    'sanction_hit',
                ],
            ]);
    }
}
