<?php

namespace App\Services\Transaction;

use App\Enums\TransactionImportStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Counter;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\TillBalance;
use App\Models\Transaction;
use App\Models\TransactionImport;
use App\Services\Accounting\CurrencyPositionLockService;
use App\Services\Accounting\CurrencyPositionService;
use App\Services\Accounting\TransactionAccountingService;
use App\Services\Branch\TillBalanceManager;
use App\Services\Compliance\ComplianceService;
use App\Services\System\MathService;
use App\Services\ThresholdService;
use App\Support\BcmathHelper;
use Illuminate\Support\Facades\DB;

class TransactionImportService
{
    protected TransactionImport $import;

    protected array $errors = [];

    protected int $successCount = 0;

    public function __construct(
        TransactionImport $import,
        protected MathService $mathService,
        protected ComplianceService $complianceService,
        protected CurrencyPositionService $positionService,
        protected TransactionMonitoringService $monitoringService,
        protected CurrencyPositionLockService $positionLockService,
        protected ThresholdService $thresholdService,
    ) {
        $this->import = $import;
    }

    /**
     * Process CSV file
     */
    public function process(string $filePath): void
    {
        $this->import->update([
            'status' => TransactionImportStatus::Processing->value,
            'imported_at' => now(),
        ]);

        $handle = fopen($filePath, 'r');

        if (! $handle) {
            throw new \Exception("Could not open file: {$filePath}");
        }

        try {
            $header = fgetcsv($handle);

            if (! $header) {
                throw new \Exception('CSV file is empty');
            }

            // Validate header
            $expectedHeader = ['customer_id', 'type', 'currency_code', 'amount_foreign', 'rate', 'purpose', 'source_of_funds', 'till_id'];
            $headerLower = array_map('strtolower', $header);
            if (count(array_diff($expectedHeader, $headerLower)) > 0) {
                throw new \Exception('Invalid CSV header. Expected columns: '.implode(', ', $expectedHeader));
            }

            $rowNumber = 1;

            while (($row = fgetcsv($handle)) !== false) {
                $rowNumber++;
                $this->processRow($row, $rowNumber);
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
    protected function processRow(array $row, int $rowNumber): void
    {
        try {
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

            $data['idempotency_key'] = md5(json_encode($data));

            // Validate required fields
            if (empty($data['customer_id']) || empty($data['type']) || empty($data['currency_code']) ||
                empty($data['amount_foreign']) || empty($data['rate']) || empty($data['purpose']) ||
                empty($data['source_of_funds'])) {
                throw new \Exception('Missing required fields');
            }

            // Validate customer exists
            $customer = Customer::find($data['customer_id']);
            if (! $customer) {
                throw new \Exception("Customer ID {$data['customer_id']} not found");
            }

            // Validate currency exists
            if (! Currency::where('code', $data['currency_code'])->exists()) {
                throw new \Exception("Currency {$data['currency_code']} not found");
            }

            // Validate transaction type
            if (TransactionType::tryFrom($data['type']) === null) {
                throw new \Exception("Invalid transaction type: {$data['type']}. Must be '".TransactionType::Buy->value."' or '".TransactionType::Sell->value."'");
            }

            // Validate numeric amounts
            if (! is_numeric($data['amount_foreign']) || BcmathHelper::lte($data['amount_foreign'], '0')) {
                throw new \Exception("Invalid amount_foreign: {$data['amount_foreign']}");
            }

            if (! is_numeric($data['rate']) || BcmathHelper::lte($data['rate'], '0')) {
                throw new \Exception("Invalid rate: {$data['rate']}");
            }

            // Validate till is open
            $counter = Counter::where('code', $data['till_id'])
                ->orWhere('id', $data['till_id'])
                ->first();

            if (! $counter) {
                throw new \Exception("Till {$data['till_id']} is not open for {$data['currency_code']}");
            }

            $tillBalance = app(TillBalanceManager::class)->currentBalance($counter, $data['currency_code']);

            if (! $tillBalance) {
                throw new \Exception("Till {$data['till_id']} is not open for {$data['currency_code']}");
            }

            // Calculate local amount
            $amountForeign = (string) $data['amount_foreign'];
            $rate = (string) $data['rate'];
            $amountLocal = $this->mathService->multiply($amountForeign, $rate);

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

            // Enforce auto-approve threshold: if amount exceeds threshold, require approval
            $threshold = $this->thresholdService->getAutoApproveThreshold();
            if ($this->mathService->compare($amountLocal, $threshold) >= 0) {
                $status = TransactionStatus::PendingApproval->value;
                $holdReason = $holdReason ?: 'Transaction amount exceeds auto-approve threshold';
            }

            // Create transaction within database transaction
            DB::beginTransaction();

            try {
                // Check idempotency to prevent duplicates
                if (! empty($data['idempotency_key'])) {
                    $existing = Transaction::where('idempotency_key', $data['idempotency_key'])->exists();
                    if ($existing) {
                        $this->successCount++;
                        DB::rollBack();

                        return;
                    }
                }

                // For sell transactions, check stock availability with findForUpdate()
                // so a zero-balance row is not created when there is no position yet.
                if ($data['type'] === TransactionType::Sell->value) {
                    $position = $this->positionLockService->findForUpdate(
                        $tillBalance->branch_id,
                        $data['currency_code']
                    );

                    if ($position === null || $this->mathService->compare($position->balance, $amountForeign) < 0) {
                        $availableBalance = $position ? $position->balance : '0';
                        throw new \Exception("Insufficient stock. Available: {$availableBalance} {$data['currency_code']}");
                    }
                }

                // Create transaction record
                $transaction = Transaction::create([
                    'customer_id' => $data['customer_id'],
                    'user_id' => $this->import->imported_by,
                    'till_id' => $data['till_id'],
                    'type' => $data['type'],
                    'currency_code' => $data['currency_code'],
                    'amount_foreign' => $amountForeign,
                    'amount_local' => $amountLocal,
                    'rate' => $rate,
                    'purpose' => $data['purpose'],
                    'source_of_funds' => $data['source_of_funds'],
                    'cdd_level' => $cddLevel,
                ]);

                $transaction->status = $status;
                $transaction->hold_reason = $holdReason;
                $transaction->approved_by = $approvedBy;
                $transaction->save();

                // Update currency position (if not pending approval)
                if ($status === TransactionStatus::Completed->value) {
                    $this->positionService->updatePosition(
                        $data['currency_code'],
                        $amountForeign,
                        $rate,
                        $data['type'],
                        $data['till_id']
                    );

                    // Update till balance (cash)
                    $this->updateTillBalance($tillBalance, $data['type'], $amountLocal, $amountForeign);

                    // Create accounting entries
                    app(TransactionAccountingService::class)->createImportAccountingEntries($transaction);
                }

                // Run compliance monitoring BEFORE commit (moved before commit)
                if ($status === TransactionStatus::Completed->value) {
                    $this->monitoringService->monitorTransaction($transaction);
                }

                DB::commit();

                $this->successCount++;
            } catch (\Exception $e) {
                // Only rollback if transaction wasn't committed
                // If we reach here after commit, the rollback has no effect but is harmless
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            $this->errors[] = [
                'row' => $rowNumber,
                'data' => $row,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Update till balance for transaction
     */
    protected function updateTillBalance(TillBalance $tillBalance, string $type, string $amountLocal, string $amountForeign): void
    {
        app(TillBalanceManager::class)->applyTransaction(
            $tillBalance,
            TransactionType::from($type),
            $amountLocal,
            $amountForeign,
            false
        );
    }
}
