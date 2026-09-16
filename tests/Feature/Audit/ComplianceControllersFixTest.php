<?php

namespace Tests\Feature\Audit;

use App\Enums\AlertPriority;
use App\Enums\CaseResolution;
use App\Enums\ComplianceCaseStatus;
use App\Enums\FlagStatus;
use App\Enums\UserRole;
use App\Models\Alert;
use App\Models\Compliance\ComplianceCase;
use App\Models\Customer;
use App\Models\SanctionEntry;
use App\Models\SanctionList;
use App\Models\User;
use App\Services\Compliance\SanctionsOrchestrationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ComplianceControllersFixTest extends TestCase
{
    use DatabaseTransactions;

    protected User $officer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->officer = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
    }

    #[Test]
    public function sanction_entries_search_works_with_special_characters(): void
    {
        $list = SanctionList::factory()->create();
        SanctionEntry::factory()->create([
            'list_id' => $list->id,
            'entity_name' => 'Alpha Trading Co',
            'status' => 'active',
        ]);
        SanctionEntry::factory()->create([
            'list_id' => $list->id,
            'entity_name' => 'Beta Corporation',
            'status' => 'active',
        ]);

        $this->actingAs($this->officer);

        $response = $this->get('/compliance/sanctions/entries?search=Trading');
        $response->assertStatus(200);
        $response->assertSee('Alpha Trading Co', false);

        // A literal % must not break the query. The old ESCAPE '\' clause
        // compiled to an unterminated string on MySQL (SQL syntax error 1064).
        $response = $this->get('/compliance/sanctions/entries?search=100%25');
        $response->assertStatus(200);
    }

    #[Test]
    public function sanctions_import_route_is_throttled(): void
    {
        $this->mock(SanctionsOrchestrationService::class, function ($mock) {
            $mock->shouldReceive('syncSanctionsList')->andReturn([
                'success' => true,
                'created' => 0,
                'updated' => 0,
                'deactivated' => 0,
            ]);
        });

        $list = SanctionList::factory()->create();
        $this->actingAs($this->officer);

        // ThrottleRequests keys on sha1(user id); clear it first so the test is
        // deterministic even when the cache store (e.g. Redis) persists
        // between test runs.
        RateLimiter::clear(sha1((string) $this->officer->id));

        for ($i = 0; $i < 5; $i++) {
            $this->post("/compliance/sanctions/{$list->id}/import")->assertStatus(302);
        }

        // The 6th request within the 10-minute window is rejected.
        $this->post("/compliance/sanctions/{$list->id}/import")->assertStatus(429);
    }

    #[Test]
    public function compliance_officer_can_update_case_status_via_web_endpoint(): void
    {
        $case = ComplianceCase::factory()->create(['status' => ComplianceCaseStatus::Open]);

        $this->actingAs($this->officer);

        // Closing a case requires a resolution per UpdateCaseStatusRequest.
        $response = $this->patch("/compliance/cases/{$case->id}", [
            'status' => 'Closed',
            'resolution' => CaseResolution::ClosedNoAction->value,
        ]);
        $response->assertStatus(302);
        $response->assertSessionHas('success');

        $this->assertSame(ComplianceCaseStatus::Closed, $case->fresh()->status);
        $this->assertNotNull($case->fresh()->resolved_at);
    }

    #[Test]
    public function case_status_update_enforces_transitions(): void
    {
        $case = ComplianceCase::factory()->create(['status' => ComplianceCaseStatus::Closed]);

        $this->actingAs($this->officer);

        $response = $this->patch("/compliance/cases/{$case->id}", ['status' => 'Open']);
        $response->assertStatus(302);
        $response->assertSessionHas('error');

        $this->assertSame(ComplianceCaseStatus::Closed, $case->fresh()->status);
    }

    #[Test]
    public function case_status_update_rejects_values_outside_the_enum(): void
    {
        $case = ComplianceCase::factory()->create(['status' => ComplianceCaseStatus::Open]);

        $this->actingAs($this->officer);

        // Lowercase slug used to pass validation but then crashed with a
        // TypeError; the request now validates against the enum backing values.
        $response = $this->patch("/compliance/cases/{$case->id}", ['status' => 'open']);
        $response->assertStatus(302);
        $response->assertSessionHasErrors('status');
    }

    #[Test]
    public function create_case_from_multi_customer_alerts_redirects_with_error(): void
    {
        $alertA = Alert::factory()->create();
        $alertB = Alert::factory()->create();

        $this->actingAs($this->officer);

        $response = $this->post('/compliance/cases', ['alert_ids' => [$alertA->id, $alertB->id]]);
        $response->assertStatus(302);
        $response->assertSessionHas('error');

        $this->assertSame(0, ComplianceCase::count());
    }

    #[Test]
    public function unified_alerts_page_renders_with_aggregated_stats(): void
    {
        $customer = Customer::factory()->create();
        Alert::factory()->create([
            'customer_id' => $customer->id,
            'priority' => AlertPriority::Critical,
            'status' => FlagStatus::Open,
        ]);
        Alert::factory()->create([
            'customer_id' => $customer->id,
            'priority' => AlertPriority::Low,
            'status' => FlagStatus::Resolved,
        ]);

        $this->actingAs($this->officer);

        // Exercises the single-aggregate alertStats() path (replaces four
        // count queries per source).
        $response = $this->get('/compliance/unified');
        $response->assertStatus(200);
        $response->assertViewIs('compliance.unified.index');
        $response->assertSee('Resolved', false);
    }

    #[Test]
    public function unified_alerts_page_handles_deep_pages_within_the_fetch_cap(): void
    {
        $customer = Customer::factory()->create();
        Alert::factory()->count(40)->create(['customer_id' => $customer->id]);

        $this->actingAs($this->officer);

        $response = $this->get('/compliance/unified?page=2');
        $response->assertStatus(200);
    }
}
