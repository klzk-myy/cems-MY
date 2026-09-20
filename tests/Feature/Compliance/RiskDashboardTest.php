<?php

namespace Tests\Feature\Compliance;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\RiskScoreSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RiskDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    #[Test]
    public function index_lists_customers_by_latest_snapshot_only(): void
    {
        // Latest snapshot is below the threshold — the old high snapshot
        // must not keep this customer on the high-risk list.
        $recovered = Customer::factory()->create(['full_name' => 'Recovered Customer']);
        RiskScoreSnapshot::factory()->create([
            'customer_id' => $recovered->id,
            'snapshot_date' => today()->subMonths(2),
            'overall_score' => 85,
            'overall_rating_label' => 'High',
        ]);
        RiskScoreSnapshot::factory()->create([
            'customer_id' => $recovered->id,
            'snapshot_date' => today(),
            'overall_score' => 30,
            'overall_rating_label' => 'Medium',
        ]);

        $highRisk = Customer::factory()->create(['full_name' => 'Still High Risk']);
        RiskScoreSnapshot::factory()->create([
            'customer_id' => $highRisk->id,
            'snapshot_date' => today(),
            'overall_score' => 75,
            'overall_rating_label' => 'High',
        ]);

        $this->actingAs($this->admin)
            ->get(route('compliance.risk-dashboard.index'))
            ->assertOk()
            ->assertSee('Still High Risk')
            ->assertDontSee('Recovered Customer');
    }

    #[Test]
    public function latest_snapshot_scope_ignores_overdue_history(): void
    {
        // Overdue old snapshot, current latest — must NOT need rescreening.
        $current = Customer::factory()->create();
        RiskScoreSnapshot::factory()->create([
            'customer_id' => $current->id,
            'snapshot_date' => today()->subMonths(3),
            'next_screening_date' => today()->subMonth(),
        ]);
        RiskScoreSnapshot::factory()->create([
            'customer_id' => $current->id,
            'snapshot_date' => today(),
            'next_screening_date' => today()->addMonths(3),
        ]);

        // Overdue latest snapshot — must need rescreening.
        $overdue = Customer::factory()->create();
        RiskScoreSnapshot::factory()->create([
            'customer_id' => $overdue->id,
            'snapshot_date' => today(),
            'next_screening_date' => today()->subDay(),
        ]);

        // No snapshots at all — must not appear.
        $neverScored = Customer::factory()->create();

        $ids = Customer::whereLatestSnapshotNeedsRescreening()->pluck('id');

        $this->assertTrue($ids->contains($overdue->id));
        $this->assertFalse($ids->contains($current->id));
        $this->assertFalse($ids->contains($neverScored->id));
    }

    #[Test]
    public function index_respects_the_threshold_parameter(): void
    {
        $borderline = Customer::factory()->create(['full_name' => 'Borderline Customer']);
        RiskScoreSnapshot::factory()->create([
            'customer_id' => $borderline->id,
            'snapshot_date' => today(),
            'overall_score' => 65,
            'overall_rating_label' => 'High',
        ]);

        $severe = Customer::factory()->create(['full_name' => 'Severe Customer']);
        RiskScoreSnapshot::factory()->create([
            'customer_id' => $severe->id,
            'snapshot_date' => today(),
            'overall_score' => 90,
            'overall_rating_label' => 'High',
        ]);

        $this->actingAs($this->admin)
            ->get(route('compliance.risk-dashboard.index', ['threshold' => 80]))
            ->assertOk()
            ->assertSee('Severe Customer')
            ->assertDontSee('Borderline Customer');
    }

    #[Test]
    public function index_requires_the_view_risk_dashboard_permission(): void
    {
        $accountant = User::factory()->create(['role' => UserRole::Accountant]);

        $this->actingAs($accountant)
            ->get(route('compliance.risk-dashboard.index'))
            ->assertForbidden();
    }
}
