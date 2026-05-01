<?php

namespace Tests\Feature;

use App\Enums\FlagStatus;
use App\Enums\UserRole;
use App\Models\Alert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertViewTest extends TestCase
{
    use RefreshDatabase;

    private User $complianceOfficer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->complianceOfficer = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
    }

    public function test_alert_index_page_loads_without_errors(): void
    {
        Alert::factory()->count(3)->create();

        $response = $this->actingAs($this->complianceOfficer)
            ->get(route('compliance.alerts.index'));

        $response->assertStatus(200);
        $response->assertViewIs('compliance.alerts.index');
    }

    public function test_alert_index_page_loads_when_no_alerts_exist(): void
    {
        $response = $this->actingAs($this->complianceOfficer)
            ->get(route('compliance.alerts.index'));

        $response->assertStatus(200);
        $response->assertSee('No alerts found');
    }

    public function test_alert_show_page_loads_without_errors(): void
    {
        $alert = Alert::factory()->create();

        $response = $this->actingAs($this->complianceOfficer)
            ->get(route('compliance.alerts.show', $alert));

        $response->assertStatus(200);
        $response->assertViewIs('compliance.alerts.show');
    }

    public function test_alert_show_page_displays_alert_details(): void
    {
        $alert = Alert::factory()->create([
            'reason' => 'Suspicious velocity detected',
            'status' => FlagStatus::Open,
        ]);

        $response = $this->actingAs($this->complianceOfficer)
            ->get(route('compliance.alerts.show', $alert));

        $response->assertStatus(200);
        $response->assertSee('Suspicious velocity detected');
    }

    public function test_alert_index_shows_unassigned_badge_when_no_assignee(): void
    {
        Alert::factory()->create(['assigned_to' => null]);

        $response = $this->actingAs($this->complianceOfficer)
            ->get(route('compliance.alerts.index'));

        $response->assertStatus(200);
        $response->assertSee('Unassigned');
    }

    public function test_alert_index_shows_assignee_username_when_assigned(): void
    {
        $assignee = User::factory()->create(['username' => 'officer_jane']);
        Alert::factory()->create(['assigned_to' => $assignee->id]);

        $response = $this->actingAs($this->complianceOfficer)
            ->get(route('compliance.alerts.index'));

        $response->assertStatus(200);
        $response->assertSee('officer_jane');
    }

    public function test_alert_show_page_displays_unassigned_when_no_assignee(): void
    {
        $alert = Alert::factory()->create(['assigned_to' => null]);

        $response = $this->actingAs($this->complianceOfficer)
            ->get(route('compliance.alerts.show', $alert));

        $response->assertStatus(200);
        $response->assertSee('Unassigned');
    }

    public function test_alert_show_page_displays_assignee_username_when_assigned(): void
    {
        $assignee = User::factory()->create(['username' => 'officer_john']);
        $alert = Alert::factory()->create(['assigned_to' => $assignee->id]);

        $response = $this->actingAs($this->complianceOfficer)
            ->get(route('compliance.alerts.show', $alert));

        $response->assertStatus(200);
        $response->assertSee('officer_john');
    }

    public function test_alert_assignedto_relationship_is_not_null_when_assigned(): void
    {
        $assignee = User::factory()->create();
        $alert = Alert::factory()->create(['assigned_to' => $assignee->id]);

        $alert->load('assignedTo');

        $this->assertNotNull($alert->assignedTo);
        $this->assertEquals($assignee->id, $alert->assignedTo->id);
    }

    public function test_alert_assignedto_relationship_is_null_when_unassigned(): void
    {
        $alert = Alert::factory()->create(['assigned_to' => null]);

        $alert->load('assignedTo');

        $this->assertNull($alert->assignedTo);
    }

    public function test_alert_resolution_redirects_to_index_with_success(): void
    {
        $alert = Alert::factory()->create(['status' => FlagStatus::Open]);

        $response = $this->actingAs($this->complianceOfficer)
            ->post(route('compliance.alerts.resolve', $alert), [
                'resolution' => 'Test resolution',
                'resolution_type' => 'legitimate',
            ]);

        $response->assertRedirect(route('compliance.alerts.index'));
        $response->assertSessionHas('success', 'Alert resolved successfully');
    }

    public function test_alert_resolution_updates_status_to_resolved(): void
    {
        $alert = Alert::factory()->create(['status' => FlagStatus::Open]);

        $this->actingAs($this->complianceOfficer)
            ->post(route('compliance.alerts.resolve', $alert), [
                'resolution' => 'Test resolution',
                'resolution_type' => 'legitimate',
            ]);

        $this->assertDatabaseHas('alerts', [
            'id' => $alert->id,
            'status' => FlagStatus::Resolved,
        ]);
    }

    public function test_alert_resolution_accepts_optional_notes(): void
    {
        $alert = Alert::factory()->create(['status' => FlagStatus::Open]);

        $response = $this->actingAs($this->complianceOfficer)
            ->post(route('compliance.alerts.resolve', $alert), [
                'resolution' => 'Verified with customer, false positive.',
                'resolution_type' => 'false_positive',
            ]);

        $response->assertRedirect(route('compliance.alerts.index'));
        $this->assertDatabaseHas('alerts', [
            'id' => $alert->id,
            'status' => FlagStatus::Resolved,
        ]);
    }

    public function test_alert_dismissal_redirects_to_index_with_success(): void
    {
        $alert = Alert::factory()->create(['status' => FlagStatus::Open]);

        $response = $this->actingAs($this->complianceOfficer)
            ->post(route('compliance.alerts.dismiss', $alert));

        $response->assertRedirect(route('compliance.alerts.index'));
        $response->assertSessionHas('success', 'Alert dismissed');
    }

    public function test_alert_dismissal_updates_status_to_rejected(): void
    {
        $alert = Alert::factory()->create(['status' => FlagStatus::Open]);

        $this->actingAs($this->complianceOfficer)
            ->post(route('compliance.alerts.dismiss', $alert));

        $this->assertDatabaseHas('alerts', [
            'id' => $alert->id,
            'status' => FlagStatus::Rejected,
        ]);
    }

    public function test_resolved_alert_cannot_be_dismissed(): void
    {
        $alert = Alert::factory()->create(['status' => FlagStatus::Resolved]);

        $response = $this->actingAs($this->complianceOfficer)
            ->post(route('compliance.alerts.dismiss', $alert));

        $response->assertStatus(403);
    }

    public function test_show_page_does_not_error_with_assignedto_relationship(): void
    {
        $assignee = User::factory()->create(['username' => 'compliance_user']);
        $alert = Alert::factory()->create(['assigned_to' => $assignee->id]);

        $response = $this->actingAs($this->complianceOfficer)
            ->get(route('compliance.alerts.show', $alert));

        $response->assertStatus(200);
        $response->assertSee('compliance_user');
        $response->assertDontSee('Undefined property');
    }

    public function test_index_page_does_not_error_with_assignedto_relationship(): void
    {
        $assignee = User::factory()->create(['username' => 'assigned_officer']);
        Alert::factory()->create(['assigned_to' => $assignee->id]);
        Alert::factory()->create(['assigned_to' => null]);

        $response = $this->actingAs($this->complianceOfficer)
            ->get(route('compliance.alerts.index'));

        $response->assertStatus(200);
        $response->assertSee('assigned_officer');
        $response->assertDontSee('Undefined property');
    }
}
