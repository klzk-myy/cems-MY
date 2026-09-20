<?php

namespace Tests\Traits;

use App\Enums\RiskRating;
use App\Enums\TransactionImportStatus;
use App\Models\Counter;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\TillBalance;
use App\Models\TransactionImport;
use App\Models\User;
use App\Services\Branch\TillBalanceManager;
use App\Services\Contracts\RateManagementServiceInterface;
use App\Services\System\MathService;
use App\Services\ThresholdService;
use App\Services\Transaction\ExchangeCalculator;
use App\Services\Transaction\InitialStatusResolver;
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

        // The import runs the shared booking gate, so the importer must be a
        // real user bound to the counter's branch (till scoping is enforced).
        $user = User::factory()->manager()->create(['branch_id' => $counter->branch_id]);

        TillBalance::factory()->create([
            'till_id' => $counter->code,
            'currency_code' => $currency->code,
            'branch_id' => $counter->branch_id,
            'date' => today(),
            'opening_balance' => '10000',
        ]);
        TillBalance::factory()->create([
            'till_id' => $counter->code,
            'currency_code' => 'MYR',
            'branch_id' => $counter->branch_id,
            'date' => today(),
            'opening_balance' => '100000',
        ]);

        $import = $createImport
            ? TransactionImport::factory()->create([
                'imported_by' => $user->id,
                'status' => TransactionImportStatus::Pending->value,
            ])
            : null;

        return [
            'currency' => $currency,
            'customer' => $customer,
            'counter' => $counter,
            'user' => $user,
            'import' => $import,
        ];
    }

    private function createImportService(
        string $threshold,
        ?RateManagementServiceInterface $rateManagementService = null
    ): TransactionImportService {
        $thresholdService = $this->createMock(ThresholdService::class);
        $thresholdService->method('getAutoApproveThreshold')->willReturn($threshold);

        // The import delegates the booking gate to TransactionCreationService,
        // so rate/compliance mocks must be bound in the container to take
        // effect — constructor injection alone no longer reaches them.
        if ($rateManagementService) {
            $this->app->instance(RateManagementServiceInterface::class, $rateManagementService);
        }

        return new TransactionImportService(
            app(MathService::class),
            app(TransactionMonitoringService::class),
            app(TillBalanceManager::class),
            app(TransactionCreationService::class),
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
