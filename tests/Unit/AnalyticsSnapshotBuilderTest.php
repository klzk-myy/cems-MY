<?php

namespace Tests\Unit;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Jobs\BuildAnalyticsSnapshots;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Bi\AnalyticsSnapshotBuilder;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AnalyticsSnapshotBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected AnalyticsSnapshotBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = app(AnalyticsSnapshotBuilder::class);
    }

    /**
     * Create a transaction with controlled attributes. created_at is not
     * fillable, so it is stamped after creation via forceFill.
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

    protected function moneyEqual(string $expected, mixed $actual): void
    {
        $this->assertSame(0, bccomp((string) $actual, $expected, 4), "Expected {$expected}, got {$actual}");
    }

    #[Test]
    public function builds_branch_rollup_for_a_day(): void
    {
        $branch = $this->createTestBranch();
        $other = $this->createTestBranch();
        $day = '2026-03-10';

        // Branch A: 2 completed buys, 1 completed sell, 1 rejected, 1 cancelled.
        $this->makeTransaction(['branch_id' => $branch->id, 'type' => 'Buy', 'amount_myr' => '100.0000', 'created_at' => "{$day} 10:00:00"]);
        $this->makeTransaction(['branch_id' => $branch->id, 'type' => 'Buy', 'amount_myr' => '200.0000', 'created_at' => "{$day} 11:00:00"]);
        $this->makeTransaction(['branch_id' => $branch->id, 'type' => 'Sell', 'amount_myr' => '50.0000', 'created_at' => "{$day} 12:00:00"]);
        $this->makeTransaction(['branch_id' => $branch->id, 'status' => 'rejected', 'created_at' => "{$day} 12:30:00"]);
        $this->makeTransaction(['branch_id' => $branch->id, 'status' => 'cancelled', 'created_at' => "{$day} 13:00:00"]);
        // Other branch and other day must not bleed into the rollup.
        $this->makeTransaction(['branch_id' => $other->id, 'type' => 'Sell', 'amount_myr' => '80.0000', 'created_at' => "{$day} 14:00:00"]);
        $this->makeTransaction(['branch_id' => $branch->id, 'amount_myr' => '999.0000', 'created_at' => '2026-03-11 09:00:00']);

        $this->builder->rebuildDay(Carbon::parse($day));

        $row = DB::table('analytics_daily_branch')
            ->where('date', $day)->where('branch_id', $branch->id)->first();

        $this->assertNotNull($row);
        $this->assertEquals(5, $row->tx_count);
        $this->assertEquals(3, $row->completed_count);
        $this->assertEquals(1, $row->rejected_count);
        $this->assertEquals(1, $row->cancelled_count);
        $this->moneyEqual('300', $row->buy_volume_myr);
        $this->moneyEqual('50', $row->sell_volume_myr);
        $this->assertEquals(3, $row->unique_customers);
        $this->assertNotNull($row->computed_at);

        $otherRow = DB::table('analytics_daily_branch')
            ->where('date', $day)->where('branch_id', $other->id)->first();
        $this->assertEquals(1, $otherRow->tx_count);
        $this->moneyEqual('80', $otherRow->sell_volume_myr);

        // The next day's transaction produced its own row, not bleed-through.
        $nextDay = DB::table('analytics_daily_branch')
            ->where('date', '2026-03-11')->where('branch_id', $branch->id)->first();
        $this->assertNull($nextDay);
    }

    #[Test]
    public function currency_rollup_computes_quantities_and_realized_margin(): void
    {
        $branch = $this->createTestBranch();
        $day = '2026-03-10';

        // Sell 100 @ rate 4.70 with cost basis 4.50 → margin (4.70-4.50)*100 = 20.
        $this->makeTransaction([
            'branch_id' => $branch->id, 'type' => 'Sell',
            'quantity' => '100.0000', 'rate' => '4.70000000',
            'amount_myr' => '470.0000', 'prev_average_cost' => '4.50000000',
            'created_at' => "{$day} 10:00:00",
        ]);
        // Sell without a cost snapshot contributes volume but no margin.
        $this->makeTransaction([
            'branch_id' => $branch->id, 'type' => 'Sell',
            'quantity' => '50.0000', 'rate' => '4.80000000',
            'amount_myr' => '240.0000', 'prev_average_cost' => null,
            'created_at' => "{$day} 11:00:00",
        ]);
        // A buy and a different currency partition the grain correctly.
        $this->makeTransaction([
            'branch_id' => $branch->id, 'type' => 'Buy',
            'quantity' => '200.0000', 'amount_myr' => '800.0000',
            'created_at' => "{$day} 12:00:00",
        ]);
        $this->makeTransaction([
            'branch_id' => $branch->id, 'type' => 'Sell', 'currency_code' => 'EUR',
            'quantity' => '10.0000', 'rate' => '5.00000000', 'amount_myr' => '50.0000',
            'created_at' => "{$day} 13:00:00",
        ]);
        // A rejected transaction is excluded entirely (completed-only grain).
        $this->makeTransaction([
            'branch_id' => $branch->id, 'type' => 'Sell', 'status' => 'rejected',
            'amount_myr' => '9999.0000', 'created_at' => "{$day} 14:00:00",
        ]);

        $this->builder->rebuildDay(Carbon::parse($day));

        $usd = DB::table('analytics_daily_currency')
            ->where('date', $day)->where('branch_id', $branch->id)->where('currency_code', 'USD')->first();

        $this->assertNotNull($usd);
        $this->moneyEqual('200', $usd->buy_qty);
        $this->moneyEqual('150', $usd->sell_qty);
        $this->moneyEqual('800', $usd->buy_volume_myr);
        $this->moneyEqual('710', $usd->sell_volume_myr);
        $this->moneyEqual('20', $usd->realized_margin_myr);

        $eur = DB::table('analytics_daily_currency')
            ->where('date', $day)->where('currency_code', 'EUR')->first();
        $this->assertNotNull($eur);
        $this->moneyEqual('50', $eur->sell_volume_myr);
        $this->moneyEqual('0', $eur->realized_margin_myr);
    }

    #[Test]
    public function teller_rollup_tracks_volume_status_counts_and_turnaround(): void
    {
        $branch = $this->createTestBranch();
        $teller = User::factory()->create(['branch_id' => $branch->id]);
        $day = '2026-03-10';

        $this->makeTransaction([
            'branch_id' => $branch->id, 'user_id' => $teller->id,
            'amount_myr' => '150.0000',
            'created_at' => "{$day} 10:00:00",
            'approved_at' => "{$day} 11:30:00", // 5400s turnaround
        ]);
        $this->makeTransaction([
            'branch_id' => $branch->id, 'user_id' => $teller->id,
            'status' => 'cancelled', 'created_at' => "{$day} 12:00:00",
        ]);
        $this->makeTransaction([
            'branch_id' => $branch->id, 'user_id' => $teller->id,
            'rate_override' => true, 'created_at' => "{$day} 13:00:00",
        ]);
        // Another user's transactions stay out of this teller's row.
        $this->makeTransaction(['branch_id' => $branch->id, 'created_at' => "{$day} 14:00:00"]);

        $this->builder->rebuildDay(Carbon::parse($day));

        $row = DB::table('analytics_daily_teller')
            ->where('date', $day)->where('user_id', $teller->id)->first();

        $this->assertNotNull($row);
        $this->assertEquals(3, $row->tx_count);
        $this->moneyEqual('250', $row->volume_myr); // 150 completed + 100 completed (override row)
        $this->assertEquals(1, $row->cancelled_count);
        $this->assertEquals(1, $row->rate_override_count);
        $this->assertEquals(1, $row->approved_count);
        $this->moneyEqual('5400', $row->approval_seconds_total);
        $this->assertEquals($branch->id, $row->branch_id);
    }

    #[Test]
    public function monthly_customer_rollup_aggregates_the_whole_month(): void
    {
        $branch = $this->createTestBranch();
        $customer = $this->createTestCustomer();

        $this->makeTransaction([
            'branch_id' => $branch->id, 'customer_id' => $customer->id,
            'amount_myr' => '100.0000', 'created_at' => '2026-03-05 10:00:00',
        ]);
        $this->makeTransaction([
            'branch_id' => $branch->id, 'customer_id' => $customer->id,
            'currency_code' => 'EUR', 'amount_myr' => '200.0000',
            'type' => 'Sell', 'quantity' => '40.0000', 'rate' => '5.00000000',
            'prev_average_cost' => '4.75000000', // margin (5.00-4.75)*40 = 10
            'created_at' => '2026-03-20 10:00:00',
        ]);
        // Next month forms a separate rollup row.
        $this->makeTransaction([
            'branch_id' => $branch->id, 'customer_id' => $customer->id,
            'amount_myr' => '50.0000', 'created_at' => '2026-04-01 10:00:00',
        ]);
        // Cancelled transactions are excluded from the monthly rollup.
        $this->makeTransaction([
            'branch_id' => $branch->id, 'customer_id' => $customer->id,
            'status' => 'cancelled', 'amount_myr' => '777.0000',
            'created_at' => '2026-03-15 10:00:00',
        ]);

        $this->builder->rebuildRange(Carbon::parse('2026-03-01'), Carbon::parse('2026-04-01'));

        $march = DB::table('analytics_monthly_customer')
            ->where('month', '2026-03')->where('customer_id', $customer->id)->first();

        $this->assertNotNull($march);
        $this->assertEquals(2, $march->tx_count);
        $this->moneyEqual('300', $march->volume_myr);
        $this->moneyEqual('10', $march->realized_margin_myr);
        $this->assertEquals(2, $march->currencies_used);

        $april = DB::table('analytics_monthly_customer')
            ->where('month', '2026-04')->where('customer_id', $customer->id)->first();
        $this->assertEquals(1, $april->tx_count);
        $this->moneyEqual('50', $april->volume_myr);
    }

    #[Test]
    public function rebuild_is_idempotent_and_refreshes_changed_data(): void
    {
        $branch = $this->createTestBranch();
        $day = '2026-03-10';

        $txn = $this->makeTransaction([
            'branch_id' => $branch->id, 'amount_myr' => '100.0000',
            'created_at' => "{$day} 10:00:00",
        ]);

        $this->builder->rebuildDay(Carbon::parse($day));
        $this->builder->rebuildDay(Carbon::parse($day)); // second run: same result, no dupes

        $this->assertSame(1, DB::table('analytics_daily_branch')->where('date', $day)->count());

        // A later cancellation is absorbed on the next rebuild.
        $txn->forceFill(['status' => 'cancelled'])->saveQuietly();
        $this->builder->rebuildDay(Carbon::parse($day));

        $row = DB::table('analytics_daily_branch')->where('date', $day)->first();
        $this->assertEquals(0, $row->completed_count);
        $this->assertEquals(1, $row->cancelled_count);
        $this->moneyEqual('0', $row->buy_volume_myr);
        $this->assertSame(1, DB::table('analytics_daily_branch')->where('date', $day)->count());

        // Removing the source data removes the rollup row entirely.
        DB::table('transactions')->delete();
        $this->builder->rebuildDay(Carbon::parse($day));

        $this->assertSame(0, DB::table('analytics_daily_branch')->where('date', $day)->count());
        $this->assertSame(0, DB::table('analytics_daily_teller')->where('date', $day)->count());
        $this->assertSame(0, DB::table('analytics_daily_currency')->where('date', $day)->count());
    }

    #[Test]
    public function job_rebuilds_the_rolling_window_by_default(): void
    {
        $branch = $this->createTestBranch();
        $this->makeTransaction([
            'branch_id' => $branch->id,
            'created_at' => today()->subDays(2)->toDateTimeString(),
        ]);
        // Outside the default 7-day window.
        $this->makeTransaction([
            'branch_id' => $branch->id,
            'created_at' => today()->subDays(30)->toDateTimeString(),
        ]);

        (new BuildAnalyticsSnapshots)->handle($this->builder);

        $this->assertSame(1, DB::table('analytics_daily_branch')
            ->where('date', today()->subDays(2)->toDateString())->count());
        $this->assertSame(0, DB::table('analytics_daily_branch')
            ->where('date', today()->subDays(30)->toDateString())->count());

        $this->assertSame(
            'analytics-snapshots:auto:auto:7',
            (new BuildAnalyticsSnapshots)->uniqueId()
        );
    }

    #[Test]
    public function rebuild_command_runs_inline_over_a_range(): void
    {
        $branch = $this->createTestBranch();
        $this->makeTransaction([
            'branch_id' => $branch->id, 'created_at' => '2026-03-03 10:00:00',
        ]);

        $this->artisan('analytics:rebuild', ['--from' => '2026-03-01', '--to' => '2026-03-05'])
            ->assertSuccessful();

        $this->assertSame(1, DB::table('analytics_daily_branch')
            ->where('date', '2026-03-03')->count());
    }
}
