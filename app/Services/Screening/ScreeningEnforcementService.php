<?php

namespace App\Services\Screening;

use App\Enums\RiskRating;
use App\Enums\SystemAlertLevel;
use App\Models\AdverseMediaEntry;
use App\Models\Compliance\SanctionEntry;
use App\Models\Customer;
use App\Models\SystemAlert;
use App\Notifications\SanctionsMatchNotification;
use App\Services\Compliance\AlertTriageService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Post-screening enforcement for confirmed matches: freezing funds,
 * blocking transactions, rejecting pending customers, and the BNM FIU/IGP
 * reporting alert with officer escalation.
 */
class ScreeningEnforcementService
{
    public function __construct(
        protected AlertTriageService $alertTriageService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handleConfirmedMatch(Customer $customer, string $listType): array
    {
        DB::transaction(function () use ($customer, $listType) {
            // Freeze customer's funds and properties per pd-00.md 27.6.1(a)
            $customer->freeze("confirmed_{$listType}_match");

            // Mark the standing sanction-hit flag so risk scoring and future
            // screenings treat the customer as a confirmed match.
            $customer->sanction_hit = true;
            $customer->risk_rating = RiskRating::High;
            $customer->save();

            // Block transactions to prevent dissipation per pd-00.md 27.6.1(b)
            $this->blockCustomerTransactions($customer);

            // Reject potential customer per pd-00.md 27.6.2 (if not yet active)
            if (! $customer->is_active) {
                $this->rejectCustomer($customer, "positive_{$listType}_match");
            }

            // pd-00.md 27.7.1 - Report positive name match to BNM FIU and IGP
            $this->reportToBnmFiu($customer, $listType);
        });

        return [
            'action' => 'frozen_blocked_reported',
            'customer_id' => $customer->id,
            'list_type' => $listType,
        ];
    }

    /**
     * Confirmed adverse media match handling. Unlike confirmed sanctions
     * matches (handleConfirmedMatch), adverse media confirmations do NOT
     * freeze funds, block transactions or reject the customer: press
     * allegations are not listing determinations. The confirmation instead
     * escalates a compliance alert for EDD review and potential STR filing.
     *
     * @return array<string, mixed>
     */
    public function handleConfirmedAdverseMatch(Customer $customer, string $severity = AdverseMediaEntry::SEVERITY_MEDIUM): array
    {
        $level = $severity === AdverseMediaEntry::SEVERITY_HIGH
            ? SystemAlertLevel::Critical
            : SystemAlertLevel::Warning;

        SystemAlert::create([
            'level' => $level->value,
            'message' => "Confirmed adverse media match on customer {$customer->full_name} (ID: {$customer->id}) - escalated for enhanced due diligence review",
            'source' => 'adverse_media_screening',
            'metadata' => [
                'customer_id' => $customer->id,
                'customer_name' => $customer->full_name,
                'list_type' => 'adverse_media',
                'severity' => $severity,
                'action' => 'edd_review_required',
                'requires_fiu_report' => false,
            ],
        ]);

        return [
            'action' => 'escalated_for_review',
            'customer_id' => $customer->id,
            'list_type' => 'adverse_media',
        ];
    }

    private function blockCustomerTransactions(Customer $customer): void
    {
        $customer->transactions_blocked = true;
        $customer->save();
    }

    private function rejectCustomer(Customer $customer, string $reason): void
    {
        $customer->reject($reason);
    }

    /**
     * pd-00.md 27.7.1 - Report positive name match to BNM FIU and IGP
     */
    private function reportToBnmFiu(Customer $customer, string $listType): void
    {
        SystemAlert::create([
            'level' => SystemAlertLevel::Critical->value,
            'message' => "Positive {$listType} match on customer {$customer->full_name} (ID: {$customer->id}) - BNM FIU/IGP reporting required within 24 hours per pd-00.md 27.7.1",
            'source' => 'sanctions_screening',
            'metadata' => [
                'customer_id' => $customer->id,
                'customer_name' => $customer->full_name,
                'list_type' => $listType,
                'action' => 'bnm_fiu_report_required',
                'report_deadline' => now()->addHours(24)->toIso8601String(),
                'requires_fiu_report' => true,
            ],
        ]);

        $this->notifyOfficersOfConfirmedMatch($customer, $listType);
    }

    /**
     * Escalate a confirmed sanctions match to compliance officers so the FIU
     * report deadline is not missed.
     */
    private function notifyOfficersOfConfirmedMatch(Customer $customer, string $listType): void
    {
        try {
            $latestEntry = SanctionEntry::whereHas('sanctionList', fn ($q) => $q->where('list_type', $listType))
                ->inRandomOrder()
                ->first();

            $this->alertTriageService->notifyAvailableOfficers(
                new SanctionsMatchNotification(
                    $latestEntry ?? new SanctionEntry,
                    "Confirmed {$listType} match on {$customer->full_name} — BNM FIU report due within 24 hours"
                )
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to resolve officers for sanctions match escalation', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
