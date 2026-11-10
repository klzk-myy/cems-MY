<?php

namespace Tests\Feature\Api\V1;

use App\Enums\EddStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Compliance\Alert;
use App\Models\Compliance\CustomerRiskProfile;
use App\Models\Compliance\EnhancedDiligenceRecord;
use App\Models\Customer;
use App\Models\ExchangeRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RatesRiskComplianceApiTest extends TestCase
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
    public function rates_index_returns_current_rate_cards(): void
    {
        ExchangeRate::factory()->create(['currency_code' => 'USD', 'rate_buy' => '4.5000', 'rate_sell' => '4.6000']);
        $manager = $this->createUser(UserRole::Manager);

        $response = $this->actingAs($manager)->getJson('/api/v1/rates');

        $response->assertOk();
        $this->assertStringContainsString('USD', (string) $response->getContent());
    }

    #[Test]
    public function rates_summary_returns_spread_data(): void
    {
        ExchangeRate::factory()->create(['currency_code' => 'USD']);
        $manager = $this->createUser(UserRole::Manager);

        $this->actingAs($manager)->getJson('/api/v1/rates/summary')->assertOk();
    }

    #[Test]
    public function rates_dates_returns_available_rate_dates(): void
    {
        ExchangeRate::factory()->create(['currency_code' => 'USD']);
        $manager = $this->createUser(UserRole::Manager);

        $this->actingAs($manager)->getJson('/api/v1/rates/dates')->assertOk();
    }

    #[Test]
    public function rates_show_returns_single_currency_card_and_404_when_missing(): void
    {
        ExchangeRate::factory()->create(['currency_code' => 'USD']);
        $manager = $this->createUser(UserRole::Manager);

        $this->actingAs($manager)->getJson('/api/v1/rates/USD')->assertOk();
        $this->actingAs($manager)->getJson('/api/v1/rates/XXX')->assertNotFound();
    }

    #[Test]
    public function rates_history_returns_series_for_currency(): void
    {
        ExchangeRate::factory()->create(['currency_code' => 'USD']);
        $manager = $this->createUser(UserRole::Manager);

        $this->actingAs($manager)->getJson('/api/v1/rates/history/USD?days=7')->assertOk();
    }

    #[Test]
    public function rates_check_reports_missing_currencies(): void
    {
        ExchangeRate::factory()->create(['currency_code' => 'USD']);
        $manager = $this->createUser(UserRole::Manager);

        $response = $this->actingAs($manager)->getJson('/api/v1/rates/check?currencies[]=USD&currencies[]=EUR');

        $response->assertOk();
        $this->assertStringContainsString('EUR', (string) $response->getContent());
    }

    #[Test]
    public function rates_validate_accepts_a_valid_buy_rate(): void
    {
        ExchangeRate::factory()->create(['currency_code' => 'USD', 'rate_buy' => '4.5000']);
        $manager = $this->createUser(UserRole::Manager);

        $response = $this->actingAs($manager)->postJson('/api/v1/rates/validate', [
            'rate' => '4.5000',
            'currency_code' => 'USD',
            'type' => 'buy',
        ]);

        $response->assertOk();
    }

    #[Test]
    public function teller_is_forbidden_from_rates_api(): void
    {
        $teller = $this->createUser(UserRole::Teller);

        $this->actingAs($teller)->getJson('/api/v1/rates')->assertForbidden();
        $this->actingAs($teller)->postJson('/api/v1/rates/fetch', [])->assertForbidden();
    }

    #[Test]
    public function risk_history_returns_profile_timeline(): void
    {
        $customer = Customer::factory()->create();
        CustomerRiskProfile::factory()->create([
            'customer_id' => $customer->id,
            'risk_score' => 65,
        ]);
        $officer = $this->createUser(UserRole::ComplianceOfficer);

        $response = $this->actingAs($officer)->getJson("/api/v1/risk/{$customer->id}/history");

        $response->assertOk();
        $this->assertStringContainsString('65', (string) $response->getContent());
    }

    #[Test]
    public function risk_recalculate_recomputes_customer_score(): void
    {
        $customer = Customer::factory()->create();
        $officer = $this->createUser(UserRole::ComplianceOfficer);

        $this->actingAs($officer)->postJson("/api/v1/risk/{$customer->id}/recalculate")->assertOk();
    }

    #[Test]
    public function risk_unlock_clears_profile_lock(): void
    {
        $customer = Customer::factory()->create();
        $officer = $this->createUser(UserRole::ComplianceOfficer);
        $profile = CustomerRiskProfile::factory()->create([
            'customer_id' => $customer->id,
            'locked_until' => now()->addYears(50),
            'locked_by' => $officer->id,
            'lock_reason' => 'EDD pending',
        ]);

        $this->actingAs($officer)->postJson("/api/v1/risk/{$customer->id}/unlock")->assertOk();

        $this->assertNull($profile->fresh()->locked_until);
    }

    #[Test]
    public function teller_is_forbidden_from_risk_api(): void
    {
        $customer = Customer::factory()->create();
        $teller = $this->createUser(UserRole::Teller);

        $this->actingAs($teller)->getJson("/api/v1/risk/{$customer->id}/history")->assertForbidden();
    }

    #[Test]
    public function compliance_alert_show_returns_alert_resource(): void
    {
        $alert = Alert::factory()->create();
        $officer = $this->createUser(UserRole::ComplianceOfficer);

        $this->actingAs($officer)->getJson("/api/v1/compliance/alerts/{$alert->id}")->assertOk();
    }

    #[Test]
    public function compliance_dashboard_returns_kpi_payload(): void
    {
        $officer = $this->createUser(UserRole::ComplianceOfficer);

        $this->actingAs($officer)->getJson('/api/v1/compliance/dashboard')->assertOk();
    }

    #[Test]
    public function compliance_edd_show_returns_record(): void
    {
        $record = EnhancedDiligenceRecord::factory()->create([
            'status' => EddStatus::PendingReview,
        ]);
        $officer = $this->createUser(UserRole::ComplianceOfficer);

        $this->actingAs($officer)->getJson("/api/v1/compliance/edd/{$record->id}")->assertOk();
    }

    #[Test]
    public function compliance_edd_reject_finalises_record(): void
    {
        $record = EnhancedDiligenceRecord::factory()->create([
            'status' => EddStatus::PendingReview,
        ]);
        $officer = $this->createUser(UserRole::ComplianceOfficer);

        $this->actingAs($officer)->postJson("/api/v1/compliance/edd/{$record->id}/reject", [
            'reason' => 'Documentation insufficient',
        ])->assertOk();

        $this->assertSame(EddStatus::Rejected, $record->fresh()->status);
    }

    #[Test]
    public function compliance_edd_reject_requires_reason(): void
    {
        $record = EnhancedDiligenceRecord::factory()->create([
            'status' => EddStatus::PendingReview,
        ]);
        $officer = $this->createUser(UserRole::ComplianceOfficer);

        $this->actingAs($officer)->postJson("/api/v1/compliance/edd/{$record->id}/reject", [])
            ->assertUnprocessable();
    }
}
