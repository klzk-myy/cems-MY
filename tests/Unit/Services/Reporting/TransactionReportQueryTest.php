<?php

namespace Tests\Unit\Services\Reporting;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Branch;
use App\Models\Transaction;
use App\Services\Reporting\TransactionReportQuery;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionReportQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_returns_only_completed_transactions(): void
    {
        $completed = Transaction::factory()->create(['status' => TransactionStatus::Completed->value]);
        $pending = Transaction::factory()->create(['status' => TransactionStatus::Pending->value]);
        $cancelled = Transaction::factory()->create(['status' => TransactionStatus::Cancelled->value]);

        $query = new TransactionReportQuery;
        $result = $query->completed()->pluck('id')->toArray();

        $this->assertContains($completed->id, $result);
        $this->assertNotContains($pending->id, $result);
        $this->assertNotContains($cancelled->id, $result);
    }

    public function test_for_date_range_filters_by_date_range_without_status_filter(): void
    {
        $inRangeCompleted = Transaction::factory()->create([
            'status' => TransactionStatus::Completed->value,
            'created_at' => Carbon::parse('2024-01-15'),
        ]);
        $inRangeCancelled = Transaction::factory()->create([
            'status' => TransactionStatus::Cancelled->value,
            'created_at' => Carbon::parse('2024-01-20'),
        ]);
        $outOfRange = Transaction::factory()->create([
            'status' => TransactionStatus::Completed->value,
            'created_at' => Carbon::parse('2024-02-01'),
        ]);

        $query = new TransactionReportQuery;
        $result = $query->forDateRange('2024-01-01', '2024-01-31')->pluck('id')->toArray();

        $this->assertContains($inRangeCompleted->id, $result);
        $this->assertContains($inRangeCancelled->id, $result);
        $this->assertNotContains($outOfRange->id, $result);
    }

    public function test_count_by_status_returns_correct_counts_per_status(): void
    {
        Transaction::factory()->count(2)->create(['status' => TransactionStatus::Completed->value]);
        Transaction::factory()->count(3)->create(['status' => TransactionStatus::Pending->value]);
        Transaction::factory()->count(4)->create(['status' => TransactionStatus::Cancelled->value]);

        $query = new TransactionReportQuery;
        $result = $query->countByStatus();

        $this->assertEquals(2, $result[TransactionStatus::Completed->value]);
        $this->assertEquals(3, $result[TransactionStatus::Pending->value]);
        $this->assertEquals(4, $result[TransactionStatus::Cancelled->value]);
    }

    public function test_sum_by_type_respects_branch_filtering(): void
    {
        $branch = Branch::factory()->create();
        Transaction::factory()->create([
            'branch_id' => $branch->id,
            'type' => TransactionType::Buy->value,
            'status' => TransactionStatus::Completed->value,
            'amount_foreign' => 100,
        ]);
        Transaction::factory()->create([
            'branch_id' => $branch->id,
            'type' => TransactionType::Sell->value,
            'status' => TransactionStatus::Completed->value,
            'amount_foreign' => 50,
        ]);
        Transaction::factory()->create([
            'type' => TransactionType::Buy->value,
            'status' => TransactionStatus::Completed->value,
            'amount_foreign' => 999,
        ]);
        Transaction::factory()->create([
            'branch_id' => $branch->id,
            'type' => TransactionType::Buy->value,
            'status' => TransactionStatus::Cancelled->value,
            'amount_foreign' => 200,
        ]);

        $query = new TransactionReportQuery;
        $result = $query->sumByType($branch->id);

        $this->assertEquals(100, $result['buy']);
        $this->assertEquals(50, $result['sell']);
    }

    public function test_base_query_filters_by_branch(): void
    {
        $branch = Branch::factory()->create();
        $branchTransaction = Transaction::factory()->create(['branch_id' => $branch->id]);
        $otherTransaction = Transaction::factory()->create();

        $query = new TransactionReportQuery;
        $result = $query->baseQuery($branch->id)->pluck('id')->toArray();

        $this->assertContains($branchTransaction->id, $result);
        $this->assertNotContains($otherTransaction->id, $result);
    }

    public function test_completed_sum_by_type(): void
    {
        Transaction::factory()->create(['type' => TransactionType::Buy->value, 'status' => TransactionStatus::Completed->value, 'amount_foreign' => 100]);
        Transaction::factory()->create(['type' => TransactionType::Sell->value, 'status' => TransactionStatus::Completed->value, 'amount_foreign' => 50]);
        Transaction::factory()->create(['type' => TransactionType::Buy->value, 'status' => TransactionStatus::Cancelled->value, 'amount_foreign' => 200]);

        $query = new TransactionReportQuery;
        $result = $query->sumByType();

        $this->assertEquals(100, $result['buy']);
        $this->assertEquals(50, $result['sell']);
    }
}
