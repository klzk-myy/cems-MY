<?php

namespace Tests\Feature\Views;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\RiskScoreSnapshot;
use App\Models\TestResult;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChartPlaceholderTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function risk_trends_renders_real_chart_data(): void
    {
        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $customer = Customer::factory()->create();

        RiskScoreSnapshot::factory()->count(3)->create([
            'customer_id' => $customer->id,
            'overall_score' => 80,
            'snapshot_date' => now()->subMonths(1),
        ]);

        $response = $this->actingAs($user)->get('/compliance/risk-dashboard/trends');

        $response->assertStatus(200);
        $response->assertDontSee('[Chart Placeholder', false);
        $response->assertSee('High Risk Customer Trend', false);
        $response->assertSee('Alert Volume Trend', false);
    }

    #[Test]
    public function statistics_does_not_have_placeholder_comment(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);

        TestResult::factory()->create([
            'test_suite' => 'Feature',
            'status' => 'passed',
            'total_tests' => 100,
            'passed' => 85,
            'failed' => 15,
            'created_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($user)->get('/test-results/statistics');

        $response->assertStatus(200);
        $response->assertDontSee('Chart Placeholder', false);
        $response->assertSee('Pass Rate Trend', false);
    }
}
