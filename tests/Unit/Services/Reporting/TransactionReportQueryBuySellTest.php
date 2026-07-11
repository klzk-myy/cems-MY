<?php

namespace Tests\Unit\Services\Reporting;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Currency;
use App\Models\Transaction;
use App\Services\Reporting\TransactionReportQuery;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransactionReportQueryBuySellTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function buy_sell_summary_aggregates_by_currency(): void
    {
        $currency = Currency::factory()->create(['code' => 'USD']);

        Transaction::factory()->completed()->create([
            'currency_code' => $currency->code,
            'type' => TransactionType::Buy->value,
            'amount_local' => '1000.00',
            'amount_foreign' => '250.00',
            'created_at' => Carbon::today(),
        ]);

        Transaction::factory()->completed()->create([
            'currency_code' => $currency->code,
            'type' => TransactionType::Sell->value,
            'amount_local' => '500.00',
            'amount_foreign' => '100.00',
            'created_at' => Carbon::today(),
        ]);

        $query = app(TransactionReportQuery::class);
        $rows = $query->buySellSummary(
            Transaction::completed()->forDateRange(Carbon::today()->toDateString(), Carbon::today()->toDateString())->select('currency_code'),
            'currency_code',
            'amount_foreign',
            'amount_local'
        );

        $this->assertCount(1, $rows);
        $row = $rows->first();
        $this->assertSame('USD', $row->currency_code);
        $this->assertSame(1, (int) $row->buy_count);
        $this->assertSame('250', (string) $row->buy_volume);
        $this->assertSame('1000', (string) $row->buy_amount);
        $this->assertSame(1, (int) $row->sell_count);
        $this->assertSame('100', (string) $row->sell_volume);
        $this->assertSame('500', (string) $row->sell_amount);
    }

    #[Test]
    public function buy_sell_summary_without_group_by_returns_single_aggregate(): void
    {
        Transaction::factory()->completed()->create([
            'type' => TransactionType::Buy->value,
            'amount_local' => '1000.00',
            'created_at' => Carbon::today(),
        ]);

        Transaction::factory()->completed()->create([
            'type' => TransactionType::Sell->value,
            'amount_local' => '500.00',
            'created_at' => Carbon::today(),
        ]);

        $query = app(TransactionReportQuery::class);
        $rows = $query->buySellSummary(
            Transaction::completed()->forDateRange(Carbon::today()->toDateString(), Carbon::today()->toDateString())
        );

        $this->assertCount(1, $rows);
        $row = $rows->first();
        $this->assertSame(1, (int) $row->buy_count);
        $this->assertSame('1000', (string) $row->buy_volume);
        $this->assertSame(1, (int) $row->sell_count);
        $this->assertSame('500', (string) $row->sell_volume);
    }

    #[Test]
    public function buy_sell_volumes_splits_prefetched_collection(): void
    {
        $transactions = collect([
            Transaction::factory()->make([
                'type' => TransactionType::Buy->value,
                'amount_local' => '1000.00',
            ]),
            Transaction::factory()->make([
                'type' => TransactionType::Buy->value,
                'amount_local' => '500.00',
            ]),
            Transaction::factory()->make([
                'type' => TransactionType::Sell->value,
                'amount_local' => '300.00',
            ]),
        ]);

        $volumes = app(TransactionReportQuery::class)->buySellVolumes($transactions);

        $this->assertSame(2, $volumes['buy_count']);
        $this->assertSame('1500', $volumes['buy_volume']);
        $this->assertSame(1, $volumes['sell_count']);
        $this->assertSame('300', $volumes['sell_volume']);
    }

    #[Test]
    public function buy_sell_summary_excludes_cancelled_transactions(): void
    {
        Transaction::factory()->create([
            'status' => TransactionStatus::Cancelled->value,
            'type' => TransactionType::Buy->value,
            'amount_local' => '1000.00',
            'created_at' => Carbon::today(),
        ]);

        $query = app(TransactionReportQuery::class);
        $rows = $query->buySellSummary(
            Transaction::completed()->forDateRange(Carbon::today()->toDateString(), Carbon::today()->toDateString())
        );

        $this->assertCount(1, $rows);
        $row = $rows->first();
        $this->assertSame(0, (int) $row->buy_count);
        $this->assertSame(0, (int) $row->sell_count);
    }
}
