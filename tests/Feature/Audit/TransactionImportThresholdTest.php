<?php

namespace Tests\Feature\Audit;

use App\Enums\RiskRating;
use App\Enums\TransactionImportStatus;
use App\Enums\TransactionStatus;
use App\Models\Counter;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\TillBalance;
use App\Models\TransactionImport;
use App\Services\Accounting\CurrencyPositionLockService;
use App\Services\Accounting\CurrencyPositionService;
use App\Services\Compliance\ComplianceService;
use App\Services\System\MathService;
use App\Services\ThresholdService;
use App\Services\Transaction\TransactionImportService;
use App\Services\Transaction\TransactionMonitoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionImportThresholdTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_marks_rows_above_auto_approve_threshold_as_pending(): void
    {
        ['customer' => $customer, 'import' => $import] = $this->createFixtures();
        $service = $this->createImportService($import, '5000');
        $csv = $this->createCsv("{$customer->id},Buy,USD,2000,4.0,Business,Salary,MAIN");

        try {
            $service->process($csv);

            $this->assertDatabaseHas('transactions', [
                'customer_id' => $customer->id,
                'status' => TransactionStatus::PendingApproval->value,
                'hold_reason' => 'Transaction amount exceeds auto-approve threshold',
            ]);
        } finally {
            unlink($csv);
        }
    }

    public function test_import_marks_rows_equal_to_auto_approve_threshold_as_pending(): void
    {
        ['customer' => $customer, 'import' => $import] = $this->createFixtures();
        $service = $this->createImportService($import, '4000');
        $csv = $this->createCsv("{$customer->id},Buy,USD,1000,4.0,Business,Salary,MAIN");

        try {
            $service->process($csv);

            $this->assertDatabaseHas('transactions', [
                'customer_id' => $customer->id,
                'status' => TransactionStatus::PendingApproval->value,
                'hold_reason' => 'Transaction amount exceeds auto-approve threshold',
            ]);
        } finally {
            unlink($csv);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function createFixtures(): array
    {
        $currency = Currency::factory()->create(['code' => 'USD']);
        $customer = Customer::factory()->create([
            'risk_rating' => RiskRating::Low->value,
        ]);
        $counter = Counter::factory()->create(['code' => 'MAIN']);
        TillBalance::factory()->create([
            'till_id' => $counter->code,
            'currency_code' => $currency->code,
            'date' => today(),
            'opening_balance' => '10000',
        ]);

        $import = TransactionImport::factory()->create([
            'imported_by' => $customer->id,
            'status' => TransactionImportStatus::Pending->value,
        ]);

        return [
            'currency' => $currency,
            'customer' => $customer,
            'counter' => $counter,
            'import' => $import,
        ];
    }

    private function createImportService(TransactionImport $import, string $threshold): TransactionImportService
    {
        $thresholdService = $this->createMock(ThresholdService::class);
        $thresholdService->method('getAutoApproveThreshold')->willReturn($threshold);

        return new TransactionImportService(
            $import,
            app(MathService::class),
            app(ComplianceService::class),
            app(CurrencyPositionService::class),
            app(TransactionMonitoringService::class),
            app(CurrencyPositionLockService::class),
            $thresholdService,
        );
    }

    private function createCsv(string $row): string
    {
        $csv = tempnam(sys_get_temp_dir(), 'import');
        file_put_contents($csv, "customer_id,type,currency_code,amount_foreign,rate,purpose,source_of_funds,till_id\n");
        file_put_contents($csv, "{$row}\n", FILE_APPEND);

        return $csv;
    }
}
