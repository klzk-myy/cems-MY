<?php

namespace Tests\Unit\Services\Branch;

use App\Models\Counter;
use App\Models\TillBalance;
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
            'transaction_total_myr' => '500.00',
            'date' => today()->toDateString(),
            'opened_by' => $opener->id,
        ]);

        $usdBalance = TillBalance::create([
            'till_id' => $counter->code,
            'currency_code' => 'USD',
            'branch_id' => $branch->id,
            'opening_balance' => '1000.00',
            'closing_balance' => '950.00',
            'buy_quantity' => '100.00',
            'sell_quantity' => '150.00',
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

        // MYR: expected = opening + transaction_total_myr = 10000 + 500 = 10500;
        // actual closing 10500 -> zero variance.
        $myrRow = collect($result['currency_reconciliation'])->firstWhere('currency_code', 'MYR');
        $this->assertSame(0, bccomp($myrRow['expected'], '10500', 4));
        $this->assertSame(0, bccomp($myrRow['actual'], '10500', 4));
        $this->assertSame(0, bccomp($myrRow['variance'], '0', 4));

        // USD: expected = opening + buy_quantity - sell_quantity
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
            'transaction_total_myr' => '500.00',
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
}
