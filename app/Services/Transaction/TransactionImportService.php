<?php

namespace App\Services\Transaction;

use App\Enums\TransactionImportStatus;
use App\Enums\TransactionType;
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
use App\Services\Branch\TillBalanceManager;
use App\Services\System\MathService;
use App\Services\Transaction\DTOs\ImportContext;
use App\Support\BcmathHelper;

class TransactionImportService
{
    public function __construct(
        protected MathService $mathService,
        protected TillBalanceManager $tillBalanceManager,
        protected TransactionCreationService $transactionCreationService,
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
            // No outer transaction: create() is self-transactional (phase-1
            // record + phase-2 side effects), matching the web/API path which
            // never wraps prepareAndCreate() in a transaction either. The
            // authoritative stock/floor checks live under create()'s row
            // locks; the gate checks here are advisory pre-checks. Wrapping
            // the whole row also breaks audit sealing: afterCommit seal jobs
            // dispatched by pre-validation/monitoring writes inside an outer
            // transaction can interleave unsealed predecessors at commit and
            // throw through DB::commit(), failing the row after it persisted.
            $data = $this->mapRow($row, (int) $context->import->id, $rowNumber);

            // Skip duplicate - already processed
            if ($this->isDuplicate($data['idempotency_key'])) {
                $context->recordSuccess();

                return;
            }

            $data = $this->validateRowShape($context, $data);

            // One booking pipeline for every entry path: eligibility gate,
            // exchange normalization, compliance gates and initial status all
            // run inside buildCreationContext() — the same orchestration the
            // web form, wizard and API use, so a gate added there can never be
            // bypassed by the import. Imports settle straight to the booked
            // till and never consume a teller allocation.
            $creationContext = $this->transactionCreationService->buildCreationContext(
                $context->importUser,
                $data,
                withAllocation: false,
            );

            $this->assertTillLiquidity($context, $data, $creationContext->tillBalance, $creationContext->amountMyr);

            $this->transactionCreationService->create(
                $creationContext,
                $context->importUser->id,
            );

            // Compliance monitoring is NOT run inline: create() dispatches
            // TransactionCreated, whose queued listener monitors the row,
            // recalculates the customer's risk score and stamps
            // last_transaction_at — the same post-booking pipeline as every
            // other creation path. An inline pass would only re-run the flag
            // checks and emit duplicate-prevention audit noise.

            $context->recordSuccess();
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
    private function mapRow(array $row, int $importId, int $rowNumber): array
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

        // Idempotency scope is (import, row): re-running THIS import dedupes
        // per row, identical rows in one file are legitimate separate
        // bookings and each import, and identical rows in a different file
        // (different import id) import independently — content-only hashing
        // silently dropped them.
        $data['idempotency_key'] = hash('sha256', $importId.'|'.$rowNumber.'|'.$encoded);

        return $data;
    }

    private function isDuplicate(string $idempotencyKey): bool
    {
        return Transaction::where('idempotency_key', $idempotencyKey)->exists();
    }

    /**
     * Validate required fields, customer, currency, transaction type, and
     * numeric bounds. Returns the normalized row data.
     *
     * @param  array<string, mixed>  $data
     * @return array{type: string, currency_code: string, quantity: string, rate: string, purpose: string, source_of_funds: string, source_of_wealth?: string, idempotency_key?: string, customer_id: int, till_id: string}
     */
    private function validateRowShape(ImportContext $context, array $data): array
    {
        if (empty($data['customer_id']) || empty($data['type']) || empty($data['currency_code']) ||
            empty($data['quantity']) || empty($data['rate']) || empty($data['purpose']) ||
            empty($data['source_of_funds'])) {
            throw new ImportValidationException('Missing required fields');
        }

        $data['customer_id'] = (int) $data['customer_id'];

        if (! Customer::whereKey($data['customer_id'])->exists()) {
            throw new CustomerNotFoundException($data['customer_id']);
        }

        if ($context->cachedCurrency($data['currency_code']) === null) {
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
        return $data;
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
     * Validate the till has sufficient live balance for the transaction type.
     * Compares against getExpectedBalance() (opening + today's movements)
     * — not the morning's opening_balance — so rows later in the file see
     * the deductions earlier rows committed. Advisory only: the rows run
     * without an outer transaction, so the authoritative stock/till check
     * happens inside create()'s locked booking transaction.
     *
     * @param  array<string, mixed>  $data
     */
    private function assertTillLiquidity(ImportContext $context, array $data, ?TillBalance $tillBalance, string $amountMyr): void
    {
        // Drawer-less rows have no drawer liquidity to check — position and
        // allocation checks still run inside the booking path.
        if ($tillBalance === null) {
            return;
        }

        if ($data['type'] === TransactionType::Buy->value) {
            // Buy: the drawer pays MYR out — check the live MYR balance.
            $counter = $this->resolveCounter($context, $data);
            $tillMyrBalance = $this->tillBalanceManager->currentBalance($counter, Currency::baseCurrency(), true);
            if (! $tillMyrBalance || $this->mathService->compare($tillMyrBalance->getExpectedBalance(), $amountMyr) < 0) {
                throw new ImportValidationException('Insufficient MYR balance in till for buy transaction');
            }
        } else {
            // Sell: the drawer pays foreign currency out — the till row was
            // already locked by the booking gate's validateTillBalance call.
            if ($this->mathService->compare($tillBalance->getExpectedBalance(), (string) $data['quantity']) < 0) {
                throw new ImportValidationException("Insufficient {$data['currency_code']} balance in till for sell transaction");
            }
        }
    }
}
