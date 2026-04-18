<?php

namespace App\Services;

use App\Enums\TransactionImportStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\TillBalance;
use App\Models\Transaction;
use App\Models\TransactionImport;
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
        protected AccountingService $accountingService,
        protected TransactionMonitoringService $monitoringService
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
            'started_at' => now(),
        ]);

        $handle = fopen($filePath, 'r');

        if (! $handle) {
            throw new \Exception("Could not open file: {$filePath}");
        }

        $header = fgetcsv($handle);

        if (! $header) {
            fclose($handle);
            throw new \Exception('CSV file is empty');
        }

        // Validate header
        $expectedHeader = ['customer_id', 'type', 'currency_code', 'amount_foreign', 'rate', 'purpose', 'source_of_funds', 'till_id'];
        $headerLower = array_map('strtolower', $header);
        if (count(array_diff($expectedHeader, $headerLower)) > 0) {
            fclose($handle);
            throw new \Exception('Invalid CSV header. Expected columns: '.implode(', ', $expectedHeader));
        }

        $rowNumber = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            $this->processRow($row, $rowNumber);
        }

        fclose($handle);

        $this->import->update([
            'status' => count($this->errors) > 0 ? TransactionImportStatus::CompletedWithErrors->value : TransactionImportStatus::Completed->value,
            'success_count' => $this->successCount,
            'error_count' => count($this->errors),
            'errors' => $this->errors,
            'completed_at' => now(),
        ]);
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
            $tillBalance = TillBalance::where('till_id', $data['till_id'])
                ->where('currency_code', $data['currency_code'])
                ->whereDate('date', today())
                ->whereNull('closed_at')
                ->first();

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

            if ($holdCheck['requires_hold']) {
                $status = TransactionStatus::PendingApproval->value;
                $holdReason = implode(', ', $holdCheck['reasons']);
            }

            // For sell transactions, check stock availability
            if ($data['type'] === TransactionType::Sell->value) {
                $position = $this->positionService->getPosition(
                    $data['currency_code'],
                    $data['till_id']
                );

                if (! $position || $this->mathService->compare($position->balance, $amountForeign) < 0) {
                    $availableBalance = $position ? $position->balance : '0';
                    throw new \Exception("Insufficient stock. Available: {$availableBalance} {$data['currency_code']}");
                }
            }

            // Create transaction within database transaction
            DB::beginTransaction();

            try {
                // Create transaction record
                $transaction = Transaction::create([
                    'customer_id' => $data['customer_id'],
                    'user_id' => $this->import->user_id,
                    'till_id' => $data['till_id'],
                    'type' => $data['type'],
                    'currency_code' => $data['currency_code'],
                    'amount_foreign' => $amountForeign,
                    'amount_local' => $amountLocal,
                    'rate' => $rate,
                    'purpose' => $data['purpose'],
                    'source_of_funds' => $data['source_of_funds'],
                    'status' => $status,
                    'hold_reason' => $holdReason,
                    'approved_by' => $approvedBy,
                    'cdd_level' => $cddLevel,
                ]);

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
                    $this->createAccountingEntries($transaction);
                }

                // Run compliance monitoring
                if ($status === TransactionStatus::Completed->value) {
                    $this->monitoringService->monitorTransaction($transaction);
                }

                DB::commit();

                $this->successCount++;
            } catch (\Exception $e) {
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
        $currentTotal = $tillBalance->transaction_total ?? '0';
        $foreignTotal = $tillBalance->foreign_total ?? '0';

        if ($type === TransactionType::Buy->value) {
            // Buying foreign: stock increases
            $tillBalance->update([
                'transaction_total' => $this->mathService->add($currentTotal, $amountLocal),
                'foreign_total' => $this->mathService->add($foreignTotal, $amountForeign),
            ]);
        } else {
            // Selling foreign: stock decreases
            $tillBalance->update([
                'transaction_total' => $this->mathService->add($currentTotal, $amountLocal),
                'foreign_total' => $this->mathService->subtract($foreignTotal, $amountForeign),
            ]);
        }
    }

    /**
     * Create accounting journal entries
     */
    protected function createAccountingEntries(Transaction $transaction): void
    {
        $entries = [];

        if ($transaction->type->value === TransactionType::Buy->value) {
            // Buy: Dr Foreign Currency Inventory, Cr Cash - MYR
            $entries = [
                [
                    'account_code' => '2000', // Foreign Currency Inventory
                    'debit' => $transaction->amount_local,
                    'credit' => '0',
                    'description' => "Buy {$transaction->amount_foreign} {$transaction->currency_code} @ {$transaction->rate}",
                ],
                [
                    'account_code' => '1000', // Cash - MYR
                    'debit' => '0',
                    'credit' => $transaction->amount_local,
                    'description' => "Payment for {$transaction->currency_code} purchase",
                ],
            ];
        } else {
            // Sell: Calculate gain/loss
            $position = $this->positionService->getPosition($transaction->currency_code);
            $avgCost = $position ? $position->avg_cost_rate : $transaction->rate;
            $costBasis = $this->mathService->multiply(
                (string) $transaction->amount_foreign,
                $avgCost
            );
            $revenue = $this->mathService->subtract(
                (string) $transaction->amount_local,
                $costBasis
            );

            $isGain = $this->mathService->compare($revenue, '0') >= 0;

            $entries = [
                [
                    'account_code' => '1000', // Cash - MYR
                    'debit' => $transaction->amount_local,
                    'credit' => '0',
                    'description' => "Sale of {$transaction->amount_foreign} {$transaction->currency_code}",
                ],
                [
                    'account_code' => '2000', // Foreign Currency Inventory
                    'debit' => '0',
                    'credit' => $costBasis,
                    'description' => "Cost of {$transaction->currency_code} sold",
                ],
            ];

            if ($isGain) {
                $entries[] = [
                    'account_code' => '5000', // Revenue - Forex Trading
                    'debit' => '0',
                    'credit' => $revenue,
                    'description' => "Gain on {$transaction->currency_code} sale",
                ];
            } else {
                $entries[] = [
                    'account_code' => '6000', // Expense - Forex Loss
                    'debit' => $this->mathService->multiply($revenue, '-1'),
                    'credit' => '0',
                    'description' => "Loss on {$transaction->currency_code} sale",
                ];
            }
        }

        $this->accountingService->createJournalEntry(
            $entries,
            'Transaction',
            $transaction->id,
            "Transaction #{$transaction->id} - {$transaction->type->value} {$transaction->currency_code}"
        );
    }
}
