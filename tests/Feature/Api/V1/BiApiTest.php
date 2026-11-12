<?php

namespace Tests\Feature\Api\V1;

use App\Models\Branch;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Bi\AnalyticsSnapshotBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BiApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    /**
     * @return array<int, string>
     */
    protected function biActions(): array
    {
        return ['dashboard', 'branches', 'currencies', 'rates', 'operations', 'customers', 'stock', 'forecast'];
    }

    #[Test]
    public function bi_endpoints_require_authentication_and_permission(): void
    {
        foreach ($this->biActions() as $action) {
            $this->getJson("/api/v1/bi/{$action}")->assertUnauthorized();
        }

        $teller = User::factory()->teller()->create();

        foreach ($this->biActions() as $action) {
            $this->actingAs($teller)->getJson("/api/v1/bi/{$action}")->assertForbidden();
        }
    }

    #[Test]
    public function dashboard_returns_cards_sparklines_and_a_period_echo(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->getJson('/api/v1/bi/dashboard')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('period.from', today()->subDays(29)->toDateString())
            ->assertJsonPath('period.to', today()->toDateString())
            ->assertJsonStructure([
                'period' => ['from', 'to'],
                'cards' => ['volume_myr', 'tx_count', 'realized_margin_myr'],
                'sparklines' => ['volume_myr', 'tx_count'],
                'last_computed_at',
            ]);
    }

    #[Test]
    public function bi_responses_are_branch_scoped_for_branch_viewers(): void
    {
        $manager = User::factory()->manager()->create();
        $other = Branch::factory()->create();

        Transaction::factory()->create([
            'branch_id' => $manager->branch_id,
            'amount_myr' => '100.0000',
            'created_at' => today(),
        ]);
        Transaction::factory()->create([
            'branch_id' => $other->id,
            'amount_myr' => '900.0000',
            'created_at' => today(),
        ]);

        app(AnalyticsSnapshotBuilder::class)->rebuildRange(today(), today());

        $response = $this->actingAs($manager)->getJson('/api/v1/bi/branches')->assertOk();

        $rows = $response->json('rows');
        $this->assertIsArray($rows);
        $branchIds = collect($rows)->pluck('branch_id')->all();

        $this->assertNotContains($other->id, $branchIds);
        $this->assertContains($manager->branch_id, $branchIds);
    }

    #[Test]
    public function operations_hides_teller_rows_without_the_grant(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)
            ->getJson('/api/v1/bi/operations')
            ->assertOk()
            ->assertJsonPath('tellers', []);
    }

    #[Test]
    public function rates_returns_spread_override_series_and_currency_rows(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->getJson('/api/v1/bi/rates')->assertOk();

        $response->assertJsonStructure([
            'period' => ['from', 'to'],
            'labels',
            'spread',
            'overrides',
            'override_rate_pct',
            'currencies',
        ]);
    }

    #[Test]
    public function stock_returns_exposure_and_compliance_snapshot(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->getJson('/api/v1/bi/stock')
            ->assertOk()
            ->assertJsonStructure([
                'period' => ['from', 'to'],
                'positions',
                'totals' => ['exposure_myr', 'unrealized_gain_loss_myr', 'unrealized_pct'],
                'compliance' => ['completed_transactions', 'flagged_transactions', 'flagged_rate_pct', 'open_alerts', 'open_flags', 'str_reports'],
            ]);
    }
}
