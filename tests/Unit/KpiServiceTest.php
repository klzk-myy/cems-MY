<?php

namespace Tests\Unit;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Bi\AnalyticsSnapshotBuilder;
use App\Services\Bi\KpiService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class KpiServiceTest extends TestCase
{
    use RefreshDatabase;

    protected AnalyticsSnapshotBuilder $builder;

    protected KpiService $kpi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = app(AnalyticsSnapshotBuilder::class);
        $this->kpi = app(KpiService::class);
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

    /**
     * @param  numeric-string  $expected
     */
    protected function moneyEqual(string $expected, mixed $actual): void
    {
        $this->assertTrue(is_numeric($actual), "Expected {$expected}, got non-numeric value");
        $this->assertSame(0, bccomp($expected, (string) $actual, 4), "Expected {$expected}, got {$actual}");
    }

    #[Test]
    public function cards_sum_period_and_delta_against_prior_window(): void
    {
        $branch = Branch::factory()->create();

        // Prior window 2026-03-05..07: volume 100.
        $this->makeTransaction(['branch_id' => $branch->id, 'amount_myr' => '100.0000', 'created_at' => '2026-03-06 10:00:00']);

        // Current window 2026-03-08..10: buys 200+300, sell 100, 1 cancelled.
        $this->makeTransaction(['branch_id' => $branch->id, 'amount_myr' => '200.0000', 'created_at' => '2026-03-08 10:00:00']);
        $this->makeTransaction(['branch_id' => $branch->id, 'amount_myr' => '300.0000', 'created_at' => '2026-03-09 10:00:00']);
        $this->makeTransaction([
            'branch_id' => $branch->id,
            'type' => TransactionType::Sell->value,
            'amount_myr' => '100.0000',
            'rate' => '4.00000000',
            'prev_average_cost' => '3.50000000',
            'created_at' => '2026-03-10 10:00:00',
        ]);
        $this->makeTransaction(['branch_id' => $branch->id, 'status' => TransactionStatus::Cancelled->value, 'created_at' => '2026-03-10 12:00:00']);

        $this->builder->rebuildRange(Carbon::parse('2026-03-05'), Carbon::parse('2026-03-10'));

        $cards = $this->kpi->cards(Carbon::parse('2026-03-08'), Carbon::parse('2026-03-10'), null);

        // Volume 600 vs prior 100 → +500%.
        $this->moneyEqual('600.0000', $cards['volume_myr']['value']);
        $this->moneyEqual('500.00', $cards['volume_myr']['delta']);
        $this->assertSame(4, $cards['tx_count']['value']);
        // Margin on the sell: (4.0 - 3.5) * 25 = 12.5; margin % of sell volume = 12.5%.
        $this->moneyEqual('12.5000', $cards['realized_margin_myr']['value']);
        $this->moneyEqual('12.5000', $cards['margin_pct']['value']);
        // Avg ticket over completed = 600 / 3 = 200.
        $this->moneyEqual('200.0000', $cards['avg_ticket_myr']['value']);
        // Cancel rate: 1 cancelled of 4 transactions = 25%.
        $this->moneyEqual('25.0000', $cards['cancel_rate_pct']['value']);
        $this->assertSame(3, $cards['unique_customers']['value']);
    }

    #[Test]
    public function card_delta_is_null_when_prior_period_is_empty(): void
    {
        $branch = Branch::factory()->create();
        $this->makeTransaction(['branch_id' => $branch->id, 'created_at' => '2026-03-08 10:00:00']);
        $this->builder->rebuildRange(Carbon::parse('2026-03-08'), Carbon::parse('2026-03-10'));

        $cards = $this->kpi->cards(Carbon::parse('2026-03-08'), Carbon::parse('2026-03-10'), null);

        $this->assertNull($cards['volume_myr']['delta']);
        $this->assertNull($cards['tx_count']['delta']);
    }

    #[Test]
    public function daily_trends_zero_fill_days_without_rollups(): void
    {
        $branch = Branch::factory()->create();
        $this->makeTransaction(['branch_id' => $branch->id, 'amount_myr' => '50.0000', 'created_at' => '2026-03-08 10:00:00']);
        $this->makeTransaction(['branch_id' => $branch->id, 'amount_myr' => '70.0000', 'created_at' => '2026-03-10 10:00:00']);
        $this->builder->rebuildRange(Carbon::parse('2026-03-08'), Carbon::parse('2026-03-10'));

        $trends = $this->kpi->dailyTrends(Carbon::parse('2026-03-08'), Carbon::parse('2026-03-10'), null);

        $this->assertCount(3, $trends['labels']);
        $this->moneyEqual('50.0000', $trends['buy'][0]);
        $this->moneyEqual('0', $trends['buy'][1]);
        $this->moneyEqual('70.0000', $trends['buy'][2]);
    }

    #[Test]
    public function currency_mix_orders_by_volume_and_computes_shares(): void
    {
        $branch = Branch::factory()->create();
        $this->makeTransaction(['branch_id' => $branch->id, 'currency_code' => 'EUR', 'amount_myr' => '400.0000', 'created_at' => '2026-03-08 10:00:00']);
        $this->makeTransaction(['branch_id' => $branch->id, 'currency_code' => 'USD', 'amount_myr' => '600.0000', 'created_at' => '2026-03-08 11:00:00']);
        $this->builder->rebuildRange(Carbon::parse('2026-03-08'), Carbon::parse('2026-03-08'));

        $mix = $this->kpi->currencyMix(Carbon::parse('2026-03-08'), Carbon::parse('2026-03-08'), null);

        $this->assertSame('USD', $mix[0]['currency_code']);
        $this->assertSame('EUR', $mix[1]['currency_code']);
        $this->moneyEqual('60.00', $mix[0]['share_pct']);
        $this->moneyEqual('40.00', $mix[1]['share_pct']);
    }

    #[Test]
    public function branch_comparison_merges_margin_from_currency_rollup(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();

        $this->makeTransaction(['branch_id' => $branchA->id, 'amount_myr' => '500.0000', 'created_at' => '2026-03-08 10:00:00']);
        $this->makeTransaction([
            'branch_id' => $branchB->id,
            'type' => TransactionType::Sell->value,
            'amount_myr' => '300.0000',
            'rate' => '4.00000000',
            'prev_average_cost' => '3.00000000',
            'created_at' => '2026-03-08 10:00:00',
        ]);
        $this->builder->rebuildRange(Carbon::parse('2026-03-08'), Carbon::parse('2026-03-08'));

        $rows = collect($this->kpi->branchComparison(Carbon::parse('2026-03-08'), Carbon::parse('2026-03-08')))
            ->keyBy('branch_id');

        $this->moneyEqual('500.0000', $rows[$branchA->id]['volume_myr']);
        $this->moneyEqual('0', $rows[$branchA->id]['realized_margin_myr']);
        // (4.0 - 3.0) * 25 = 25 margin on branch B's sell.
        $this->moneyEqual('25.0000', $rows[$branchB->id]['realized_margin_myr']);
    }

    #[Test]
    public function status_mix_counts_outcomes(): void
    {
        $branch = Branch::factory()->create();
        $this->makeTransaction(['branch_id' => $branch->id, 'created_at' => '2026-03-08 10:00:00']);
        $this->makeTransaction(['branch_id' => $branch->id, 'status' => TransactionStatus::Cancelled->value, 'created_at' => '2026-03-08 11:00:00']);
        $this->makeTransaction(['branch_id' => $branch->id, 'status' => TransactionStatus::PendingApproval->value, 'created_at' => '2026-03-08 12:00:00']);
        $this->builder->rebuildRange(Carbon::parse('2026-03-08'), Carbon::parse('2026-03-08'));

        $mix = $this->kpi->statusMix(Carbon::parse('2026-03-08'), Carbon::parse('2026-03-08'), null);

        $this->assertSame(1, $mix['completed']);
        $this->assertSame(1, $mix['cancelled']);
        $this->assertSame(0, $mix['rejected']);
        $this->assertSame(1, $mix['other']);
    }

    #[Test]
    public function branch_scoped_queries_exclude_other_branches(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();
        $this->makeTransaction(['branch_id' => $branchA->id, 'amount_myr' => '100.0000', 'created_at' => '2026-03-08 10:00:00']);
        $this->makeTransaction(['branch_id' => $branchB->id, 'amount_myr' => '900.0000', 'created_at' => '2026-03-08 10:00:00']);
        $this->builder->rebuildRange(Carbon::parse('2026-03-08'), Carbon::parse('2026-03-08'));

        $cards = $this->kpi->cards(Carbon::parse('2026-03-08'), Carbon::parse('2026-03-08'), $branchA->id);

        $this->moneyEqual('100.0000', $cards['volume_myr']['value']);
        $this->assertSame(1, $cards['tx_count']['value']);
    }

    #[Test]
    public function currency_breakdown_sums_quantities_volumes_and_margin(): void
    {
        $branch = Branch::factory()->create();
        $this->makeTransaction(['branch_id' => $branch->id, 'currency_code' => 'USD', 'amount_myr' => '600.0000', 'quantity' => '150.0000', 'created_at' => '2026-03-08 10:00:00']);
        $this->makeTransaction([
            'branch_id' => $branch->id,
            'type' => TransactionType::Sell->value,
            'currency_code' => 'USD',
            'amount_myr' => '200.0000',
            'quantity' => '50.0000',
            'rate' => '4.00000000',
            'prev_average_cost' => '3.60000000',
            'created_at' => '2026-03-09 10:00:00',
        ]);
        $this->builder->rebuildRange(Carbon::parse('2026-03-08'), Carbon::parse('2026-03-09'));

        $rows = $this->kpi->currencyBreakdown(Carbon::parse('2026-03-08'), Carbon::parse('2026-03-09'), null);

        $this->assertSame('USD', $rows[0]['currency_code']);
        $this->moneyEqual('150.0000', $rows[0]['buy_qty']);
        $this->moneyEqual('50.0000', $rows[0]['sell_qty']);
        $this->moneyEqual('800.0000', $rows[0]['volume_myr']);
        // (4.0 - 3.6) * 50 = 20 margin on the sell.
        $this->moneyEqual('20.0000', $rows[0]['realized_margin_myr']);
        $this->moneyEqual('100.00', $rows[0]['share_pct']);
    }

    #[Test]
    public function currency_trend_isolates_one_currency(): void
    {
        $branch = Branch::factory()->create();
        $this->makeTransaction(['branch_id' => $branch->id, 'currency_code' => 'USD', 'amount_myr' => '100.0000', 'created_at' => '2026-03-08 10:00:00']);
        $this->makeTransaction(['branch_id' => $branch->id, 'currency_code' => 'EUR', 'amount_myr' => '900.0000', 'created_at' => '2026-03-08 10:00:00']);
        $this->builder->rebuildRange(Carbon::parse('2026-03-08'), Carbon::parse('2026-03-08'));

        $trend = $this->kpi->currencyTrend(Carbon::parse('2026-03-08'), Carbon::parse('2026-03-08'), null, 'USD');

        $this->moneyEqual('100.0000', $trend['buy'][0]);
        $this->moneyEqual('0', $trend['sell'][0]);
    }

    #[Test]
    public function operations_trend_reports_approval_seconds_and_rates(): void
    {
        $branch = Branch::factory()->create();
        $approved = $this->makeTransaction([
            'branch_id' => $branch->id,
            'created_at' => '2026-03-08 10:00:00',
        ]);
        $approved->forceFill(['approved_at' => $approved->created_at->copy()->addMinutes(2)])->saveQuietly();
        $this->makeTransaction(['branch_id' => $branch->id, 'status' => TransactionStatus::Cancelled->value, 'created_at' => '2026-03-08 11:00:00']);
        $this->builder->rebuildRange(Carbon::parse('2026-03-08'), Carbon::parse('2026-03-08'));

        $trend = $this->kpi->operationsTrend(Carbon::parse('2026-03-08'), Carbon::parse('2026-03-08'), null);

        $this->moneyEqual('120.00', $trend['avg_approval_seconds'][0]);
        $this->moneyEqual('50.0000', $trend['cancel_rate_pct'][0]);
        $this->moneyEqual('0', $trend['reject_rate_pct'][0]);
    }

    #[Test]
    public function teller_productivity_groups_per_teller(): void
    {
        $branch = Branch::factory()->create();
        $teller = User::factory()->teller()->create(['branch_id' => $branch->id]);
        $other = User::factory()->teller()->create(['branch_id' => $branch->id]);
        $this->makeTransaction(['branch_id' => $branch->id, 'user_id' => $teller->id, 'amount_myr' => '300.0000', 'created_at' => '2026-03-08 10:00:00']);
        $this->makeTransaction(['branch_id' => $branch->id, 'user_id' => $other->id, 'amount_myr' => '100.0000', 'created_at' => '2026-03-08 10:00:00']);
        $this->builder->rebuildRange(Carbon::parse('2026-03-08'), Carbon::parse('2026-03-08'));

        $rows = $this->kpi->tellerProductivity(Carbon::parse('2026-03-08'), Carbon::parse('2026-03-08'), null);

        $this->assertSame($teller->id, $rows[0]['user_id']);
        $this->moneyEqual('300.0000', $rows[0]['volume_myr']);
        $this->assertSame($teller->username, $rows[0]['teller_name']);
    }

    #[Test]
    public function top_customers_reads_monthly_rollup(): void
    {
        $branch = Branch::factory()->create();
        $big = Customer::factory()->create(['full_name' => 'Big Spender']);
        $small = Customer::factory()->create(['full_name' => 'Small Fry']);
        $this->makeTransaction(['branch_id' => $branch->id, 'customer_id' => $big->id, 'amount_myr' => '900.0000', 'created_at' => '2026-03-08 10:00:00']);
        $this->makeTransaction(['branch_id' => $branch->id, 'customer_id' => $small->id, 'amount_myr' => '100.0000', 'created_at' => '2026-03-09 10:00:00']);
        $this->builder->rebuildRange(Carbon::parse('2026-03-08'), Carbon::parse('2026-03-09'));

        $rows = $this->kpi->topCustomers(Carbon::parse('2026-03-01'), Carbon::parse('2026-03-31'), null);

        $this->assertSame('Big Spender', $rows[0]['full_name']);
        $this->moneyEqual('900.0000', $rows[0]['volume_myr']);
        $this->assertSame('Small Fry', $rows[1]['full_name']);
    }

    #[Test]
    public function active_customers_splits_new_vs_returning(): void
    {
        $branch = Branch::factory()->create();
        $old = Customer::factory()->create();
        $new = Customer::factory()->create();
        // $old traded in February and again in March; $new first appears in March.
        $this->makeTransaction(['branch_id' => $branch->id, 'customer_id' => $old->id, 'created_at' => '2026-02-15 10:00:00']);
        $this->makeTransaction(['branch_id' => $branch->id, 'customer_id' => $old->id, 'created_at' => '2026-03-08 10:00:00']);
        $this->makeTransaction(['branch_id' => $branch->id, 'customer_id' => $new->id, 'created_at' => '2026-03-09 10:00:00']);
        $this->builder->rebuildRange(Carbon::parse('2026-02-01'), Carbon::parse('2026-03-31'));

        $trend = $this->kpi->activeCustomersTrend(Carbon::parse('2026-02-01'), Carbon::parse('2026-03-31'), null);

        $this->assertSame(1, $trend['active'][0]); // Feb: only $old
        $this->assertSame(1, $trend['new'][0]);
        $this->assertSame(2, $trend['active'][1]); // Mar: $old + $new
        $this->assertSame(1, $trend['new'][1]); // only $new is new
    }

    #[Test]
    public function unique_customers_dedupes_across_days(): void
    {
        $branch = Branch::factory()->create();
        $customer = Customer::factory()->create();
        $this->makeTransaction(['branch_id' => $branch->id, 'customer_id' => $customer->id, 'created_at' => '2026-03-08 10:00:00']);
        $this->makeTransaction(['branch_id' => $branch->id, 'customer_id' => $customer->id, 'created_at' => '2026-03-09 10:00:00']);
        $this->builder->rebuildRange(Carbon::parse('2026-03-08'), Carbon::parse('2026-03-09'));

        $cards = $this->kpi->cards(Carbon::parse('2026-03-08'), Carbon::parse('2026-03-09'), null);

        $this->assertSame(1, $cards['unique_customers']['value']);
    }

    #[Test]
    public function teller_productivity_scopes_by_branch_without_ambiguous_columns(): void
    {
        $branch = Branch::factory()->create();
        $teller = User::factory()->create(['branch_id' => $branch->id]);

        $this->makeTransaction([
            'branch_id' => $branch->id,
            'user_id' => $teller->id,
            'created_at' => today(),
        ]);
        $this->builder->rebuildRange(today(), today());

        $rows = $this->kpi->tellerProductivity(today(), today(), $branch->id);

        $this->assertCount(1, $rows);
        $this->assertSame($teller->id, $rows[0]['user_id']);
    }
}
