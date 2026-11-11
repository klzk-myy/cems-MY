<?php

namespace Tests\Unit;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Transaction;
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
}
