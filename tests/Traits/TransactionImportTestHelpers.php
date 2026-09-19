<?php

namespace Tests\Traits;

use App\Enums\RiskRating;
use App\Enums\TransactionImportStatus;
use App\Models\Counter;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\TillBalance;
use App\Models\TransactionImport;
use App\Services\Accounting\CurrencyPositionLockService;
use App\Services\Accounting\CurrencyPositionService;
use App\Services\Branch\TillBalanceManager;
use App\Services\Compliance\ComplianceService;
use App\Services\System\MathService;
use App\Services\ThresholdService;
use App\Services\Transaction\ExchangeCalculator;
use App\Services\Transaction\InitialStatusResolver;
use App\Services\Transaction\RateManagementService;
use App\Services\Transaction\TransactionCreationService;
use App\Services\Transaction\TransactionImportService;
use App\Services\Transaction\TransactionMonitoringService;

trait TransactionImportTestHelpers
{
    /**
     * Create the common fixtures needed for transaction-import tests.
     *
     * The MYR currency and till balance are created because completed Buy
     * transactions update the local-currency till balance in addition to the
     * foreign-currency till balance.
     *
     * @return array<string, mixed>
     */
    private function createFixtures(bool $createImport = true): array
    {
        $currency = Currency::factory()->create(['code' => 'USD']);
        Currency::factory()->create(['code' => 'MYR']);

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
        TillBalance::factory()->create([
            'till_id' => $counter->code,
            'currency_code' => 'MYR',
            'date' => today(),
            'opening_balance' => '100000',
        ]);

        $import = $createImport
            ? TransactionImport::factory()->create([
                'imported_by' => $customer->id,
                'status' => TransactionImportStatus::Pending->value,
            ])
            : null;

        return [
            'currency' => $currency,
            'customer' => $customer,
            'counter' => $counter,
            'import' => $import,
        ];
    }

    private function createImportService(
        string $threshold,
        ?ComplianceService $complianceService = null,
        ?RateManagementService $rateManagementService = null
    ): TransactionImportService {
        $thresholdService = $this->createMock(ThresholdService::class);
        $thresholdService->method('getAutoApproveThreshold')->willReturn($threshold);

        return new TransactionImportService(
            app(MathService::class),
            $complianceService ?? app(ComplianceService::class),
            app(CurrencyPositionService::class),
            app(TransactionMonitoringService::class),
            $thresholdService,
            app(TillBalanceManager::class),
            app(TransactionCreationService::class),
            $rateManagementService ?? app(RateManagementService::class),
            app(CurrencyPositionLockService::class),
            new InitialStatusResolver(app(MathService::class), $thresholdService),
            app(ExchangeCalculator::class),
        );
    }

    private function createCsv(string $row): string
    {
        $csv = tempnam(sys_get_temp_dir(), 'import');
        file_put_contents($csv, "customer_id,type,currency_code,quantity,rate,purpose,source_of_funds,till_id\n");
        file_put_contents($csv, "{$row}\n", FILE_APPEND);

        return $csv;
    }
}
