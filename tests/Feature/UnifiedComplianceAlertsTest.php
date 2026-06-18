<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Alert;
use App\Models\Compliance\ComplianceFinding;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UnifiedComplianceAlertsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    #[Test]
    public function unauthorized_access_redirects_to_login(): void
    {
        $response = $this->get('/compliance/unified');
        $response->assertRedirect('/login');
    }

    #[Test]
    public function teller_cannot_access_unified_alerts(): void
    {
        $user = User::factory()->create(['role' => UserRole::Teller]);
        $this->actingAs($user);

        $response = $this->get('/compliance/unified');
        $response->assertStatus(403);
    }

    #[Test]
    public function manager_cannot_access_unified_alerts(): void
    {
        $user = User::factory()->create(['role' => UserRole::Manager]);
        $this->actingAs($user);

        $response = $this->get('/compliance/unified');
        $response->assertStatus(403);
    }

    #[Test]
    public function compliance_officer_can_access_unified_alerts(): void
    {
        Http::fake([
            config('app.url').'/api/v1/compliance/findings*' => Http::response(['data' => ['data' => []]], 200),
        ]);

        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $this->actingAs($user);

        $response = $this->get('/compliance/unified');
        $response->assertStatus(200);
    }

    #[Test]
    public function admin_can_access_unified_alerts(): void
    {
        Http::fake([
            config('app.url').'/api/v1/compliance/findings*' => Http::response(['data' => ['data' => []]], 200),
        ]);

        $user = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($user);

        $response = $this->get('/compliance/unified');
        $response->assertStatus(200);
    }

    #[Test]
    public function page_loads_successfully(): void
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

    #[Test]
    public function stats_bar_is_displayed(): void
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

    #[Test]
    public function filter_form_is_present(): void
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

    #[Test]
    public function clear_filters_link_is_present(): void
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

    #[Test]
    public function source_filter_shows_alerts_only(): void
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

    #[Test]
    public function source_filter_shows_findings_only(): void
    {
        Http::fake([
            config('app.url').'/api/v1/compliance/findings*' => Http::response(['data' => ['data' => []]], 200),
        ]);

        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $this->actingAs($user);

        $response = $this->get('/compliance/unified?source=finding');
        $response->assertStatus(200);
    }

    #[Test]
    public function priority_filter(): void
    {
        Http::fake([
            config('app.url').'/api/v1/compliance/findings*' => Http::response(['data' => ['data' => []]], 200),
        ]);

        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $this->actingAs($user);

        $response = $this->get('/compliance/unified?priority=Critical');
        $response->assertStatus(200);
    }

    #[Test]
    public function status_filter(): void
    {
        Http::fake([
            config('app.url').'/api/v1/compliance/findings*' => Http::response(['data' => ['data' => []]], 200),
        ]);

        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $this->actingAs($user);

        $response = $this->get('/compliance/unified?status=open');
        $response->assertStatus(200);
    }

    #[Test]
    public function type_filter(): void
    {
        Http::fake([
            config('app.url').'/api/v1/compliance/findings*' => Http::response(['data' => ['data' => []]], 200),
        ]);

        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $this->actingAs($user);

        $response = $this->get('/compliance/unified?type=Velocity');
        $response->assertStatus(200);
    }

    #[Test]
    public function customer_search_filter(): void
    {
        Http::fake([
            config('app.url').'/api/v1/compliance/findings*' => Http::response(['data' => ['data' => []]], 200),
        ]);

        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $this->actingAs($user);

        $response = $this->get('/compliance/unified?customer=John');
        $response->assertStatus(200);
    }

    #[Test]
    public function date_range_filter(): void
    {
        Http::fake([
            config('app.url').'/api/v1/compliance/findings*' => Http::response(['data' => ['data' => []]], 200),
        ]);

        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $this->actingAs($user);

        $response = $this->get('/compliance/unified?from_date=2026-04-01&to_date=2026-04-17');
        $response->assertStatus(200);
    }

    #[Test]
    public function unified_table_is_present(): void
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

    #[Test]
    public function source_badges_are_displayed(): void
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

        $response->assertSee('Alert', false);
    }

    #[Test]
    public function stats_display_with_zero_values(): void
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

    #[Test]
    public function stats_display_with_alerts_data(): void
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

    #[Test]
    public function empty_state_shown_when_no_data(): void
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

    #[Test]
    public function findings_are_fetched_when_source_is_finding(): void
    {
        $customer = Customer::factory()->create();
        $finding = ComplianceFinding::factory()->create([
            'subject_type' => 'Customer',
            'subject_id' => $customer->id,
            'severity' => 'High',
            'finding_type' => 'Velocity_Exceeded',
            'status' => 'New',
            'details' => ['summary' => 'Test finding'],
        ]);

        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $this->actingAs($user);

        $response = $this->get('/compliance/unified?source=finding');
        $response->assertStatus(200);
        $response->assertSee('Finding', false);
    }

    #[Test]
    public function alerts_and_findings_are_merged_in_unified_view(): void
    {
        $customer = Customer::factory()->create();
        Alert::factory()->create(['customer_id' => $customer->id]);
        ComplianceFinding::factory()->create([
            'subject_type' => 'Customer',
            'subject_id' => $customer->id,
            'severity' => 'High',
            'finding_type' => 'Velocity_Exceeded',
            'status' => 'New',
            'details' => ['summary' => 'Test finding'],
        ]);

        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $this->actingAs($user);

        $response = $this->get('/compliance/unified?source=all');
        $response->assertStatus(200);

        $response->assertSee('Alert', false);
        $response->assertSee('Finding', false);
    }

    #[Test]
    public function findings_are_sorted_by_date(): void
    {
        $customer = Customer::factory()->create();
        ComplianceFinding::factory()->create([
            'subject_type' => 'Customer',
            'subject_id' => $customer->id,
            'severity' => 'High',
            'finding_type' => 'Velocity_Exceeded',
            'status' => 'New',
            'details' => ['summary' => 'Older finding'],
            'generated_at' => now()->subDay(),
        ]);
        ComplianceFinding::factory()->create([
            'subject_type' => 'Customer',
            'subject_id' => $customer->id,
            'severity' => 'Critical',
            'finding_type' => 'Sanction_Match',
            'status' => 'New',
            'details' => ['summary' => 'Newer finding'],
            'generated_at' => now(),
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
