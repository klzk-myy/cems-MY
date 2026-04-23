<?php

namespace App\Services;

use App\Enums\CddLevel;
use App\Enums\ComplianceFlagType;
use App\Enums\TransactionStatus;
use App\Models\Customer;
use App\Models\CustomerDocument;
use App\Models\FlaggedTransaction;
use App\Models\Transaction;
use App\Services\Risk\StructuringRiskService;
use App\Services\Risk\VelocityRiskService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Compliance Service
 *
 * Provides compliance-related operations for money changing transactions.
 * Handles Customer Due Diligence (CDD) level determination, sanctions screening,
 * velocity checks, structuring detection, aggregate transaction tracking,
 * and transaction hold decisions.
 *
 * This service ensures compliance with BNM regulations and AML/CFT requirements:
 * - BNM AML/CFT Policy (Revised 2025)
 * - PDPA 2010 (Amended 2024)
 * - MIA accounting standards
 */
class ComplianceService
{
    /**
     * Encryption service for sensitive data operations.
     */
    protected EncryptionService $encryptionService;

    /**
     * Math service for precise financial calculations.
     */
    protected MathService $mathService;

    /**
     * Sanction screening service for fuzzy matching.
     */
    protected ?CustomerScreeningService $screeningService;

    /**
     * Threshold service for dynamic threshold values.
     */
    protected ?ThresholdService $thresholdService;

    /**
     * BNM STR filing deadline in working days.
     */
    private const STR_FILING_DEADLINE_DAYS = 3;

    /**
     * Create a new ComplianceService instance.
     *
     * @param  EncryptionService  $encryptionService  Service for data encryption
     * @param  MathService  $mathService  Service for high-precision calculations
     * @param  CustomerScreeningService|null  $screeningService  Service for sanctions screening
     * @param  ThresholdService|null  $thresholdService  Service for dynamic thresholds
     */
    public function __construct(
        EncryptionService $encryptionService,
        MathService $mathService,
        ?CustomerScreeningService $screeningService = null,
        ?ThresholdService $thresholdService = null,
        ?VelocityRiskService $velocityRiskService = null,
        ?StructuringRiskService $structuringRiskService = null
    ) {
        $this->encryptionService = $encryptionService;
        $this->mathService = $mathService;
        $this->screeningService = $screeningService;
        $this->thresholdService = $thresholdService ?? new ThresholdService;
        $this->velocityRiskService = $velocityRiskService;
        $this->structuringRiskService = $structuringRiskService;
    }

    /**
     * Determine CDD level per pd-00.md 14C.12 for MSB:
     * - Simplified: < RM 3,000
     * - Specific: RM 3,000 - 10,000
     * - Standard: >= RM 10,000
     * - Enhanced: PEP, Sanction match, or High risk (risk-based, not amount-based)
     *
     * SECURITY NOTE: This method always uses the customer's actual record values
     * for PEP status and sanctions screening. No override parameters are allowed
     * to prevent bypassing Enhanced CDD requirements.
     *
     * @param  string  $amount  Transaction amount in MYR (as string for precision)
     * @param  Customer  $customer  The customer initiating the transaction
     * @return CddLevel The determined CDD level (Simplified, Specific, Standard, or Enhanced)
     */
    public function determineCDDLevel(string $amount, Customer $customer): CddLevel
    {
        // Always use customer record - no overrides allowed for security
        $pepStatus = $customer->pep_status ?? false;
        $sanctionStatus = $this->checkSanctionMatch($customer);

        // Enhanced Due Diligence triggers (risk-based per pd-00.md 14C.13)
        if ($pepStatus || $sanctionStatus || $customer->risk_rating === 'High') {
            return CddLevel::Enhanced;
        }

        // Standard CDD: >= RM 10,000 per pd-00.md 14C.12.2
        if ($this->mathService->compare($amount, $this->thresholdService->getStandardCddThreshold()) >= 0) {
            return CddLevel::Standard;
        }

        // Specific CDD: >= RM 3,000 per pd-00.md 14C.12.1
        if ($this->mathService->compare($amount, $this->thresholdService->getSpecificCddThreshold()) >= 0) {
            return CddLevel::Specific;
        }

        return CddLevel::Simplified;
    }

    /**
     * Check if customer matches any sanctions list entries.
     *
     * Uses CustomerScreeningService for fuzzy matching when available,
     * with fallback to direct database query for backward compatibility.
     *
     * @param  Customer  $customer  The customer to screen against sanctions lists
     * @return bool True if customer matches any sanctions entry, false otherwise
     */
    public function checkSanctionMatch(Customer $customer): bool
    {
        // Use the comprehensive screening service if available
        if ($this->screeningService !== null) {
            $result = $this->screeningService->screenCustomer($customer);

            return $result->action !== 'clear';
        }

        // Fallback to direct query (legacy behavior)
        $customerName = $customer->full_name;
        if (! is_string($customerName) || trim($customerName) === '') {
            return false;
        }

        // Escape LIKE wildcards to prevent false matches (e.g. % and _ in names)
        // Also escape backslash so our ESCAPE clause behaves predictably.
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $customerName);
        $pattern = '%'.$escaped.'%';

        $driver = DB::connection()->getDriverName();
        $operator = $driver === 'pgsql' ? 'ILIKE' : 'LIKE';
        $escapeClause = $driver === 'sqlite' ? " ESCAPE '\\'" : " ESCAPE '\\\\'";

        $matches = DB::table('sanction_entries')
            ->whereRaw("entity_name {$operator} ?{$escapeClause}", [$pattern])
            ->orWhereRaw("aliases {$operator} ?{$escapeClause}", [$pattern])
            ->count();

        return $matches > 0;
    }

    /**
     * Check transaction velocity for a customer over the last 24 hours.
     *
     * Calculates the total transaction amount within the past 24 hours
     * and determines if adding the new transaction would exceed the threshold.
     *
     * @param  int  $customerId  The ID of the customer to check
     * @param  string  $newAmount  The amount of the new transaction (as string for precision)
     * @return array<string, mixed> Velocity check results containing:
     *                              - amount_24h: string Total amount transacted in last 24 hours
     *                              - with_new_transaction: string Projected total including new transaction
     *                              - threshold_exceeded: bool Whether threshold of RM 50,000 would be exceeded
     *                              - threshold_amount: string The threshold amount (RM 50,000)
     */
    public function checkVelocity(int $customerId, string $newAmount): array
    {
        if ($this->velocityRiskService) {
            return $this->velocityRiskService->checkAmountThreshold($customerId, $newAmount);
        }

        $startTime = now()->subHours(24);
        $velocity = Transaction::where('customer_id', $customerId)
            ->where('created_at', '>=', $startTime)
            ->selectRaw('CAST(SUM(amount_local) AS CHAR) as total')
            ->value('total') ?? '0';

        $total = $this->mathService->add((string) $velocity, $newAmount);

        return [
            'amount_24h' => (string) $velocity,
            'with_new_transaction' => $total,
            'threshold_exceeded' => $this->mathService->compare($total, $this->thresholdService->getLargeTransactionThreshold()) >= 0,
            'threshold_amount' => $this->thresholdService->getLargeTransactionThreshold(),
        ];
    }

    /**
     * Detect potential structuring behavior for a customer.
     *
     * STRUCTURING (SMURFING) EXPLANATION:
     * Structuring is the practice of deliberately breaking up large transactions
     * into smaller amounts to avoid reporting thresholds and regulatory scrutiny.
     * This is a serious AML/CFT violation that BNM requires MSBs to detect and report.
     *
     * DETECTION LOGIC:
     * This method checks for 3 or more transactions under the structuring sub-threshold
     * (default: RM 3,000) within a 1-hour window. The logic is:
     *
     * 1. Look back 1 hour from current time
     * 2. Count all transactions with amount_local < structuring_sub_threshold
     * 3. If count >= 3, flag as potential structuring
     *
     * WHY CHECK BELOW THRESHOLD?
     * - Structuring specifically involves transactions BELOW reporting thresholds
     * - If transactions were >= RM 3,000, they would already trigger Standard CDD
     * - The pattern of multiple small transactions is the red flag, not the amounts
     * - Example: 3 transactions of RM 2,900 each = RM 8,700 total (above RM 3,000 threshold)
     *            but each individually avoids Standard CDD requirements
     *
     * BNM REQUIREMENTS:
     * - MSBs must monitor for structuring patterns
     * - Aggregation of related transactions must be considered
     * - Suspicious structuring must be reported via STR
     *
     * CONFIGURATION:
     * - Threshold: config('thresholds.structuring.sub_threshold', '3000')
     * - Minimum transactions: 3 (hardcoded per BNM guidance)
     * - Time window: 1 hour (hardcoded per BNM guidance)
     *
     * @param  int  $customerId  The ID of the customer to check
     * @return bool True if structuring behavior is detected (3+ small transactions in 1 hour)
     */
    public function checkStructuring(int $customerId): bool
    {
        if ($this->structuringRiskService) {
            return $this->structuringRiskService->isStructuring($customerId);
        }

        $oneHourAgo = now()->subHour();
        $smallTransactions = Transaction::where('customer_id', $customerId)
            ->where('created_at', '>=', $oneHourAgo)
            ->where('amount_local', '<', $this->thresholdService->getStructuringSubThreshold())
            ->count();

        return $smallTransactions >= 3;
    }

    /**
     * Determine if a transaction requires a compliance hold.
     *
     * Evaluates transaction and customer risk factors to decide if the
     * transaction should be held pending compliance review. Returns a list
     * of reasons for the hold decision.
     *
     * Hold triggers include:
     * - Large amounts (≥ RM 50,000)
     * - PEP status
     * - Sanctions list match
     * - High risk customer rating
     *
     * @param  string  $amount  Transaction amount in MYR (as string for precision)
     * @param  Customer  $customer  The customer initiating the transaction
     * @return array<string, mixed> Hold decision containing:
     *                              - requires_hold: bool Whether the transaction must be held
     *                              - reasons: array<string> List of ComplianceFlagType values as strings
     */
    public function requiresHold(string $amount, Customer $customer): array
    {
        $reasons = [];

        if ($this->mathService->compare($amount, $this->thresholdService->getLargeTransactionThreshold()) >= 0) {
            $reasons[] = ComplianceFlagType::EddRequired->value;
        }

        if ($customer->pep_status) {
            $reasons[] = ComplianceFlagType::PepStatus->value;
        }

        if ($this->checkSanctionMatch($customer)) {
            $reasons[] = ComplianceFlagType::SanctionMatch->value;
        }

        if ($customer->risk_rating === 'High') {
            $reasons[] = ComplianceFlagType::HighRiskCustomer->value;
        }

        return [
            'requires_hold' => ! empty($reasons),
            'reasons' => $reasons,
        ];
    }

    /**
     * Check aggregate transactions for a customer that should be combined.
     *
     * BNM AML/CFT requires tracking related transactions that together exceed thresholds,
     * even if individually below thresholds. This detects potential structuring where
     * a customer splits a large transaction into smaller ones.
     *
     * @param  int  $customerId  The ID of the customer to check
     * @param  string  $currentAmount  The amount of the current transaction (as string for precision)
     * @return array<string, mixed> Aggregate check results containing:
     *                              - has_aggregate_concern: bool Whether aggregate exceeds threshold
     *                              - total_aggregate: string Total of related transactions
     *                              - transaction_count: int Number of related transactions
     *                              - threshold_amount: string The threshold amount
     *                              - related_transactions: array List of related transaction IDs
     */
    public function checkAggregateTransactions(int $customerId, string $currentAmount): array
    {
        $lookbackDays = config('cems.aggregate_lookback_days', 7);
        $lookbackPeriod = now()->subDays($lookbackDays);

        // Use SQL aggregate for sum - more efficient than loading all rows
        $query = Transaction::where('customer_id', $customerId)
            ->where('created_at', '>=', $lookbackPeriod)
            ->where('status', '!=', TransactionStatus::Cancelled->value);

        // Get sum efficiently using SQL
        $existingSum = (string) ($query->sum('amount_local') ?? '0');

        // Get IDs separately (clone query to avoid stateful issue)
        $relatedIds = (clone $query)->pluck('id')->toArray();

        // Add current transaction amount to get total aggregate
        $totalAggregate = $this->mathService->add($currentAmount, $existingSum);

        $thresholdExceeded = $this->mathService->compare(
            $totalAggregate,
            $this->thresholdService->getLargeTransactionThreshold()
        ) > 0;

        return [
            'has_aggregate_concern' => $thresholdExceeded && count($relatedIds) > 0,
            'total_aggregate' => $totalAggregate,
            'transaction_count' => count($relatedIds) + 1,
            'threshold_amount' => $this->thresholdService->getLargeTransactionThreshold(),
            'related_transactions' => $relatedIds,
        ];
    }

    /**
     * Calculate STR filing deadline based on suspicion date.
     *
     * BNM requires STR to be filed within 3 working days of suspicion arising.
     * This method calculates the deadline and checks if it's overdue.
     *
     * @param  Carbon|string  $suspicionDate  When suspicion first arose
     * @return array<string, mixed> Deadline info containing:
     *                              - deadline: Carbon The filing deadline
     *                              - is_overdue: bool Whether deadline has passed
     *                              - days_remaining: int Working days remaining
     *                              - working_days_until_deadline: int Total working days allowed
     */
    public function calculateStrDeadline($suspicionDate): array
    {
        $suspicion = $suspicionDate instanceof Carbon
            ? $suspicionDate
            : Carbon::parse($suspicionDate);

        // Add 3 working days (excluding weekends) - use addWeekdays for Carbon 2.x compatibility
        $deadline = $suspicion->copy()->addWeekdays(self::STR_FILING_DEADLINE_DAYS);

        $now = now();
        $isOverdue = $now->isAfter($deadline);

        // Calculate working days remaining (negative if overdue)
        // For Carbon 2.x, we calculate this manually
        $daysRemaining = $this->countWorkingDays($now, $deadline);

        return [
            'deadline' => $deadline,
            'is_overdue' => $isOverdue,
            'days_remaining' => $daysRemaining,
            'working_days_until_deadline' => self::STR_FILING_DEADLINE_DAYS,
            'suspicion_date' => $suspicion,
        ];
    }

    /**
     * Count working days between two dates (excluding weekends).
     */
    protected function countWorkingDays(Carbon $from, Carbon $to): int
    {
        $fromDay = $from->copy()->startOfDay();
        $toDay = $to->copy()->startOfDay();

        if ($fromDay->equalTo($toDay)) {
            return $fromDay->isWeekend() ? 0 : 1;
        }

        if ($fromDay->gt($toDay)) {
            return -1 * $this->countWorkingDays($toDay, $fromDay);
        }

        $days = 0;
        $current = $fromDay->copy();

        // Inclusive range to match BNM “within N working days” expectations and existing fault test.
        while ($current->lte($toDay)) {
            if (! $current->isWeekend()) {
                $days++;
            }
            $current->addDay();
        }

        return $days;
    }

    /**
     * Check if a large transaction has exceeded its duration threshold.
     *
     * BNM requires enhanced monitoring for large transactions (>= RM 50,000)
     * that remain outstanding/held beyond certain duration thresholds.
     *
     * @param  Transaction  $transaction  The transaction to check
     * @return array<string, mixed> Duration check results containing:
     *                              - has_duration_concern: bool Whether duration threshold exceeded
     *                              - hours_on_hold: int Hours since transaction was put on hold
     *                              - threshold_hours: int Duration threshold in hours
     *                              - severity: string 'warning' or 'critical'
     */
    public function checkTransactionDuration(Transaction $transaction): array
    {
        // Only check transactions that are pending approval (on hold for compliance review)
        if ($transaction->status !== TransactionStatus::PendingApproval) {
            return [
                'has_duration_concern' => false,
                'hours_on_hold' => 0,
                'threshold_hours' => 0,
                'severity' => 'none',
            ];
        }

        // Large transaction threshold
        if ($this->mathService->compare((string) $transaction->amount_local, $this->thresholdService->getLargeTransactionThreshold()) < 0) {
            return [
                'has_duration_concern' => false,
                'hours_on_hold' => 0,
                'threshold_hours' => 0,
                'severity' => 'none',
            ];
        }

        $createdAt = $transaction->created_at instanceof Carbon
            ? $transaction->created_at
            : Carbon::parse($transaction->created_at);

        $hoursOnHold = $createdAt->diffInHours(now());

        // Warning at configured hours, critical at double for large transactions
        $thresholdHours = $this->thresholdService->getDurationWarningHours();
        $criticalHours = $this->thresholdService->getDurationCriticalHours();
        $severity = 'warning';

        if ($hoursOnHold >= $criticalHours) {
            $severity = 'critical';
        }

        return [
            'has_duration_concern' => $hoursOnHold >= $thresholdHours,
            'hours_on_hold' => $hoursOnHold,
            'threshold_hours' => $thresholdHours,
            'severity' => $severity,
        ];
    }

    /**
     * Check if a transaction requires Cash Transaction Report (CTOS/BNM).
     *
     * Cash transactions >= RM 10,000 require reporting to BNM.
     *
     * @param  string  $amount  Transaction amount in MYR
     * @param  string  $transactionType  Buy or Sell
     * @return bool True if CTOS report is required
     */
    public function requiresCtos(string $amount, string $transactionType): bool
    {
        // CTOS applies to all cash transactions (both Buy and Sell) >= RM 10,000
        if (! in_array($transactionType, ['Buy', 'Sell'], true)) {
            return false;
        }

        return $this->mathService->compare($amount, $this->thresholdService->getCtosThreshold()) >= 0;
    }

    /**
     * Get all open flags for a customer requiring attention.
     *
     * @param  int  $customerId  The customer ID
     * @return array<FlaggedTransaction> List of open flags
     */
    public function getCustomerOpenFlags(int $customerId): array
    {
        return FlaggedTransaction::whereHas('transaction', function ($query) use ($customerId) {
            $query->where('customer_id', $customerId);
        })
            ->where('status', '!=', 'Resolved')
            ->with('transaction')
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Check if required documents are uploaded and verified for the CDD level.
     *
     * Verifies that the customer has the necessary KYC documents based on
     * the required due diligence level. Simplified CDD requires only MyKad,
     * Standard CDD requires MyKad + Proof of Address, Enhanced CDD requires
     * all documents plus additional verification.
     *
     * @param  Customer  $customer  The customer to verify documents for
     * @param  CddLevel  $cddLevel  The required CDD level
     * @return array<string, mixed> Document verification result containing:
     *                              - is_compliant: bool Whether documents meet requirements
     *                              - missing_documents: array<string> List of missing document types
     *                              - unverified_documents: array<string> List of uploaded but unverified documents
     *                              - is_verified: bool Whether all uploaded documents are verified
     */
    public function verifyCddDocuments(Customer $customer, CddLevel $cddLevel): array
    {
        $documents = CustomerDocument::where('customer_id', $customer->id)->get();

        // Define required document types per CDD level
        $requiredDocs = match ($cddLevel) {
            CddLevel::Simplified => ['MyKad_Front', 'MyKad_Back'],
            CddLevel::Standard => ['MyKad_Front', 'MyKad_Back', 'Proof_of_Address'],
            CddLevel::Enhanced => ['MyKad_Front', 'MyKad_Back', 'Proof_of_Address', 'Passport'],
        };

        $uploadedTypes = $documents->pluck('document_type')->toArray();
        $verifiedTypes = $documents->filter(fn ($doc) => $doc->isVerified())->pluck('document_type')->toArray();

        $missingDocuments = array_diff($requiredDocs, $uploadedTypes);
        $unverifiedDocuments = array_diff($uploadedTypes, $verifiedTypes);

        // Enhanced CDD requires all documents to be verified
        if ($cddLevel === CddLevel::Enhanced) {
            $isCompliant = empty($missingDocuments) && empty($unverifiedDocuments);
        } else {
            // Standard/Simplified only requires documents to be uploaded (verification can be pending)
            $isCompliant = empty($missingDocuments);
        }

        return [
            'is_compliant' => $isCompliant,
            'missing_documents' => array_values($missingDocuments),
            'unverified_documents' => array_values($unverifiedDocuments),
            'is_verified' => empty($unverifiedDocuments),
            'uploaded_documents' => $uploadedTypes,
            'verified_documents' => $verifiedTypes,
        ];
    }
}
