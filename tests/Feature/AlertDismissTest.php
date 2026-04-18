<?php

namespace Tests\Feature;

use App\Enums\FlagStatus;
use App\Enums\UserRole;
use App\Models\Alert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertDismissTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_unauthenticated_user_cannot_dismiss_alert(): void
    {
        $alert = Alert::factory()->create(['status' => FlagStatus::Open]);

        $response = $this->post(route('compliance.alerts.dismiss', $alert));

        $response->assertRedirect('/login');
    }

    public function test_teller_cannot_dismiss_alert(): void
    {
        $user = User::factory()->create(['role' => UserRole::Teller]);
        $alert = Alert::factory()->create(['status' => FlagStatus::Open]);

        $response = $this->actingAs($user)->post(route('compliance.alerts.dismiss', $alert));

        $response->assertStatus(403);
    }

    public function test_manager_cannot_dismiss_alert(): void
    {
        $user = User::factory()->create(['role' => UserRole::Manager]);
        $alert = Alert::factory()->create(['status' => FlagStatus::Open]);

        $response = $this->actingAs($user)->post(route('compliance.alerts.dismiss', $alert));

        $response->assertStatus(403);
    }

    public function test_compliance_officer_can_dismiss_alert(): void
    {
        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $alert = Alert::factory()->create(['status' => FlagStatus::Open]);

        $response = $this->actingAs($user)->post(route('compliance.alerts.dismiss', $alert));

        $response->assertRedirect(route('compliance.alerts.index'));
        $response->assertSessionHas('success', 'Alert dismissed');
    }

    public function test_admin_can_dismiss_alert(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);
        $alert = Alert::factory()->create(['status' => FlagStatus::Open]);

        $response = $this->actingAs($user)->post(route('compliance.alerts.dismiss', $alert));

        $response->assertRedirect(route('compliance.alerts.index'));
        $response->assertSessionHas('success', 'Alert dismissed');
    }

    public function test_dismiss_updates_alert_status_to_rejected(): void
    {
        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $alert = Alert::factory()->create(['status' => FlagStatus::Open]);

        $this->actingAs($user)->post(route('compliance.alerts.dismiss', $alert));

        $this->assertDatabaseHas('alerts', [
            'id' => $alert->id,
            'status' => FlagStatus::Rejected,
        ]);
    }

    public function test_dismiss_redirects_to_index_with_success_message(): void
    {
        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $alert = Alert::factory()->create(['status' => FlagStatus::Open]);

        $response = $this->actingAs($user)->post(route('compliance.alerts.dismiss', $alert));

        $response->assertRedirect(route('compliance.alerts.index'));
        $response->assertSessionHas('success', 'Alert dismissed');
    }

    public function test_dismissing_non_existent_alert_returns_404(): void
    {
        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);

        $response = $this->actingAs($user)->post(route('compliance.alerts.dismiss', ['alert' => 9999]));

        $response->assertStatus(404);
    }

    public function test_already_resolved_alert_cannot_be_dismissed(): void
    {
        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $alert = Alert::factory()->create(['status' => FlagStatus::Resolved]);

        $response = $this->actingAs($user)->post(route('compliance.alerts.dismiss', $alert));

        $response->assertStatus(403);
    }

    public function test_alert_with_under_review_status_can_be_dismissed(): void
    {
        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $alert = Alert::factory()->create(['status' => FlagStatus::UnderReview]);

        $response = $this->actingAs($user)->post(route('compliance.alerts.dismiss', $alert));

        $response->assertRedirect(route('compliance.alerts.index'));
        $this->assertDatabaseHas('alerts', [
            'id' => $alert->id,
            'status' => FlagStatus::Rejected,
        ]);
    }

    public function test_alert_with_escalated_status_can_be_dismissed(): void
    {
        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $alert = Alert::factory()->create(['status' => FlagStatus::Escalated]);

        $response = $this->actingAs($user)->post(route('compliance.alerts.dismiss', $alert));

        $response->assertRedirect(route('compliance.alerts.index'));
        $this->assertDatabaseHas('alerts', [
            'id' => $alert->id,
            'status' => FlagStatus::Rejected,
        ]);
    }

    public function test_dismiss_with_reason_is_successful(): void
    {
        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $alert = Alert::factory()->create(['status' => FlagStatus::Open]);

        $response = $this->actingAs($user)->post(route('compliance.alerts.dismiss', $alert), [
            'reason' => 'False positive - customer verified',
        ]);

        $response->assertRedirect(route('compliance.alerts.index'));
        $this->assertDatabaseHas('alerts', [
            'id' => $alert->id,
            'status' => FlagStatus::Rejected,
        ]);
    }

    public function test_dismiss_reason_is_optional(): void
    {
        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $alert = Alert::factory()->create(['status' => FlagStatus::Open]);

        $response = $this->actingAs($user)->post(route('compliance.alerts.dismiss', $alert), []);

        $response->assertRedirect(route('compliance.alerts.index'));
        $this->assertDatabaseHas('alerts', [
            'id' => $alert->id,
            'status' => FlagStatus::Rejected,
        ]);
    }

    public function test_already_rejected_alert_cannot_be_dismissed(): void
    {
        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $alert = Alert::factory()->create(['status' => FlagStatus::Rejected]);

        $response = $this->actingAs($user)->post(route('compliance.alerts.dismiss', $alert));

        $response->assertStatus(403);
    }
}
