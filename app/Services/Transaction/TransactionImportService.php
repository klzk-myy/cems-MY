<?php

namespace App\Services\Transaction;

use App\Enums\RiskRating;
use App\Enums\TransactionImportStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Exceptions\Domain\CurrencyNotFoundException;
use App\Exceptions\Domain\CustomerNotFoundException;
use App\Exceptions\Domain\FileOperationException;
use App\Exceptions\Domain\ImportValidationException;
use App\Models\Counter;
use App\Models\Currency;
use App\Models\Customer;
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
use App\Support\BcmathHelper;
use Illuminate\Support\Facades\DB;

class TransactionImportService
{
    use ExchangeCalculatorTrait, TillBalanceTrait;

    protected ?TransactionImport $import = null;

    protected array $errors = [];

    protected int $successCount = 0;

    /** @var array<string, bool> Currency existence cache, keyed by code. */
    protected array $currencyCache = [];

    /** @var array<string, Counter|null> Counter lookup cache, keyed by till id. */
    protected array $counterCache = [];

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
        protected ?ExchangeCalculator $exchangeCalculator = null,
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
        $this->import = $import;
        $this->errors = [];
        $this->successCount = 0;
        $this->currencyCache = [];
        $this->counterCache = [];

        $this->import->update([
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
            $expectedHeader = ['customer_id', 'type', 'currency_code', 'amount_foreign', 'rate', 'purpose', 'source_of_funds', 'till_id'];
            $headerLower = array_map(fn ($column): string => strtolower((string) $column), $header);
            if (count(array_diff($expectedHeader, $headerLower)) > 0) {
                throw new ImportValidationException('Invalid CSV header. Expected columns: '.implode(', ', $expectedHeader));
            }

            $threshold = $this->thresholdService->getAutoApproveThreshold();
            $rowNumber = 1;

            // Resolve the importing user once instead of once per row.
            $importUser = User::find($import->imported_by);
            if (! $importUser) {
                throw new ImportValidationException("Import user ID {$import->imported_by} not found");
            }

            while (($row = fgetcsv($handle)) !== false) {
                $rowNumber++;
                $this->processRow($import, $row, $rowNumber, $threshold, $importUser);

                // Live progress counter for the polling results page. Each
                // row commits in its own transaction, so a job death mid-file
                // leaves processed_rows reflecting real progress; re-running
                // is safe because rows dedupe on idempotency_key.
                $import->increment('processed_rows');
            }

            $this->import->update([
                'status' => count($this->errors) > 0 ? TransactionImportStatus::CompletedWithErrors->value : TransactionImportStatus::Completed->value,
                'success_count' => $this->successCount,
                'error_count' => count($this->errors),
                'error_details' => $this->errors,
                'completed_at' => now(),
            ]);
        } finally {
            fclose($handle);
        }
    }

    /**
     * Process single row
     */
    protected function processRow(TransactionImport $import, array $row, int $rowNumber, string $threshold, User $importUser): void
    {
        try {
            DB::transaction(function () use ($row, $threshold, $importUser) {
                // Pad short rows so malformed CSVs produce clean per-row errors instead
                // of PHP undefined-array-key warnings on every column access.
                $row = array_pad($row, 8, '');

                // Expected columns: customer_id, type, currency_code, amount_foreign, rate, purpose, source_of_funds, till_id
                $data = [
                    'customer_id' => trim($row[0]),
                    'type' => trim($row[1]), // Buy or Sell
                    'currency_code' => strtoupper(trim($row[2])),
                    'amount_foreign' => trim($row[3]),
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

                // Check for duplicate transaction (idempotency)
                $existingTransaction = Transaction::where('idempotency_key', $data['idempotency_key'])->first();
                if ($existingTransaction) {
                    // Skip duplicate - already processed
                    $this->successCount++;

                    return;
                }

                // Validate required fields
                if (empty($data['customer_id']) || empty($data['type']) || empty($data['currency_code']) ||
                    empty($data['amount_foreign']) || empty($data['rate']) || empty($data['purpose']) ||
                    empty($data['source_of_funds'])) {
                    throw new ImportValidationException('Missing required fields');
                }

                $data['customer_id'] = (int) $data['customer_id'];

                // Validate customer exists
                $customer = Customer::find($data['customer_id']);
                if (! $customer) {
                    throw new CustomerNotFoundException((int) $data['customer_id']);
                }

                // Validate currency exists (cached per import - avoids one query per row)
                $currencyCode = $data['currency_code'];
                if (! isset($this->currencyCache[$currencyCode])) {
                    $this->currencyCache[$currencyCode] = Currency::where('code', $currencyCode)->exists();
                }
                if (! $this->currencyCache[$currencyCode]) {
                    throw new CurrencyNotFoundException($currencyCode);
                }

                // Validate transaction type
                if (TransactionType::tryFrom($data['type']) === null) {
                    throw new ImportValidationException("Invalid transaction type: {$data['type']}. Must be '".TransactionType::Buy->value."' or '".TransactionType::Sell->value."'");
                }

                // Validate numeric amounts
                if (! is_numeric($data['amount_foreign']) || BcmathHelper::lte($data['amount_foreign'], '0')) {
                    throw new ImportValidationException("Invalid amount_foreign: {$data['amount_foreign']}");
                }

                if (! is_numeric($data['rate']) || BcmathHelper::lte($data['rate'], '0')) {
                    throw new ImportValidationException("Invalid rate: {$data['rate']}");
                }

                // Upper bounds so a single malformed row cannot create unbounded entries.
                $maxAmountForeign = (string) config('transactions.import.max_amount_foreign');
                if (BcmathHelper::gt($data['amount_foreign'], $maxAmountForeign)) {
                    throw new ImportValidationException("amount_foreign {$data['amount_foreign']} exceeds maximum allowed ({$maxAmountForeign})");
                }

                $maxRate = (string) config('transactions.import.max_rate');
                if (BcmathHelper::gt($data['rate'], $maxRate)) {
                    throw new ImportValidationException("rate {$data['rate']} exceeds maximum allowed ({$maxRate})");
                }

                // Validate till is open (cached per import - avoids one query per row)
                $tillKey = (string) $data['till_id'];
                if (! array_key_exists($tillKey, $this->counterCache)) {
                    $this->counterCache[$tillKey] = Counter::where('code', $data['till_id'])
                        ->orWhere('id', $data['till_id'])
                        ->first();
                }
                $counter = $this->counterCache[$tillKey];

                if (! $counter) {
                    throw new ImportValidationException("Till {$data['till_id']} is not open for {$data['currency_code']}");
                }

                $tillBalance = $this->tillBalanceManager->currentBalance($counter, $data['currency_code']);

                if (! $tillBalance) {
                    throw new ImportValidationException("Till {$data['till_id']} is not open for {$data['currency_code']}");
                }

                // Validate the rate against the current market rate so bulk imports
                // cannot book trades at aberrant rates (same guard the interactive
                // wizard applies). Skips rows where no market rate is configured.
                $rateCheck = $this->rateManagementService->validateTransactionRate(
                    (string) $data['rate'],
                    $data['currency_code'],
                    strtolower($data['type']),
                    $counter->branch_id
                );

                if (! $rateCheck['valid']) {
                    throw new ImportValidationException($rateCheck['reason'] ?? 'Rate deviation exceeds maximum allowed');
                }

                // Calculate local amount (single source of truth for the conversion)
                $amountForeign = (string) $data['amount_foreign'];
                $rate = (string) $data['rate'];
                $amountLocal = $this->resolveExchangeCalculator()->calculate(
                    TransactionType::from((string) $data['type']),
                    $data['currency_code'],
                    $amountForeign,
                    $rate,
                )['amount_local'];

                // Validate till has sufficient balance for the transaction type
                if ($data['type'] === TransactionType::Buy->value) {
                    // Buy: customer buys foreign currency with MYR - check till has enough MYR
                    $tillMyrBalance = $this->tillBalanceManager->currentBalance($counter, 'MYR');
                    if (! $tillMyrBalance || $this->mathService->compare((string) $tillMyrBalance->opening_balance, $amountLocal) < 0) {
                        throw new ImportValidationException('Insufficient MYR balance in till for buy transaction');
                    }
                } else {
                    // Sell: customer sells foreign currency for MYR - check till has enough foreign currency
                    if ($this->mathService->compare((string) $tillBalance->opening_balance, $amountForeign) < 0) {
                        throw new ImportValidationException("Insufficient {$data['currency_code']} balance in till for sell transaction");
                    }
                }

                // Re-screen customer against sanctions lists per BNM requirements
                $screeningResult = $this->complianceService->checkSanctionMatch($customer);
                if ($screeningResult) {
                    throw new ImportValidationException('Customer failed sanctions screening - cannot process import');
                }

                // Compliance checks
                $cddLevel = $this->complianceService->determineCDDLevel(
                    $amountLocal,
                    $customer
                );

                // Check if requires hold/approval
                $holdCheck = $this->complianceService->requiresHold(
                    $amountLocal,
                    $customer
                );

                // Determine initial status
                $status = TransactionStatus::Completed->value;
                $holdReason = null;
                $approvedBy = null;

                if ($holdCheck->requiresHold) {
                    $status = TransactionStatus::PendingApproval->value;
                    $holdReason = implode(', ', $holdCheck->reasons);
                }

                // Only Low-risk customers auto-complete; Medium/High risk or
                // PEP requires approval per the auto-approve policy.
                if ($customer->risk_rating !== RiskRating::Low || $customer->is_pep_associate) {
                    $status = TransactionStatus::PendingApproval->value;
                    $pepReason = $customer->is_pep_associate
                        ? 'Customer is a PEP'
                        : 'Customer risk rating is '.$customer->risk_rating->value;
                    $holdReason = $holdReason ? "{$holdReason}; {$pepReason}" : $pepReason;
                }

                // Enforce auto-approve threshold: if amount exceeds threshold, require approval
                if ($this->mathService->compare($amountLocal, $threshold) >= 0) {
                    $status = TransactionStatus::PendingApproval->value;
                    $thresholdReason = 'Transaction amount exceeds auto-approve threshold';
                    $holdReason = $holdReason ? "{$holdReason}; {$thresholdReason}" : $thresholdReason;
                }

                // Create transaction using TransactionCreationService to avoid duplicate logic.
                // The importing user is resolved once in process() and passed in.
                $transaction = $this->transactionCreationService->createForImport(
                    data: $data,
                    customer: $customer,
                    tillBalance: $tillBalance,
                    cddLevel: $cddLevel,
                    status: TransactionStatus::from($status),
                    amountLocal: $amountLocal,
                    user: $importUser,
                    holdReason: $holdReason,
                );

                // Run compliance monitoring BEFORE commit (moved before commit)
                if ($status === TransactionStatus::Completed->value) {
                    $this->monitoringService->monitorTransaction($transaction);
                }

                $this->successCount++;
            });
        } catch (\Exception $e) {
            $this->errors[] = [
                'row' => $rowNumber,
                'data' => $row,
                'error' => $e->getMessage(),
            ];
        }
    }
}
