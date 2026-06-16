<?php

namespace Tests\Feature\Api;

use App\Models\Compliance\ComplianceCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaseApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_still_returns_legacy_envelope()
    {
        $case = ComplianceCase::factory()->create();

        $response = $this->actingAs(User::factory()->create(['role' => 'compliance_officer']))
            ->getJson("/api/v1/compliance/cases/{$case->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $case->id)
            ->assertJsonPath('data.case_number', $case->case_number);
    }
}
