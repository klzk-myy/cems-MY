<?php

namespace App\Services\Transaction;

use App\Enums\TransactionImportStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Exceptions\Domain\CurrencyNotFoundException;
use App\Exceptions\Domain\CustomerNotFoundException;
use App\Exceptions\Domain\FileOperationException;
use App\Exceptions\Domain\ImportValidationException;
use App\Models\Counter;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\TillBalance;
use App\Models\Transaction;
use App\Models\TransactionImport;
use App\Models\User;
use App\Services\Accounting\CurrencyPositionLockService;
use App\Services\Accounting\CurrencyPositionService;
use App\Services\Branch\TillBalanceManager;
use App\Services\Compliance\ComplianceService;
use App\Services\System\MathService;
use App\Services\ThresholdService;
use App\Services\Traits\ExchangeCalculatorTrait;
use App\Services\Traits\TillBalanceTrait;
use App\Services\Transaction\DTOs\ImportContext;
use App\Services\Transaction\DTOs\InitialStatusResult;
use App\Support\BcmathHelper;
use App\ValueObjects\QuoteConvention;
use Illuminate\Support\Facades\DB;

class TransactionImportService
{
    use ExchangeCalculatorTrait, TillBalanceTrait;

    public function __construct(
        protected MathService $mathService,
        protected ComplianceService $complianceService,
        protected CurrencyPositionService $positionService,
        protected TransactionMonitoringService $monitoringService,
        protected ThresholdService $thresholdService,
        protected TillBalanceManager $tillBalanceManager,
        protected TransactionCreationService $transactionCreationService,
        protected RateManagementService $rateManagementService,
        protected CurrencyPositionLockService $positionLockService,
        protected InitialStatusResolver $statusResolver,
        protected ExchangeCalculator $exchangeCalculator,
    ) {}

    /**
     * Count the number of data rows in a CSV file (excluding header).
     *
     * @throws FileOperationException If the file cannot be opened
     */
    public function countRows(string $filePath): int
    {
        $handle = fopen($filePath, 'r');

        if (! $handle) {
            throw new FileOperationException("Could not open file for row counting: {$filePath}");
        }

        try {
            fgetcsv($handle); // Skip header row

            $count = 0;
            while (fgetcsv($handle) !== false) {
                $count++;
            }

            return $count;
        } finally {
            fclose($handle);
        }
    }

    /**
     * Process CSV file
     */
    public function process(TransactionImport $import, string $filePath): void
    {
        $import->update([
            'status' => TransactionImportStatus::Processing->value,
            'imported_at' => now(),
        ]);

        $handle = fopen($filePath, 'r');

        if (! $handle) {
            throw new FileOperationException("Could not open file: {$filePath}");
        }

        try {
            $header = fgetcsv($handle);

            if (! $header) {
                throw new ImportValidationException('CSV file is empty');
            }

            // Validate header
            $expectedHeader = ['customer_id', 'type', 'currency_code', 'quantity', 'rate', 'purpose', 'source_of_funds', 'till_id'];
            $headerLower = array_map(fn ($column): string => strtolower((string) $column), $header);
            if (count(array_diff($expectedHeader, $headerLower)) > 0) {
                throw new ImportValidationException('Invalid CSV header. Expected columns: '.implode(', ', $expectedHeader));
            }

            $rowNumber = 1;

            // Resolve the importing user once instead of once per row.
            /** @var User|null $importUser */
            $importUser = User::find($import->imported_by);
            if (! $importUser) {
                throw new ImportValidationException("Import user ID {$import->imported_by} not found");
            }

            $context = new ImportContext($import, $importUser);

            while (($row = fgetcsv($handle)) !== false) {
                $rowNumber++;
                $this->processRow($context, $row, $rowNumber);

                // Live progress counter for the polling results page. Each
                // row commits in its own transaction, so a job death mid-file
                // leaves processed_rows reflecting real progress; re-running
                // is safe because rows dedupe on idempotency_key.
                $import->increment('processed_rows');
            }

            $import->update([
                'status' => count($context->errors()) > 0 ? TransactionImportStatus::CompletedWithErrors->value : TransactionImportStatus::Completed->value,
                'success_count' => $context->successCount(),
                'error_count' => count($context->errors()),
                'error_details' => $context->errors(),
                'completed_at' => now(),
            ]);
        } finally {
            fclose($handle);
        }
    }

    /**
     * Process single row
     */
    protected function processRow(ImportContext $context, array $row, int $rowNumber): void
    {
        try {
            DB::transaction(function () use ($context, $row) {
                $data = $this->mapRow($row);

                // Skip duplicate - already processed
                if ($this->isDuplicate($data['idempotency_key'])) {
                    $context->recordSuccess();

                    return;
                }

                [$data, $customer, $convention] = $this->validateRowShape($context, $data);

                $counter = $this->resolveCounter($context, $data);
                $tillBalance = $this->tillBalanceManager->currentBalance($counter, $data['currency_code']);

                if (! $tillBalance) {
                    throw new ImportValidationException("Till {$data['till_id']} is not open for {$data['currency_code']}");
                }

                $this->assertMarketRate($data, $counter, $context->importUser->role);

                [$data, $amountMyr] = $this->convertRowAmount($data, $convention);

                $this->assertTillLiquidity($counter, $tillBalance, $data, $amountMyr);
                $this->assertSanctionsClear($customer);

                // Compliance checks
                $cddLevel = $this->complianceService->determineCDDLevel(
                    $amountMyr,
                    $customer
                );

                $initialStatus = $this->resolveInitialStatus($amountMyr, $customer);

                // Create transaction using TransactionCreationService to avoid duplicate logic.
                // The importing user is resolved once in process() and passed in.
                $transaction = $this->transactionCreationService->createForImport(
                    data: $data,
                    customer: $customer,
                    tillBalance: $tillBalance,
                    cddLevel: $cddLevel,
                    status: $initialStatus->status,
                    amountMyr: $amountMyr,
                    user: $context->importUser,
                    holdReason: $initialStatus->holdReason,
                );

                // Run compliance monitoring BEFORE commit (moved before commit)
                if ($initialStatus->status === TransactionStatus::Completed) {
                    $this->monitoringService->monitorTransaction($transaction);
                }

                $context->recordSuccess();
            });
        } catch (\Exception $e) {
            $context->recordError($rowNumber, $row, $e->getMessage());
        }
    }

    /**
     * Normalize a raw CSV row into the transaction data array, including the
     * idempotency key derived from the normalized columns.
     *
     * Expected columns: customer_id, type, currency_code, quantity, rate, purpose, source_of_funds, till_id
     *
     * @param  array<int, string|null>  $row
     * @return array{customer_id: string, type: string, currency_code: string, quantity: string, rate: string, purpose: string, source_of_funds: string, till_id: string, idempotency_key: string}
     */
    private function mapRow(array $row): array
    {
        // Pad short rows so malformed CSVs produce clean per-row errors instead
        // of PHP undefined-array-key warnings on every column access.
        $row = array_pad($row, 8, '');

        $data = [
            'customer_id' => trim($row[0]),
            'type' => trim($row[1]), // Buy or Sell
            'currency_code' => strtoupper(trim($row[2])),
            'quantity' => trim($row[3]),
            'rate' => trim($row[4]),
            'purpose' => trim($row[5]),
            'source_of_funds' => trim($row[6]),
            'till_id' => isset($row[7]) && ! empty(trim($row[7])) ? trim($row[7]) : 'MAIN',
        ];

        $encoded = json_encode($data);

        if ($encoded === false) {
            throw new ImportValidationException('Row data could not be encoded for idempotency key');
        }

        $data['idempotency_key'] = hash('sha256', $encoded);

        return $data;
    }

    private function isDuplicate(string $idempotencyKey): bool
    {
        return Transaction::where('idempotency_key', $idempotencyKey)->exists();
    }

    /**
     * Validate required fields, customer, currency, transaction type, and
     * numeric bounds. Returns the normalized data plus the resolved
     * customer and the currency's quote convention.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: array{type: string, currency_code: string, quantity: string, rate: string, purpose: string, source_of_funds: string, source_of_wealth?: string, idempotency_key?: string, customer_id: int, till_id: string}, 1: Customer, 2: QuoteConvention}
     */
    private function validateRowShape(ImportContext $context, array $data): array
    {
        if (empty($data['customer_id']) || empty($data['type']) || empty($data['currency_code']) ||
            empty($data['quantity']) || empty($data['rate']) || empty($data['purpose']) ||
            empty($data['source_of_funds'])) {
            throw new ImportValidationException('Missing required fields');
        }

        $data['customer_id'] = (int) $data['customer_id'];

        $customer = Customer::find($data['customer_id']);
        if (! $customer) {
            throw new CustomerNotFoundException((int) $data['customer_id']);
        }

        $currency = $context->cachedCurrency($data['currency_code']);
        if (! $currency) {
            throw new CurrencyNotFoundException($data['currency_code']);
        }

        if (TransactionType::tryFrom($data['type']) === null) {
            throw new ImportValidationException("Invalid transaction type: {$data['type']}. Must be '".TransactionType::Buy->value."' or '".TransactionType::Sell->value."'");
        }

        if (! is_numeric($data['quantity']) || BcmathHelper::lte((string) $data['quantity'], '0')) {
            throw new ImportValidationException("Invalid quantity: {$data['quantity']}");
        }

        if (! is_numeric($data['rate']) || BcmathHelper::lte((string) $data['rate'], '0')) {
            throw new ImportValidationException("Invalid rate: {$data['rate']}");
        }

        /** @var numeric-string $quantity */
        $quantity = (string) $data['quantity'];
        /** @var numeric-string $rate */
        $rate = (string) $data['rate'];

        // Upper bounds so a single malformed row cannot create unbounded entries.
        /** @var numeric-string $maxQuantity */
        $maxQuantity = (string) config('transactions.import.max_quantity');
        if (BcmathHelper::gt($quantity, $maxQuantity)) {
            throw new ImportValidationException("quantity {$quantity} exceeds maximum allowed ({$maxQuantity})");
        }

        /** @var numeric-string $maxRate */
        $maxRate = (string) config('transactions.import.max_rate');
        if (BcmathHelper::gt($rate, $maxRate)) {
            throw new ImportValidationException("rate {$rate} exceeds maximum allowed ({$maxRate})");
        }

        /** @var array{type: string, currency_code: string, quantity: string, rate: string, purpose: string, source_of_funds: string, source_of_wealth?: string, idempotency_key?: string, customer_id: int, till_id: string} $data */
        return [$data, $customer, QuoteConvention::for($currency)];
    }

    /**
     * Counter lookup is cached per import (on the context) to avoid one
     * query per row.
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveCounter(ImportContext $context, array $data): Counter
    {
        $counter = $context->cachedCounter((string) $data['till_id']);

        if (! $counter) {
            throw new ImportValidationException("Till {$data['till_id']} is not open for {$data['currency_code']}");
        }

        return $counter;
    }

    /**
     * Validate the rate against the current market rate so bulk imports
     * cannot book trades at aberrant rates (same guard the interactive
     * wizard applies). Skips rows where no market rate is configured.
     *
     * @param  array<string, mixed>  $data
     */
    private function assertMarketRate(array $data, Counter $counter, UserRole $role): void
    {
        $rateCheck = $this->rateManagementService->validateTransactionRate(
            (string) $data['rate'],
            $data['currency_code'],
            strtolower($data['type']),
            $counter->branch_id,
            $role
        );

        if (! $rateCheck['valid']) {
            throw new ImportValidationException($rateCheck['reason'] ?? 'Rate deviation exceeds maximum allowed');
        }
    }

    /**
     * Calculate local amount (single source of truth for the conversion).
     * CSV rates are unit-quoted per currencies.rate_unit; the stored
     * transaction keeps the normalized per-unit rate.
     *
     * @param  array{type: string, currency_code: string, quantity: string, rate: string, purpose: string, source_of_funds: string, source_of_wealth?: string, idempotency_key?: string, customer_id: int, till_id: string}  $data
     * @return array{0: array{type: string, currency_code: string, quantity: string, rate: string, purpose: string, source_of_funds: string, source_of_wealth?: string, idempotency_key?: string, customer_id: int, till_id: string}, 1: string} normalized data (rate rewritten per-unit) and amount_myr
     */
    private function convertRowAmount(array $data, QuoteConvention $convention): array
    {
        $exchangeResult = $this->resolveExchangeCalculator()->calculate(
            TransactionType::from((string) $data['type']),
            $data['currency_code'],
            (string) $data['quantity'],
            (string) $data['rate'],
            null,
            $convention
        );

        $data['rate'] = $exchangeResult['rate'];

        return [$data, $exchangeResult['amount_myr']];
    }

    /**
     * Validate till has sufficient balance for the transaction type.
     *
     * @param  array<string, mixed>  $data
     */
    private function assertTillLiquidity(Counter $counter, TillBalance $tillBalance, array $data, string $amountMyr): void
    {
        $quantity = (string) $data['quantity'];

        if ($data['type'] === TransactionType::Buy->value) {
            // Buy: customer buys foreign currency with MYR - check till has enough MYR
            $tillMyrBalance = $this->tillBalanceManager->currentBalance($counter, Currency::baseCurrency());
            if (! $tillMyrBalance || $this->mathService->compare((string) $tillMyrBalance->opening_balance, $amountMyr) < 0) {
                throw new ImportValidationException('Insufficient MYR balance in till for buy transaction');
            }
        } else {
            // Sell: customer sells foreign currency for MYR - check till has enough foreign currency
            if ($this->mathService->compare((string) $tillBalance->opening_balance, $quantity) < 0) {
                throw new ImportValidationException("Insufficient {$data['currency_code']} balance in till for sell transaction");
            }
        }
    }

    /**
     * Re-screen customer against sanctions lists per BNM requirements.
     */
    private function assertSanctionsClear(Customer $customer): void
    {
        if ($this->complianceService->checkSanctionMatch($customer)) {
            throw new ImportValidationException('Customer failed sanctions screening - cannot process import');
        }
    }

    /**
     * Initial status comes from the shared resolver so imports apply the
     * same auto-approve policy as the wizard and API paths: hold required,
     * High/unknown risk rating, or amount at the auto-approve threshold all
     * land in PendingApproval.
     */
    private function resolveInitialStatus(string $amountMyr, Customer $customer): InitialStatusResult
    {
        $holdCheck = $this->complianceService->requiresHold(
            $amountMyr,
            $customer
        );

        return $this->statusResolver->resolve(
            $amountMyr,
            $holdCheck->requiresHold,
            $customer->risk_rating,
            $holdCheck->reasons
        );
    }
}
