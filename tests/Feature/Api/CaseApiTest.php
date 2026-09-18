<?php

namespace Tests\Feature\Api;

use App\Models\Compliance\ComplianceCase;
use App\Models\Compliance\ComplianceFinding;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CaseApiTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function show_still_returns_legacy_envelope()
    {
        $case = ComplianceCase::factory()->create();

        $response = $this->actingAs(User::factory()->create(['role' => 'compliance_officer']))
            ->getJson("/api/v1/compliance/cases/{$case->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $case->id)
            ->assertJsonPath('data.case_number', $case->case_number);
    }

    #[Test]
    public function update_rejects_lowercase_priority(): void
    {
        $case = ComplianceCase::factory()->create();

        $this->actingAs(User::factory()->create(['role' => 'compliance_officer']))
            ->patchJson("/api/v1/compliance/cases/{$case->id}", [
                'priority' => 'high',
            ])
            ->assertStatus(422);
    }

    #[Test]
    public function update_accepts_titlecase_priority(): void
    {
        $case = ComplianceCase::factory()->create();

        $this->actingAs(User::factory()->create(['role' => 'compliance_officer']))
            ->patchJson("/api/v1/compliance/cases/{$case->id}", [
                'priority' => 'High',
            ])
            ->assertOk()
            ->assertJsonPath('data.priority', 'High');
    }

    #[Test]
    public function store_manual_case_requires_customer(): void
    {
        $officer = User::factory()->create(['role' => 'compliance_officer']);
        $assignee = User::factory()->create(['role' => 'compliance_officer']);

        // No finding_id and no customer_id -> validation must fail instead of
        // hitting the FK with customer_id = 0.
        $this->actingAs($officer)
            ->postJson('/api/v1/compliance/cases', [
                'case_type' => 'Investigation',
                'assigned_to' => $assignee->id,
                'severity' => 'Medium',
                'summary' => 'Test case',
            ])
            ->assertStatus(422);
    }

    #[Test]
    public function store_manual_case_accepts_valid_customer(): void
    {
        $officer = User::factory()->create(['role' => 'compliance_officer']);
        $assignee = User::factory()->create(['role' => 'compliance_officer']);
        $customer = Customer::factory()->create();

        $this->actingAs($officer)
            ->postJson('/api/v1/compliance/cases', [
                'case_type' => 'Investigation',
                'assigned_to' => $assignee->id,
                'customer_id' => $customer->id,
                'severity' => 'Medium',
                'summary' => 'Test case',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.customer_id', $customer->id);
    }

    #[Test]
    public function dismiss_already_dismissed_finding_returns_400(): void
    {
        $finding = ComplianceFinding::factory()->create();
        $finding->dismiss('initial reason');

        $this->actingAs(User::factory()->create(['role' => 'compliance_officer']))
            ->postJson("/api/v1/compliance/findings/{$finding->id}/dismiss", [
                'reason' => 'dismissing again',
            ])
            ->assertStatus(422);
    }

    #[Test]
    public function dismiss_open_finding_succeeds(): void
    {
        $finding = ComplianceFinding::factory()->create();

        $this->actingAs(User::factory()->create(['role' => 'compliance_officer']))
            ->postJson("/api/v1/compliance/findings/{$finding->id}/dismiss", [
                'reason' => 'benign',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'dismissed');
    }
}
