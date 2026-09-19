<?php

namespace Tests\Feature\Compliance;

use App\Enums\AlertPriority;
use App\Enums\FlagStatus;
use App\Exceptions\Domain\CaseManagementException;
use App\Models\Alert;
use App\Models\SystemLog;
use App\Models\User;
use App\Services\Compliance\AlertTriageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AlertTriageControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $officer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->officer = User::factory()->create(['role' => 'compliance_officer']);
    }

    #[Test]
    public function index_page_loads_for_compliance_officer(): void
    {
        Alert::factory()->count(3)->create();

        $response = $this->actingAs($this->officer)
            ->get(route('compliance.alerts.index'));

        $response->assertStatus(200)
            ->assertViewIs('compliance.alerts.index')
            ->assertViewHas('alerts')
            ->assertViewHas('summary');
    }

    #[Test]
    public function index_filters_by_priority(): void
    {
        Alert::factory()->create(['priority' => AlertPriority::Critical]);
        Alert::factory()->create(['priority' => AlertPriority::Low]);

        $response = $this->actingAs($this->officer)
            ->get(route('compliance.alerts.index', ['priority' => 'critical']));

        $response->assertStatus(200);
        $alerts = $response->viewData('alerts');

        $this->assertTrue($alerts->every(fn (Alert $a) => $a->priority === AlertPriority::Critical));
    }

    #[Test]
    public function index_filters_by_assigned_status(): void
    {
        Alert::factory()->create(['assigned_to' => null]);
        Alert::factory()->create(['assigned_to' => $this->officer->id]);

        $response = $this->actingAs($this->officer)
            ->get(route('compliance.alerts.index', ['assigned' => 'unassigned']));

        $response->assertStatus(200);
        $alerts = $response->viewData('alerts');

        $this->assertTrue($alerts->every(fn (Alert $a) => $a->assigned_to === null));
    }

    #[Test]
    public function show_returns_alert_with_relations_loaded(): void
    {
        $alert = Alert::factory()->create();

        $response = $this->actingAs($this->officer)
            ->get(route('compliance.alerts.show', $alert));

        $response->assertStatus(200)
            ->assertViewIs('compliance.alerts.show')
            ->assertViewHas('alert', $alert);
    }

    #[Test]
    public function assign_updates_alert_assigned_to_and_redirects(): void
    {
        $alert = Alert::factory()->create(['assigned_to' => null]);
        $assignee = User::factory()->create(['role' => 'compliance_officer']);

        $this->actingAs($this->officer)
            ->post(route('compliance.alerts.assign', $alert), [
                'assignee_id' => $assignee->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Alert assigned successfully');

        $this->assertDatabaseHas('alerts', [
            'id' => $alert->id,
            'assigned_to' => $assignee->id,
        ]);
    }

    #[Test]
    public function resolve_redirects_to_alerts_index(): void
    {
        $alert = Alert::factory()->create();

        $this->actingAs($this->officer)
            ->post(route('compliance.alerts.resolve', $alert), [
                'resolution' => 'Confirmed fraud — case opened',
                'resolution_type' => 'escalated',
            ])
            ->assertRedirect(route('compliance.alerts.index'))
            ->assertSessionHas('success', 'Alert resolved successfully');
    }

    #[Test]
    public function dismiss_updates_status_to_rejected_and_redirects(): void
    {
        $alert = Alert::factory()->create(['status' => FlagStatus::Open]);

        $this->actingAs($this->officer)
            ->post(route('compliance.alerts.dismiss', $alert), [
                'reason' => 'False positive',
            ])
            ->assertRedirect(route('compliance.alerts.index'))
            ->assertSessionHas('success', 'Alert dismissed');

        $this->assertDatabaseHas('alerts', [
            'id' => $alert->id,
            'status' => FlagStatus::Rejected->value,
        ]);
    }

    #[Test]
    public function dismiss_rejects_already_resolved_alert(): void
    {
        $alert = Alert::factory()->create(['status' => FlagStatus::Resolved]);

        $this->actingAs($this->officer)
            ->post(route('compliance.alerts.dismiss', $alert), [
                'reason' => 'Double dismiss attempt',
            ])
            ->assertStatus(403);

        $this->assertDatabaseMissing('alerts', [
            'id' => $alert->id,
            'status' => FlagStatus::Rejected->value,
        ]);
    }

    #[Test]
    public function dismiss_rejects_already_rejected_alert(): void
    {
        $alert = Alert::factory()->create(['status' => FlagStatus::Rejected]);

        $this->actingAs($this->officer)
            ->post(route('compliance.alerts.dismiss', $alert), [
                'reason' => 'Double dismiss attempt',
            ])
            ->assertStatus(403);
    }

    #[Test]
    public function resolve_alert_is_rejected_via_service_when_alert_already_terminal(): void
    {
        $alert = Alert::factory()->create(['status' => FlagStatus::Resolved]);
        $service = app(AlertTriageService::class);

        $this->expectException(CaseManagementException::class);

        $service->resolveAlert($alert, $this->officer->id);
    }

    #[Test]
    public function assign_rejects_assignee_who_is_not_an_officer(): void
    {
        $alert = Alert::factory()->create(['assigned_to' => null]);
        $teller = User::factory()->create(['role' => 'teller']);
        $service = app(AlertTriageService::class);

        $this->expectException(CaseManagementException::class);

        $service->assignToOfficer($alert, $teller->id);
    }

    #[Test]
    public function assign_rejects_inactive_officer(): void
    {
        $alert = Alert::factory()->create(['assigned_to' => null]);
        $inactive = User::factory()->create(['role' => 'compliance_officer', 'is_active' => false]);
        $service = app(AlertTriageService::class);

        $this->expectException(CaseManagementException::class);

        $service->assignToOfficer($alert, $inactive->id);
    }

    #[Test]
    public function assign_alert_via_bulk_assign_writes_audit_record_per_alert(): void
    {
        $alerts = Alert::factory()->count(2)->create(['case_id' => null]);
        $service = app(AlertTriageService::class);

        $service->bulkAssign($alerts->pluck('id')->all(), $this->officer->id);

        $this->assertEquals(2, SystemLog::query()
            ->where('action', 'alert_assigned')
            ->count());
    }
}
