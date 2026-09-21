<?php

namespace Tests\Feature\Compliance;

use App\Enums\AlertStatus;
use App\Enums\CaseResolution;
use App\Enums\ComplianceCaseStatus;
use App\Models\Compliance\Alert;
use App\Models\Compliance\ComplianceCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ComplianceQueueActionsTest extends TestCase
{
    use RefreshDatabase;

    private User $officer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->officer = User::factory()->create(['role' => 'compliance_officer']);
    }

    #[Test]
    public function bulk_assign_assigns_selected_alerts_to_officer(): void
    {
        $alerts = Alert::factory()->count(2)->create(['case_id' => null, 'assigned_to' => null]);
        $assignee = User::factory()->create(['role' => 'compliance_officer']);

        $this->actingAs($this->officer)
            ->post(route('compliance.alerts.bulk-assign'), [
                'alert_ids' => $alerts->pluck('id')->all(),
                'user_id' => $assignee->id,
            ])
            ->assertRedirect(route('compliance.alerts.index'));

        foreach ($alerts as $alert) {
            $this->assertDatabaseHas('alerts', [
                'id' => $alert->id,
                'assigned_to' => $assignee->id,
            ]);
        }
    }

    #[Test]
    public function bulk_resolve_marks_selected_alerts_resolved(): void
    {
        $alerts = Alert::factory()->count(2)->create(['case_id' => null, 'status' => AlertStatus::Open]);

        $this->actingAs($this->officer)
            ->post(route('compliance.alerts.bulk-resolve'), [
                'alert_ids' => $alerts->pluck('id')->all(),
                'notes' => 'Batch reviewed',
            ])
            ->assertRedirect(route('compliance.alerts.index'));

        foreach ($alerts as $alert) {
            $this->assertDatabaseHas('alerts', [
                'id' => $alert->id,
                'status' => AlertStatus::Resolved->value,
            ]);
        }
    }

    #[Test]
    public function auto_assign_distributes_unassigned_alerts(): void
    {
        Alert::factory()->count(2)->create(['case_id' => null, 'assigned_to' => null]);

        $this->actingAs($this->officer)
            ->post(route('compliance.alerts.auto-assign'))
            ->assertRedirect(route('compliance.alerts.index'))
            ->assertSessionHas('success');

        $this->assertSame(0, Alert::whereNull('assigned_to')->whereNull('case_id')->count());
    }

    #[Test]
    public function add_note_creates_case_note(): void
    {
        $case = ComplianceCase::factory()->create();

        $this->actingAs($this->officer)
            ->post(route('compliance.cases.notes.store', $case), [
                'note_type' => 'Investigation',
                'content' => 'Reviewed the flagged transactions.',
                'is_internal' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Note added');

        $this->assertDatabaseHas('compliance_case_notes', [
            'case_id' => $case->id,
            'author_id' => $this->officer->id,
            'note_type' => 'Investigation',
            'content' => 'Reviewed the flagged transactions.',
            'is_internal' => true,
        ]);
    }

    #[Test]
    public function update_assigned_to_reassigns_case_and_moves_open_to_under_review(): void
    {
        $case = ComplianceCase::factory()->open()->create();
        $assignee = User::factory()->create(['role' => 'compliance_officer']);

        $this->actingAs($this->officer)
            ->patch(route('compliance.cases.update', $case), [
                'assigned_to' => $assignee->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Case updated successfully');

        $case->refresh();
        $this->assertSame($assignee->id, $case->assigned_to);
        $this->assertSame(ComplianceCaseStatus::UnderReview, $case->status);
    }

    #[Test]
    public function close_via_update_records_resolution(): void
    {
        $case = ComplianceCase::factory()->open()->create();

        $this->actingAs($this->officer)
            ->patch(route('compliance.cases.update', $case), [
                'status' => ComplianceCaseStatus::Closed->value,
                'resolution' => CaseResolution::NoConcern->value,
                'notes' => 'No suspicious activity found.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Case updated successfully');

        $case->refresh();
        $this->assertSame(ComplianceCaseStatus::Closed, $case->status);
        $this->assertSame(CaseResolution::NoConcern, $case->resolution);
        $this->assertNotNull($case->resolved_at);
    }

    #[Test]
    public function close_via_update_requires_resolution(): void
    {
        $case = ComplianceCase::factory()->open()->create();

        $this->actingAs($this->officer)
            ->patch(route('compliance.cases.update', $case), [
                'status' => ComplianceCaseStatus::Closed->value,
            ])
            ->assertSessionHasErrors('resolution');

        $this->assertSame(ComplianceCaseStatus::Open, $case->refresh()->status);
    }

    #[Test]
    public function update_status_rejects_disallowed_transition(): void
    {
        $case = ComplianceCase::factory()->closed()->create();

        $this->actingAs($this->officer)
            ->patch(route('compliance.cases.update', $case), [
                'status' => ComplianceCaseStatus::UnderReview->value,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(ComplianceCaseStatus::Closed, $case->refresh()->status);
    }

    #[Test]
    public function teller_cannot_access_bulk_or_note_endpoints(): void
    {
        $teller = User::factory()->create(['role' => 'teller']);
        $alert = Alert::factory()->create();
        $case = ComplianceCase::factory()->create();

        $this->actingAs($teller)
            ->post(route('compliance.alerts.bulk-assign'), [
                'alert_ids' => [$alert->id],
                'user_id' => $teller->id,
            ])
            ->assertForbidden();

        $this->actingAs($teller)
            ->post(route('compliance.cases.notes.store', $case), [
                'note_type' => 'Update',
                'content' => 'Should not persist.',
            ])
            ->assertForbidden();
    }
}
