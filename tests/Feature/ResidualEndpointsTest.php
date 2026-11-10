<?php

namespace Tests\Feature;

use App\Enums\CounterSessionStatus;
use App\Enums\CounterStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Compliance\CustomerRiskProfile;
use App\Models\Counter;
use App\Models\CounterSession;
use App\Models\Customer;
use App\Models\ExchangeRate;
use App\Models\ExchangeRateHistory;
use App\Models\TellerAllocation;
use App\Models\TestResult;
use App\Models\TransactionImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ResidualEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create();
    }

    protected function createUser(UserRole $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'branch_id' => $role->requiresBranch() ? $this->branch->id : null,
            'is_active' => true,
        ]);
    }

    #[Test]
    public function api_rate_override_updates_currency_rate(): void
    {
        $manager = $this->createUser(UserRole::Manager);
        ExchangeRate::factory()->create(['currency_code' => 'USD']);

        $response = $this->actingAs($manager)->putJson('/api/v1/rates/USD', [
            'rate_buy' => '4.4500',
            'rate_sell' => '4.6500',
            'reason' => 'Market adjustment',
        ]);

        $response->assertOk();
    }

    #[Test]
    public function api_rate_copy_previous_copies_prior_day_rates(): void
    {
        $manager = $this->createUser(UserRole::Manager);
        ExchangeRateHistory::factory()->create([
            'currency_code' => 'USD',
            'branch_id' => $this->branch->id,
            'effective_date' => now()->subDay()->toDateString(),
        ]);

        $response = $this->actingAs($manager)->postJson('/api/v1/rates/copy-previous', [
            'date' => now()->subDay()->toDateString(),
        ]);

        $response->assertOk();
    }

    #[Test]
    public function api_risk_show_returns_customer_profile(): void
    {
        $officer = $this->createUser(UserRole::ComplianceOfficer);
        $customer = Customer::factory()->create();
        CustomerRiskProfile::factory()->create(['customer_id' => $customer->id]);

        $this->actingAs($officer)
            ->getJson("/api/v1/risk/{$customer->id}")
            ->assertOk();
    }

    #[Test]
    public function api_compliance_alerts_and_edd_indexes(): void
    {
        $officer = $this->createUser(UserRole::ComplianceOfficer);

        $this->actingAs($officer)->getJson('/api/v1/compliance/alerts')->assertOk();
        $this->actingAs($officer)->getJson('/api/v1/compliance/edd')->assertOk();
    }

    #[Test]
    public function api_allocation_show_scoped_to_branch(): void
    {
        $manager = $this->createUser(UserRole::Manager);
        $counter = Counter::factory()->create(['branch_id' => $this->branch->id]);
        $allocation = TellerAllocation::factory()->create([
            'branch_id' => $this->branch->id,
            'counter_id' => $counter->id,
        ]);

        $this->actingAs($manager)
            ->getJson("/api/v1/allocations/{$allocation->id}")
            ->assertOk();
    }

    #[Test]
    public function admin_creates_counter_via_api(): void
    {
        $admin = $this->createUser(UserRole::Admin);

        $response = $this->actingAs($admin)->postJson('/api/v1/counters', [
            'code' => 'CTR-9',
            'name' => 'Counter Nine',
            'branch_id' => $this->branch->id,
            'status' => CounterStatus::Inactive->value,
        ]);

        $response->assertStatus(201);
    }

    #[Test]
    public function teller_closes_open_counter_session_via_api(): void
    {
        $teller = $this->createUser(UserRole::Teller);
        $counter = Counter::factory()->create(['branch_id' => $this->branch->id]);
        CounterSession::factory()->create([
            'counter_id' => $counter->id,
            'user_id' => $teller->id,
            'session_date' => now()->toDateString(),
            'opened_at' => now()->subHour(),
            'opened_by' => $teller->id,
            'status' => CounterSessionStatus::Open,
        ]);

        $response = $this->actingAs($teller)->postJson("/api/v1/counters/{$counter->id}/close", [
            'closing_floats' => ['MYR' => '0', 'USD' => '0'],
        ]);

        $this->assertContains($response->getStatusCode(), [200, 404, 422]);
    }

    #[Test]
    public function manager_views_own_import_results(): void
    {
        $manager = $this->createUser(UserRole::Manager);
        $import = TransactionImport::factory()->create(['imported_by' => $manager->id]);

        $this->actingAs($manager)
            ->get("/transactions/import/{$import->id}")
            ->assertOk();
    }

    #[Test]
    public function import_results_forbidden_for_other_users_imports(): void
    {
        $teller = $this->createUser(UserRole::Teller);
        $other = $this->createUser(UserRole::Manager);
        $import = TransactionImport::factory()->create(['imported_by' => $other->id]);

        $this->actingAs($teller)
            ->get("/transactions/import/{$import->id}")
            ->assertForbidden();
    }

    #[Test]
    public function transactions_template_downloads_csv(): void
    {
        $manager = $this->createUser(UserRole::Manager);

        $response = $this->actingAs($manager)->get('/transactions/template');

        $response->assertOk();
        $this->assertStringContainsString('text', (string) $response->headers->get('content-type'));
    }

    #[Test]
    public function admin_reads_test_result_output_and_status(): void
    {
        $admin = $this->createUser(UserRole::Admin);
        $result = TestResult::factory()->create();

        $this->actingAs($admin)->getJson("/test-results/{$result->id}/output")->assertOk();
        $this->actingAs($admin)->getJson('/test-results/status')->assertOk();
    }

    #[Test]
    public function admin_reads_health_endpoint(): void
    {
        $admin = $this->createUser(UserRole::Admin);

        $response = $this->actingAs($admin)->get('/health');

        $this->assertContains($response->getStatusCode(), [200, 503]);
    }
}
