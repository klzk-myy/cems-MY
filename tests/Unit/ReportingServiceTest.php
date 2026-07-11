<?php

namespace Tests\Unit;

use App\Enums\TransactionType;
use App\Models\Currency;
use App\Models\CurrencyPosition;
use App\Models\Transaction;
use App\Services\Reporting\ReportingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReportingServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function generate_form_lmca_uses_configured_license_number(): void
    {
        config(['cems.license_number' => 'MSB-TEST-12345']);

        $service = $this->app->make(ReportingService::class);
        $result = $service->generateFormLMCA(now()->format('Y-m'));

        $this->assertEquals('MSB-TEST-12345', $result['license_number']);
    }

    #[Test]
    public function generate_msb2_data_delegates_aggregation_and_preserves_shape(): void
    {
        $date = '2026-07-10';
        $currency = Currency::factory()->create(['code' => 'USD', 'is_active' => true]);

        CurrencyPosition::factory()->create([
            'currency_code' => $currency->code,
            'balance' => '5000',
            'avg_cost_rate' => '4.5000',
        ]);

        Transaction::factory()->completed()->create([
            'currency_code' => $currency->code,
            'type' => TransactionType::Buy->value,
            'amount_local' => '1000.00',
            'amount_foreign' => '250.00',
            'rate' => '4.0000',
            'created_at' => Carbon::parse($date),
        ]);

        Transaction::factory()->completed()->create([
            'currency_code' => $currency->code,
            'type' => TransactionType::Sell->value,
            'amount_local' => '500.00',
            'amount_foreign' => '100.00',
            'rate' => '4.2000',
            'created_at' => Carbon::parse($date),
        ]);

        $service = $this->app->make(ReportingService::class);
        $result = $service->generateMSB2Data($date);

        $this->assertSame($date, $result['date']);
        $this->assertArrayHasKey('generated_at', $result);
        $this->assertArrayHasKey('data', $result);

        $row = collect($result['data'])->firstWhere('Currency', 'USD');
        $this->assertNotNull($row);
        $this->assertSame($date, $row['Date']);
        $this->assertSame('USD', $row['Currency']);
        $this->assertSame('1000', $row['Buy_Volume_MYR']);
        $this->assertSame(1, $row['Buy_Count']);
        $this->assertSame('500', $row['Sell_Volume_MYR']);
        $this->assertSame(1, $row['Sell_Count']);
        $this->assertSame('4', $row['Avg_Buy_Rate']);
        $this->assertSame('4.2', $row['Avg_Sell_Rate']);
        $this->assertSame('5000.0000', $row['Opening_Position']);
        $this->assertSame('5000.0000', $row['Closing_Position']);

        $emptyRow = collect($result['data'])->firstWhere('Currency', 'EUR');
        $this->assertNotNull($emptyRow);
        $this->assertSame('0', $emptyRow['Buy_Volume_MYR']);
        $this->assertSame(0, $emptyRow['Buy_Count']);
        $this->assertSame('0', $emptyRow['Sell_Volume_MYR']);
        $this->assertSame(0, $emptyRow['Sell_Count']);
    }
}
