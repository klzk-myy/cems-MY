<?php

namespace Tests\Feature\Views;

use App\Enums\ComplianceCaseType;
use App\Enums\UserRole;
use App\Models\Compliance\ComplianceCase;
use App\Models\FiscalYear;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DummyDataReplacementTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function cases_index_does_not_show_hardcoded_assignee(): void
    {
        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        ComplianceCase::factory()->create();

        $response = $this->actingAs($user)->get(route('compliance.cases.index'));

        $response->assertStatus(200);
        $response->assertDontSee('Jane Doe', false);
    }

    #[Test]
    public function case_show_does_not_show_hardcoded_timeline_entry(): void
    {
        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $case = ComplianceCase::factory()->create();

        $response = $this->actingAs($user)->get(route('compliance.cases.show', $case));

        $response->assertStatus(200);
        $response->assertDontSee('2024-01-15 10:00:00 by Jane Doe', false);
    }

    #[Test]
    public function fiscal_years_does_not_show_hardcoded_dates(): void
    {
        $user = User::factory()->create(['role' => UserRole::Manager]);
        FiscalYear::factory()->forYear(2025)->create();

        $response = $this->actingAs($user)->get(route('accounting.fiscal-years'));

        $response->assertStatus(200);
        $response->assertDontSee('2026-01-01', false);
        $response->assertSee('FY 2025', false);
    }

    #[Test]
    public function cases_index_renders_real_model_data(): void
    {
        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        ComplianceCase::factory()->create([
            'case_type' => ComplianceCaseType::Investigation,
        ]);

        $response = $this->actingAs($user)->get(route('compliance.cases.index'));

        $response->assertStatus(200);
        $response->assertSee('Investigation', false);
    }

    #[Test]
    public function fiscal_years_renders_real_model_data(): void
    {
        $user = User::factory()->create(['role' => UserRole::Manager]);
        $fiscalYear = FiscalYear::factory()->create([
            'year_code' => 'FY2025',
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
        ]);

        $response = $this->actingAs($user)->get(route('accounting.fiscal-years'));

        $response->assertStatus(200);
        $response->assertSee('FY2025', false);
    }
}
