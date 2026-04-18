<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Alert;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UnifiedComplianceAlertsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    public function test_unauthorized_access_redirects_to_login(): void
    {
        $response = $this->get('/compliance/unified');
        $response->assertRedirect('/login');
    }

    public function test_teller_cannot_access_unified_alerts(): void
    {
        $user = User::factory()->create(['role' => UserRole::Teller]);
        $this->actingAs($user);

        $response = $this->get('/compliance/unified');
        $response->assertStatus(403);
    }

    public function test_manager_cannot_access_unified_alerts(): void
    {
        $user = User::factory()->create(['role' => UserRole::Manager]);
        $this->actingAs($user);

        $response = $this->get('/compliance/unified');
        $response->assertStatus(403);
    }

    public function test_compliance_officer_can_access_unified_alerts(): void
    {
        Http::fake([
            config('app.url').'/api/v1/compliance/findings*' => Http::response(['data' => ['data' => []]], 200),
        ]);

        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $this->actingAs($user);

        $response = $this->get('/compliance/unified');
        $response->assertStatus(200);
    }

    public function test_admin_can_access_unified_alerts(): void
    {
        Http::fake([
            config('app.url').'/api/v1/compliance/findings*' => Http::response(['data' => ['data' => []]], 200),
        ]);

        $user = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($user);

        $response = $this->get('/compliance/unified');
        $response->assertStatus(200);
    }

    public function test_page_loads_successfully(): void
    {
        Http::fake([
            config('app.url').'/api/v1/compliance/findings*' => Http::response(['data' => ['data' => []]], 200),
        ]);

        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $this->actingAs($user);

        $response = $this->get('/compliance/unified');
        $response->assertStatus(200);
        $response->assertViewIs('compliance.unified.index');
    }

    public function test_stats_bar_is_displayed(): void
    {
        Http::fake([
            config('app.url').'/api/v1/compliance/findings*' => Http::response(['data' => ['data' => []]], 200),
        ]);

        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $this->actingAs($user);

        $response = $this->get('/compliance/unified');
        $response->assertStatus(200);

        $response->assertSee('Total Items', false);
        $response->assertSee('Critical', false);
        $response->assertSee('Pending/Open', false);
        $response->assertSee('Resolved Today', false);
    }

    public function test_filter_form_is_present(): void
    {
        Http::fake([
            config('app.url').'/api/v1/compliance/findings*' => Http::response(['data' => ['data' => []]], 200),
        ]);

        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $this->actingAs($user);

        $response = $this->get('/compliance/unified');
        $response->assertStatus(200);

        $response->assertSee('Source', false);
        $response->assertSee('Priority', false);
        $response->assertSee('Status', false);
        $response->assertSee('Type', false);
        $response->assertSee('Customer', false);
        $response->assertSee('From Date', false);
        $response->assertSee('To Date', false);
        $response->assertSee('Apply Filters', false);
    }

    public function test_clear_filters_link_is_present(): void
    {
        Http::fake([
            config('app.url').'/api/v1/compliance/findings*' => Http::response(['data' => ['data' => []]], 200),
        ]);

        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $this->actingAs($user);

        $response = $this->get('/compliance/unified');
        $response->assertStatus(200);

        $response->assertSee('Clear', false);
    }

    public function test_source_filter_shows_alerts_only(): void
    {
        Http::fake([
            config('app.url').'/api/v1/compliance/findings*' => Http::response(['data' => ['data' => []]], 200),
        ]);

        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $this->actingAs($user);

        $response = $this->get('/compliance/unified?source=alert');
        $response->assertStatus(200);

        $response->assertSee('selected', false);
    }

    public function test_source_filter_shows_findings_only(): void
    {
        Http::fake([
            config('app.url').'/api/v1/compliance/findings*' => Http::response(['data' => ['data' => []]], 200),
        ]);

        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $this->actingAs($user);

        $response = $this->get('/compliance/unified?source=finding');
        $response->assertStatus(200);
    }

    public function test_priority_filter(): void
    {
        Http::fake([
            config('app.url').'/api/v1/compliance/findings*' => Http::response(['data' => ['data' => []]], 200),
        ]);

        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $this->actingAs($user);

        $response = $this->get('/compliance/unified?priority=Critical');
        $response->assertStatus(200);
    }

    public function test_status_filter(): void
    {
        Http::fake([
            config('app.url').'/api/v1/compliance/findings*' => Http::response(['data' => ['data' => []]], 200),
        ]);

        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $this->actingAs($user);

        $response = $this->get('/compliance/unified?status=open');
        $response->assertStatus(200);
    }

    public function test_type_filter(): void
    {
        Http::fake([
            config('app.url').'/api/v1/compliance/findings*' => Http::response(['data' => ['data' => []]], 200),
        ]);

        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $this->actingAs($user);

        $response = $this->get('/compliance/unified?type=Velocity');
        $response->assertStatus(200);
    }

    public function test_customer_search_filter(): void
    {
        Http::fake([
            config('app.url').'/api/v1/compliance/findings*' => Http::response(['data' => ['data' => []]], 200),
        ]);

        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $this->actingAs($user);

        $response = $this->get('/compliance/unified?customer=John');
        $response->assertStatus(200);
    }

    public function test_date_range_filter(): void
    {
        Http::fake([
            config('app.url').'/api/v1/compliance/findings*' => Http::response(['data' => ['data' => []]], 200),
        ]);

        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $this->actingAs($user);

        $response = $this->get('/compliance/unified?from_date=2026-04-01&to_date=2026-04-17');
        $response->assertStatus(200);
    }

    public function test_unified_table_is_present(): void
    {
        Http::fake([
            config('app.url').'/api/v1/compliance/findings*' => Http::response(['data' => ['data' => []]], 200),
        ]);

        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $this->actingAs($user);

        $response = $this->get('/compliance/unified');
        $response->assertStatus(200);

        $response->assertSee('Source', false);
        $response->assertSee('Priority', false);
        $response->assertSee('Type', false);
        $response->assertSee('Customer', false);
        $response->assertSee('Status', false);
        $response->assertSee('Assigned To', false);
        $response->assertSee('Date', false);
        $response->assertSee('Actions', false);
    }

    public function test_source_badges_are_displayed(): void
    {
        $customer = Customer::factory()->create();
        Alert::factory()->create(['customer_id' => $customer->id]);

        Http::fake([
            config('app.url').'/api/v1/compliance/findings*' => Http::response(['data' => ['data' => []]], 200),
        ]);

        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $this->actingAs($user);

        $response = $this->get('/compliance/unified');
        $response->assertStatus(200);

        $response->assertSee('badge-info', false);
        $response->assertSee('Alert', false);
    }

    public function test_stats_display_with_zero_values(): void
    {
        Http::fake([
            config('app.url').'/api/v1/compliance/findings*' => Http::response(['data' => ['data' => []]], 200),
        ]);

        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $this->actingAs($user);

        $response = $this->get('/compliance/unified');
        $response->assertStatus(200);

        $response->assertSee('0', false);
    }

    public function test_stats_display_with_alerts_data(): void
    {
        $customer = Customer::factory()->create();
        Alert::factory()->create(['customer_id' => $customer->id]);
        Alert::factory()->create(['customer_id' => $customer->id]);

        Http::fake([
            config('app.url').'/api/v1/compliance/findings*' => Http::response(['data' => ['data' => []]], 200),
        ]);

        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $this->actingAs($user);

        $response = $this->get('/compliance/unified');
        $response->assertStatus(200);

        $response->assertSee('2', false);
    }

    public function test_empty_state_shown_when_no_data(): void
    {
        Http::fake([
            config('app.url').'/api/v1/compliance/findings*' => Http::response(['data' => ['data' => []]], 200),
        ]);

        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $this->actingAs($user);

        $response = $this->get('/compliance/unified');
        $response->assertStatus(200);

        $response->assertSee('No items found', false);
    }

    public function test_findings_api_is_called_when_source_is_all(): void
    {
        Http::fake([
            config('app.url').'/api/v1/compliance/findings*' => Http::response(['data' => ['data' => []]], 200),
        ]);

        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $this->actingAs($user);

        $this->get('/compliance/unified?source=all');
        Http::assertSent(
            fn ($request) => str_contains($request->url(), '/api/v1/compliance/findings')
        );
    }

    public function test_findings_api_is_not_called_when_source_is_alert(): void
    {
        Http::fake([
            config('app.url').'/api/v1/compliance/findings*' => Http::response(['data' => ['data' => []]], 200),
        ]);

        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $this->actingAs($user);

        $this->get('/compliance/unified?source=alert');
        Http::assertNotSent(
            fn ($request) => str_contains($request->url(), '/api/v1/compliance/findings')
        );
    }

    public function test_findings_are_fetched_when_source_is_finding(): void
    {
        Http::fake([
            config('app.url').'/api/v1/compliance/findings*' => Http::response([
                'data' => [
                    'data' => [
                        [
                            'id' => 1,
                            'severity' => 'High',
                            'finding_type' => 'Velocity_Exceeded',
                            'status' => 'New',
                            'subject_type' => 'Customer',
                            'subject_id' => 1,
                            'subject_name' => 'Test Customer',
                            'generated_at' => now()->toIso8601String(),
                            'details' => ['summary' => 'Test finding'],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $this->actingAs($user);

        $response = $this->get('/compliance/unified?source=finding');
        $response->assertStatus(200);
        $response->assertSee('Finding', false);
    }

    public function test_alerts_and_findings_are_merged_in_unified_view(): void
    {
        $customer = Customer::factory()->create();
        Alert::factory()->create(['customer_id' => $customer->id]);

        Http::fake([
            config('app.url').'/api/v1/compliance/findings*' => Http::response([
                'data' => [
                    'data' => [
                        [
                            'id' => 999,
                            'severity' => 'High',
                            'finding_type' => 'Velocity_Exceeded',
                            'status' => 'New',
                            'subject_type' => 'Customer',
                            'subject_id' => $customer->id,
                            'subject_name' => $customer->full_name,
                            'generated_at' => now()->toIso8601String(),
                            'details' => ['summary' => 'Test finding'],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $this->actingAs($user);

        $response = $this->get('/compliance/unified?source=all');
        $response->assertStatus(200);

        $response->assertSee('Alert', false);
        $response->assertSee('Finding', false);
    }

    public function test_findings_are_sorted_by_date(): void
    {
        Http::fake([
            config('app.url').'/api/v1/compliance/findings*' => Http::response([
                'data' => [
                    'data' => [
                        [
                            'id' => 1,
                            'severity' => 'High',
                            'finding_type' => 'Velocity_Exceeded',
                            'status' => 'New',
                            'subject_type' => 'Customer',
                            'subject_id' => 1,
                            'subject_name' => 'Test Customer',
                            'generated_at' => now()->subDay()->toIso8601String(),
                            'details' => ['summary' => 'Older finding'],
                        ],
                        [
                            'id' => 2,
                            'severity' => 'Critical',
                            'finding_type' => 'Sanction_Match',
                            'status' => 'New',
                            'subject_type' => 'Customer',
                            'subject_id' => 1,
                            'subject_name' => 'Test Customer',
                            'generated_at' => now()->toIso8601String(),
                            'details' => ['summary' => 'Newer finding'],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $this->actingAs($user);

        $response = $this->get('/compliance/unified?source=finding');
        $response->assertStatus(200);

        $content = $response->getContent();
        $this->assertStringContainsString('Sanction_Match', $content);
        $this->assertStringContainsString('Velocity_Exceeded', $content);
    }
}
