<?php

namespace App\Services;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Customer;
use App\Models\Transaction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Bulk Import Service
 *
 * Handles bulk import of customers and transactions from CSV files.
 * Provides validation, error reporting, and transactional processing.
 */
class BulkImportService
{
    /**
     * Import errors from the last import operation.
     */
    protected array $errors = [];

    /**
     * Import statistics from the last import operation.
     */
    protected array $stats = [];

    /**
     * Cache key for import status.
     */
    protected const STATUS_CACHE_KEY = 'bulk_import_status:';

    /**
     * Cache TTL in seconds (24 hours).
     */
    protected const STATUS_CACHE_TTL = 86400;

    /**
     * Import customers from CSV file.
     *
     * @param  string  $filePath  Path to CSV file
     * @param  int  $createdBy  User ID who initiated import
     * @return array ['success' => bool, 'stats' => array, 'errors' => array]
     */
    public function importCustomersFromCsv(string $filePath, int $createdBy): array
    {
        $this->errors = [];
        $this->stats = [
            'total_rows' => 0,
            'success_count' => 0,
            'error_count' => 0,
            'created' => 0,
            'updated' => 0,
        ];

        if (! file_exists($filePath)) {
            $this->errors[] = "File not found: {$filePath}";

            return $this->getResult(false);
        }

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            $this->errors[] = "Cannot open file: {$filePath}";

            return $this->getResult(false);
        }

        // Read header row
        $headers = fgetcsv($handle);
        if ($headers === false) {
            $this->errors[] = 'Cannot read CSV header';
            fclose($handle);

            return $this->getResult(false);
        }

        $this->stats['total_rows'] = count(file($filePath)) - 1;

        $customers = [];
        $rowNumber = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            $data = array_combine($headers, $row);

            // Validate row
            $validationError = $this->validateCustomerRow($data, $rowNumber);
            if ($validationError !== null) {
                $this->errors[] = $validationError;
                $this->stats['error_count']++;

                continue;
            }

            // Check if customer exists by ID number
            $existingCustomer = $this->findExistingCustomer($data);
            if ($existingCustomer) {
                // Update existing
                $existingCustomer->update([
                    'full_name' => $data['full_name'],
                    'id_type' => $data['id_type'],
                    'nationality' => $data['nationality'] ?? 'MY',
                    'occupation' => $data['occupation'] ?? null,
                    'address' => $data['address'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'email' => $data['email'] ?? null,
                    'risk_rating' => $data['risk_rating'] ?? 'LOW',
                    'cdd_level' => $data['cdd_level'] ?? 'Simplified',
                ]);
                $this->stats['updated']++;
            } else {
                // Create new
                $customers[] = $this->prepareCustomerData($data, $createdBy);
                $this->stats['created']++;
            }

            $this->stats['success_count']++;
        }

        fclose($handle);

        // Bulk insert new customers if any
        if (! empty($customers)) {
            try {
                Customer::insert($customers);
            } catch (\Exception $e) {
                $this->errors[] = 'Bulk insert failed: '.$e->getMessage();
                Log::error('Customer bulk import failed', [
                    'error' => $e->getMessage(),
                    'customers_count' => count($customers),
                ]);
            }
        }

        return $this->getResult(empty($this->errors));
    }

    /**
     * Import transactions from CSV file.
     *
     * @param  string  $filePath  Path to CSV file
     * @param  int  $createdBy  User ID who initiated import
     * @return array ['success' => bool, 'stats' => array, 'errors' => array]
     */
    public function importTransactionsFromCsv(string $filePath, int $createdBy): array
    {
        $this->errors = [];
        $this->stats = [
            'total_rows' => 0,
            'success_count' => 0,
            'error_count' => 0,
            'created' => 0,
            'failed' => 0,
        ];

        if (! file_exists($filePath)) {
            $this->errors[] = "File not found: {$filePath}";

            return $this->getResult(false);
        }

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            $this->errors[] = "Cannot open file: {$filePath}";

            return $this->getResult(false);
        }

        // Read header row
        $headers = fgetcsv($handle);
        if ($headers === false) {
            $this->errors[] = 'Cannot read CSV header';
            fclose($handle);

            return $this->getResult(false);
        }

        $this->stats['total_rows'] = count(file($filePath)) - 1;

        $transactions = [];
        $rowNumber = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            $data = array_combine($headers, $row);

            // Validate row
            $validationError = $this->validateTransactionRow($data, $rowNumber);
            if ($validationError !== null) {
                $this->errors[] = $validationError;
                $this->stats['error_count']++;
                $this->stats['failed']++;

                continue;
            }

            // Prepare transaction
            $transactions[] = $this->prepareTransactionData($data, $createdBy);
            $this->stats['success_count']++;
            $this->stats['created']++;

            // Batch insert every 100 rows
            if (count($transactions) >= 100) {
                $this->batchInsertTransactions($transactions);
                $transactions = [];
            }
        }

        // Insert remaining transactions
        if (! empty($transactions)) {
            $this->batchInsertTransactions($transactions);
        }

        fclose($handle);

        return $this->getResult(empty($this->errors));
    }

    /**
     * Validate a customer row.
     */
    public function validateCustomerRow(array $data, int $rowNumber): ?string
    {
        $required = ['full_name', 'id_type', 'id_number'];

        foreach ($required as $field) {
            if (empty($data[$field])) {
                return "Row {$rowNumber}: Missing required field '{$field}'";
            }
        }

        // Validate id_type
        $validIdTypes = ['IC', 'PASSPORT', 'MILITARY', 'PR'];
        if (! in_array(strtoupper($data['id_type']), $validIdTypes)) {
            return "Row {$rowNumber}: Invalid id_type '{$data['id_type']}'";
        }

        // Validate cdd_level if provided
        if (! empty($data['cdd_level'])) {
            $validCddLevels = ['Simplified', 'Standard', 'Enhanced'];
            if (! in_array($data['cdd_level'], $validCddLevels)) {
                return "Row {$rowNumber}: Invalid cdd_level '{$data['cdd_level']}'";
            }
        }

        // Validate risk_rating if provided
        if (! empty($data['risk_rating'])) {
            $validRiskRatings = ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'];
            if (! in_array(strtoupper($data['risk_rating']), $validRiskRatings)) {
                return "Row {$rowNumber}: Invalid risk_rating '{$data['risk_rating']}'";
            }
        }

        return null;
    }

    /**
     * Validate a transaction row.
     */
    public function validateTransactionRow(array $data, int $rowNumber): ?string
    {
        $required = ['type', 'currency_code', 'amount_foreign', 'rate', 'amount_local', 'customer_id', 'branch_id', 'counter_id'];

        foreach ($required as $field) {
            if (! isset($data[$field]) || $data[$field] === '') {
                return "Row {$rowNumber}: Missing required field '{$field}'";
            }
        }

        // Validate type
        $validTypes = ['buy', 'sell', 'Buy', 'Sell', 'BUY', 'SELL'];
        if (! in_array($data['type'], $validTypes)) {
            return "Row {$rowNumber}: Invalid transaction type '{$data['type']}'";
        }

        // Validate numeric fields
        $numericFields = ['amount_foreign', 'rate', 'amount_local'];
        foreach ($numericFields as $field) {
            if (! is_numeric($data[$field])) {
                return "Row {$rowNumber}: Field '{$field}' must be numeric";
            }
        }

        // Validate foreign amount is positive
        if (bccomp($data['amount_foreign'], '0', 2) <= 0) {
            return "Row {$rowNumber}: amount_foreign must be positive";
        }

        // Validate rate is positive
        if (bccomp($data['rate'], '0', 4) <= 0) {
            return "Row {$rowNumber}: rate must be positive";
        }

        // Validate customer exists
        if (! Customer::find($data['customer_id'])) {
            return "Row {$rowNumber}: Customer ID {$data['customer_id']} not found";
        }

        return null;
    }

    /**
     * Find existing customer by ID number.
     */
    protected function findExistingCustomer(array $data): ?Customer
    {
        if (empty($data['id_number'])) {
            return null;
        }

        $encryptedId = encrypt($data['id_number']);

        return Customer::where('id_number_encrypted', $encryptedId)->first();
    }

    /**
     * Prepare customer data for insertion.
     */
    protected function prepareCustomerData(array $data, int $createdBy): array
    {
        return [
            'full_name' => $data['full_name'],
            'id_type' => strtoupper($data['id_type']),
            'id_number_encrypted' => encrypt($data['id_number']),
            'nationality' => $data['nationality'] ?? 'MY',
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'occupation' => $data['occupation'] ?? null,
            'address' => $data['address'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'risk_rating' => strtoupper($data['risk_rating'] ?? 'LOW'),
            'cdd_level' => $data['cdd_level'] ?? 'Simplified',
            'is_active' => true,
            'created_by' => $createdBy,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Prepare transaction data for insertion.
     */
    protected function prepareTransactionData(array $data, int $createdBy): array
    {
        $type = strtolower($data['type']) === 'sell'
            ? TransactionType::Sell
            : TransactionType::Buy;

        return [
            'type' => $type,
            'currency_code' => strtoupper($data['currency_code']),
            'amount_foreign' => $data['amount_foreign'],
            'rate' => $data['rate'],
            'amount_local' => $data['amount_local'],
            'customer_id' => $data['customer_id'],
            'branch_id' => $data['branch_id'],
            'counter_id' => $data['counter_id'],
            'purpose' => $data['purpose'] ?? null,
            'source_of_funds' => $data['source_of_funds'] ?? null,
            'status' => TransactionStatus::PendingApproval,
            'created_by' => $createdBy,
            'created_at' => $data['transaction_date'] ?? now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Batch insert transactions.
     */
    protected function batchInsertTransactions(array $transactions): void
    {
        try {
            Transaction::insert($transactions);
        } catch (\Exception $e) {
            foreach ($transactions as $txn) {
                $this->errors[] = 'Failed to insert transaction: '.$e->getMessage();
                $this->stats['failed']++;
                $this->stats['created']--;
            }
            Log::error('Transaction batch insert failed', [
                'error' => $e->getMessage(),
                'count' => count($transactions),
            ]);
        }
    }

    /**
     * Get import errors.
     */
    public function getImportErrors(): array
    {
        return $this->errors;
    }

    /**
     * Store import status in cache.
     */
    public function storeImportStatus(string $jobId, array $status): void
    {
        Cache::put(self::STATUS_CACHE_KEY.$jobId, $status, self::STATUS_CACHE_TTL);
    }

    /**
     * Get import status from cache.
     */
    public function getImportStatus(string $jobId): ?array
    {
        return Cache::get(self::STATUS_CACHE_KEY.$jobId);
    }

    /**
     * Get the result array.
     */
    protected function getResult(bool $success): array
    {
        return [
            'success' => $success,
            'stats' => $this->stats,
            'errors' => $this->errors,
        ];
    }
}
