<?php

namespace App\Services;

use App\Enums\CtosStatus;
use App\Models\CtosReport;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

/**
 * Service for CTOS (Cash Transaction Report) generation.
 *
 * BNM requires CTOS for all cash transactions (Buy AND Sell) >= RM 10,000.
 * This is separate from LCTR (Large Cash Transaction Report) which is >= RM 50,000.
 */
class CtosReportService
{
    public function __construct(
        protected AuditService $auditService,
        protected MathService $mathService,
        protected ComplianceService $complianceService,
    ) {}

    /**
     * Generate a CTOS report number.
     */
    public function generateCtosNumber(): string
    {
        $year = date('Y');
        $month = date('m');
        $prefix = "CTOS-{$year}{$month}-";

        $lastCtos = CtosReport::where('ctos_number', 'like', $prefix.'%')
            ->orderBy('ctos_number', 'desc')
            ->first();

        if ($lastCtos) {
            $lastNumber = (int) substr($lastCtos->ctos_number, -5);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix.str_pad($newNumber, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Create a CTOS report for a transaction.
     * Called immediately after transaction creation for qualifying transactions.
     */
    public function createFromTransaction(Transaction $transaction, int $createdBy): CtosReport
    {
        return DB::transaction(function () use ($transaction, $createdBy) {
            $customer = $transaction->customer;

            // Mask ID number for privacy (store last 4 digits only)
            $idNumber = $customer->id_number_encrypted;
            $maskedId = '****'.substr(decrypt($idNumber), -4);

            $ctosReport = CtosReport::create([
                'ctos_number' => $this->generateCtosNumber(),
                'transaction_id' => $transaction->id,
                'branch_id' => $transaction->branch_id,
                'customer_id' => $transaction->customer_id,
                'customer_name' => $customer->full_name,
                'id_type' => $customer->id_type,
                'id_number_masked' => $maskedId,
                'date_of_birth' => $customer->date_of_birth,
                'nationality' => $customer->nationality,
                'amount_local' => $transaction->amount_local,
                'amount_foreign' => $transaction->amount_foreign,
                'currency_code' => $transaction->currency_code,
                'transaction_type' => $transaction->type->value,
                'report_date' => $transaction->created_at->toDateString(),
                'status' => CtosStatus::Draft,
                'created_by' => $createdBy,
            ]);

            $this->auditService->logWithSeverity(
                'ctos_report_created',
                [
                    'user_id' => $createdBy,
                    'entity_type' => 'CtosReport',
                    'entity_id' => $ctosReport->id,
                    'new_values' => [
                        'ctos_number' => $ctosReport->ctos_number,
                        'transaction_id' => $transaction->id,
                        'customer_id' => $transaction->customer_id,
                        'amount_local' => $transaction->amount_local,
                    ],
                ],
                'INFO'
            );

            return $ctosReport;
        });
    }

    /**
     * Check if a transaction qualifies for CTOS reporting.
     * Applies to all cash transactions (Buy AND Sell) >= RM 10,000.
     */
    public function qualifiesForCtos(Transaction $transaction): bool
    {
        // Only cash transactions (Buy or Sell) qualify
        if (! in_array($transaction->type->value, ['Buy', 'Sell'], true)) {
            return false;
        }

        // Must be >= RM 10,000
        return $this->mathService->compare($transaction->amount_local, $this->complianceService::CTOS_THRESHOLD) >= 0;
    }
}
