<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComplianceWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_compliance_workspace_requires_authentication(): void
    {
        $response = $this->get(route('compliance.workspace'));
        $response->assertRedirect(route('login'));
    }

    public function test_compliance_workspace_requires_compliance_role(): void
    {
        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);

        $response = $this->actingAs($user)->get(route('compliance.workspace'));
        $response->assertStatus(200);
        $response->assertViewIs('compliance.workspace.index');
    }

    public function test_non_compliance_users_cannot_access_workspace(): void
    {
        $user = User::factory()->create(['role' => UserRole::Teller]);

        $response = $this->actingAs($user)->get(route('compliance.workspace'));
        $response->assertForbidden();
    }
}
