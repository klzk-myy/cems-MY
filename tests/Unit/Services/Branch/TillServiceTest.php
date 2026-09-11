<?php

namespace Tests\Unit\Services\Branch;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Counter;
use App\Models\TillBalance;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Branch\TillService;
use App\Services\System\MathService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TillServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function calculate_net_flow_only_counts_completed_transactions(): void
    {
        $counter = Counter::factory()->create(['code' => 'T-NET1']);

        Transaction::factory()->completed()->create([
            'till_id' => $counter->code,
            'currency_code' => 'USD',
            'type' => TransactionType::Buy->value,
            'amount_local' => '1000.00',
            'created_at' => now(),
        ]);

        // These never moved till cash - they must not distort the net flow
        Transaction::factory()->create([
            'status' => TransactionStatus::Cancelled->value,
            'till_id' => $counter->code,
            'currency_code' => 'USD',
            'type' => TransactionType::Sell->value,
            'amount_local' => '9999.00',
            'created_at' => now(),
        ]);
        Transaction::factory()->create([
            'status' => TransactionStatus::Failed->value,
            'till_id' => $counter->code,
            'currency_code' => 'USD',
            'type' => TransactionType::Sell->value,
            'amount_local' => '9999.00',
            'created_at' => now(),
        ]);
        Transaction::factory()->create([
            'status' => TransactionStatus::PendingApproval->value,
            'till_id' => $counter->code,
            'currency_code' => 'USD',
            'type' => TransactionType::Sell->value,
            'amount_local' => '9999.00',
            'created_at' => now(),
        ]);

        $service = new TillService(new MathService);
        $net = $service->calculateNetFlow($counter->code, 'USD');

        $this->assertSame(0, bccomp($net, '1000', 4));
    }

    #[Test]
    public function calculate_net_flow_subtracts_sell_amounts(): void
    {
        $counter = Counter::factory()->create(['code' => 'T-NET2']);

        Transaction::factory()->completed()->create([
            'till_id' => $counter->code,
            'currency_code' => 'USD',
            'type' => TransactionType::Buy->value,
            'amount_local' => '500.00',
            'created_at' => now(),
        ]);
        Transaction::factory()->completed()->create([
            'till_id' => $counter->code,
            'currency_code' => 'USD',
            'type' => TransactionType::Sell->value,
            'amount_local' => '200.00',
            'created_at' => now(),
        ]);

        $service = new TillService(new MathService);
        $net = $service->calculateNetFlow($counter->code, 'USD');

        $this->assertSame(0, bccomp($net, '300', 4));
    }

    #[Test]
    public function calculate_net_flow_is_zero_when_only_non_booked_transactions_exist(): void
    {
        $counter = Counter::factory()->create(['code' => 'T-NET3']);

        Transaction::factory()->create([
            'status' => TransactionStatus::Cancelled->value,
            'till_id' => $counter->code,
            'currency_code' => 'USD',
            'type' => TransactionType::Buy->value,
            'amount_local' => '1000.00',
            'created_at' => now(),
        ]);

        $service = new TillService(new MathService);
        $net = $service->calculateNetFlow($counter->code, 'USD');

        $this->assertSame(0, bccomp($net, '0', 4));
    }

    #[Test]
    public function generate_reconciliation_returns_view_expected_shape(): void
    {
        $counter = Counter::factory()->create(['code' => 'T-RECON1']);
        $branch = $this->createTestBranch();
        $opener = User::factory()->create();

        $myrBalance = TillBalance::create([
            'till_id' => $counter->code,
            'currency_code' => 'MYR',
            'branch_id' => $branch->id,
            'opening_balance' => '10000.00',
            'closing_balance' => '10500.00',
            'transaction_total' => '500.00',
            'date' => today()->toDateString(),
            'opened_by' => $opener->id,
        ]);

        $usdBalance = TillBalance::create([
            'till_id' => $counter->code,
            'currency_code' => 'USD',
            'branch_id' => $branch->id,
            'opening_balance' => '1000.00',
            'closing_balance' => '950.00',
            'buy_total_foreign' => '100.00',
            'sell_total_foreign' => '150.00',
            'date' => today()->toDateString(),
            'opened_by' => $opener->id,
        ]);

        $service = new TillService(new MathService);
        $result = $service->generateReconciliation(collect([$myrBalance, $usdBalance]));

        // Keys the reconciliation view renders (MoneyCast normalizes to 4dp)
        $this->assertSame(0, bccomp($result['opening_myr'], '10000', 4));
        $this->assertSame(0, bccomp($result['opening_fcy'], '1000', 4));
        $this->assertArrayHasKey('currency_reconciliation', $result);
        $this->assertArrayHasKey('total_myr_variance', $result);
        $this->assertArrayHasKey('total_fcy_variance', $result);
        $this->assertArrayHasKey('is_balanced', $result);

        // MYR: expected = opening + transaction_total = 10000 + 500 = 10500;
        // actual closing 10500 -> zero variance.
        $myrRow = collect($result['currency_reconciliation'])->firstWhere('currency_code', 'MYR');
        $this->assertSame(0, bccomp($myrRow['expected'], '10500', 4));
        $this->assertSame(0, bccomp($myrRow['actual'], '10500', 4));
        $this->assertSame(0, bccomp($myrRow['variance'], '0', 4));

        // USD: expected = opening + buy_total_foreign - sell_total_foreign
        // = 1000 + 100 - 150 = 950; actual closing 950 -> zero variance.
        $usdRow = collect($result['currency_reconciliation'])->firstWhere('currency_code', 'USD');
        $this->assertSame(0, bccomp($usdRow['expected'], '950', 4));
        $this->assertSame(0, bccomp($usdRow['actual'], '950', 4));
        $this->assertSame(0, bccomp($usdRow['variance'], '0', 4));

        $this->assertTrue($result['is_balanced']);
        $this->assertSame(0, bccomp($result['total_myr_variance'], '0', 4));
        $this->assertSame(0, bccomp($result['total_fcy_variance'], '0', 4));
    }

    #[Test]
    public function generate_reconciliation_reports_unbalanced_tills(): void
    {
        $counter = Counter::factory()->create(['code' => 'T-RECON2']);
        $branch = $this->createTestBranch();
        $opener = User::factory()->create();

        $myrBalance = TillBalance::create([
            'till_id' => $counter->code,
            'currency_code' => 'MYR',
            'branch_id' => $branch->id,
            'opening_balance' => '10000.00',
            'closing_balance' => '10400.00', // 100.00 short of expected 10500
            'transaction_total' => '500.00',
            'date' => today()->toDateString(),
            'opened_by' => $opener->id,
        ]);

        $service = new TillService(new MathService);
        $result = $service->generateReconciliation(collect([$myrBalance]));

        $myrRow = collect($result['currency_reconciliation'])->firstWhere('currency_code', 'MYR');
        $this->assertSame(0, bccomp($myrRow['variance'], '-100', 4));
        $this->assertFalse($result['is_balanced']);
        $this->assertSame(0, bccomp($result['total_myr_variance'], '-100', 4));
        $this->assertSame(0, bccomp($result['total_fcy_variance'], '0', 4));
    }

    #[Test]
    public function net_flow_buckets_by_approval_day_for_late_approved_transactions(): void
    {
        $counter = Counter::factory()->create(['code' => 'T-NET-APPR']);

        // Created yesterday, approved (cash moved) today.
        Transaction::factory()->create([
            'till_id' => $counter->code,
            'currency_code' => 'USD',
            'type' => TransactionType::Buy->value,
            'amount_local' => '500.00',
            'status' => TransactionStatus::Completed->value,
            'created_at' => now()->subDay(),
            'approved_at' => now(),
        ]);

        $service = new TillService(new MathService);

        $todayNet = $service->calculateNetFlow($counter->code, 'USD', now()->toDateString());
        $yesterdayNet = $service->calculateNetFlow($counter->code, 'USD', now()->subDay()->toDateString());

        $this->assertSame(0, bccomp($todayNet, '500', 2), 'Approval-day flow includes the late-approved transaction');
        $this->assertSame(0, bccomp($yesterdayNet, '0', 2), 'Request day must not be charged for cash that moved later');
    }
}
