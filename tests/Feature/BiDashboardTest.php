<?php

namespace Tests\Feature;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Branch;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Bi\AnalyticsSnapshotBuilder;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BiDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // The dashboard's today-refresh is debounced through the cache;
        // a stale key from an earlier test would skip the rebuild.
        Cache::flush();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeTransaction(array $overrides = []): Transaction
    {
        $createdAt = $overrides['created_at'] ?? null;
        unset($overrides['created_at']);

        $transaction = Transaction::factory()->create(array_merge([
            'status' => TransactionStatus::Completed->value,
            'type' => TransactionType::Buy->value,
            'currency_code' => 'USD',
            'amount_myr' => '100.0000',
            'quantity' => '25.0000',
            'rate' => '4.00000000',
        ], $overrides));

        if ($createdAt !== null) {
            $transaction->forceFill(['created_at' => Carbon::parse($createdAt)])->saveQuietly();
        }

        return $transaction;
    }

    protected function rebuild(string $from, string $to): void
    {
        app(AnalyticsSnapshotBuilder::class)->rebuildRange(Carbon::parse($from), Carbon::parse($to));
    }

    #[Test]
    public function guests_are_redirected_to_login(): void
    {
        $this->get(route('bi.dashboard'))->assertRedirect();
    }

    #[Test]
    public function roles_without_the_permission_are_forbidden(): void
    {
        $teller = User::factory()->teller()->create();

        $this->actingAs($teller)
            ->get(route('bi.dashboard'))
            ->assertForbidden();
    }

    #[Test]
    public function admin_sees_branch_comparison_and_branch_selector(): void
    {
        $admin = User::factory()->admin()->create();
        $branch = Branch::factory()->create(['name' => 'Penang HQ']);
        $this->makeTransaction(['branch_id' => $branch->id, 'amount_myr' => '500.0000', 'created_at' => today()]);
        $this->rebuild(today()->toDateString(), today()->toDateString());

        $this->actingAs($admin)
            ->get(route('bi.dashboard'))
            ->assertOk()
            ->assertSee('Business Intelligence')
            ->assertSee('Branch Comparison')
            ->assertSee('name="branch_id"', false)
            ->assertSee('Penang HQ')
            ->assertSee('data-config', false);
    }

    #[Test]
    public function manager_sees_status_mix_and_not_branch_comparison(): void
    {
        $manager = User::factory()->manager()->create();
        $this->makeTransaction(['branch_id' => $manager->branch_id, 'amount_myr' => '250.0000', 'created_at' => today()]);
        $this->rebuild(today()->toDateString(), today()->toDateString());

        $this->actingAs($manager)
            ->get(route('bi.dashboard'))
            ->assertOk()
            ->assertSee('Status Mix')
            ->assertDontSee('Branch Comparison')
            ->assertDontSee('name="branch_id"', false);
    }

    #[Test]
    public function branch_scoped_viewer_never_sees_other_branch_volume(): void
    {
        $manager = User::factory()->manager()->create();
        $other = Branch::factory()->create();

        $this->makeTransaction(['branch_id' => $manager->branch_id, 'amount_myr' => '1111.0000', 'created_at' => today()]);
        $this->makeTransaction(['branch_id' => $other->id, 'amount_myr' => '9999.0000', 'created_at' => today()]);
        $this->rebuild(today()->toDateString(), today()->toDateString());

        $this->actingAs($manager)
            ->get(route('bi.dashboard'))
            ->assertOk()
            ->assertSee('1,111.00')
            ->assertDontSee('9,999.00');
    }

    #[Test]
    public function admin_branch_filter_scopes_the_whole_page(): void
    {
        $admin = User::factory()->admin()->create();
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();

        $this->makeTransaction(['branch_id' => $branchA->id, 'amount_myr' => '1111.0000', 'created_at' => today()]);
        $this->makeTransaction(['branch_id' => $branchB->id, 'amount_myr' => '9999.0000', 'created_at' => today()]);
        $this->rebuild(today()->toDateString(), today()->toDateString());

        $this->actingAs($admin)
            ->get(route('bi.dashboard', ['branch_id' => $branchA->id]))
            ->assertOk()
            ->assertSee('1,111.00')
            ->assertDontSee('9,999.00');
    }

    #[Test]
    public function date_filters_are_reflected_and_scoped(): void
    {
        $admin = User::factory()->admin()->create();
        $branch = Branch::factory()->create();
        $this->makeTransaction(['branch_id' => $branch->id, 'amount_myr' => '333.0000', 'created_at' => '2026-03-08 10:00:00']);
        $this->makeTransaction(['branch_id' => $branch->id, 'amount_myr' => '777.0000', 'created_at' => '2026-03-12 10:00:00']);
        $this->rebuild('2026-03-01', '2026-03-31');

        // Window covers only the 333 transaction.
        $this->actingAs($admin)
            ->get(route('bi.dashboard', ['start_date' => '2026-03-05', 'end_date' => '2026-03-10']))
            ->assertOk()
            ->assertSee('value="2026-03-05"', false)
            ->assertSee('333.00')
            ->assertDontSee('777.00');
    }

    #[Test]
    public function todays_rollups_refresh_on_first_page_load(): void
    {
        $admin = User::factory()->admin()->create();
        $branch = Branch::factory()->create();
        // No rebuild() call — the page's debounced refresh must pick it up.
        $this->makeTransaction(['branch_id' => $branch->id, 'amount_myr' => '4242.0000', 'created_at' => today()]);

        $this->actingAs($admin)
            ->get(route('bi.dashboard'))
            ->assertOk()
            ->assertSee('4,242.00');
    }

    #[Test]
    public function empty_period_renders_without_errors(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('bi.dashboard'))
            ->assertOk()
            ->assertSee('No analytics data yet');
    }

    #[Test]
    public function dashboard_cards_render_sparklines_and_drill_down_links(): void
    {
        $admin = User::factory()->admin()->create();
        $branch = Branch::factory()->create();

        foreach (range(1, 5) as $offset) {
            $this->makeTransaction([
                'branch_id' => $branch->id,
                'amount_myr' => (string) (100 * $offset),
                'created_at' => today()->subDays($offset),
            ]);
        }
        $this->rebuild(today()->subDays(5)->toDateString(), today()->toDateString());

        $content = (string) $this->actingAs($admin)->get(route('bi.dashboard'))->getContent();

        // Each card drills into the page that carries its detail view.
        $this->assertStringContainsString('/bi/branches', $content);
        $this->assertStringContainsString('/bi/currencies', $content);
        $this->assertStringContainsString('/bi/operations', $content);
        $this->assertStringContainsString('/bi/customers', $content);

        // Sparkline polylines are rendered for the series-bearing cards.
        $this->assertStringContainsString('<polyline', $content);
    }
}
