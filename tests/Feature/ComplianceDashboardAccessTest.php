<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComplianceDashboardAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_compliance(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $response = $this->actingAs($user)->get('/compliance');

        $response->assertOk();
    }

    public function test_compliance_officer_can_access_compliance(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::ComplianceOfficer,
        ]);

        $response = $this->actingAs($user)->get('/compliance');

        $response->assertOk();
    }

    public function test_teller_cannot_access_compliance(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Teller,
        ]);

        $response = $this->actingAs($user)->get('/compliance');

        $response->assertForbidden();
    }
}
