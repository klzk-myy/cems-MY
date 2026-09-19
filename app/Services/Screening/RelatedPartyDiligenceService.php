<?php

namespace App\Services\Screening;

use App\Enums\RelationType;
use App\Events\RelatedPartyOwnershipConcern;
use App\Models\Customer;
use App\Models\CustomerRelation;
use App\Models\SanctionsAnalysis;
use App\Models\Transaction;
use App\Services\System\MathService;

/**
 * pd-00.md 27.5: due diligence on related parties — examines past
 * transactions of related entities, records the analysis, and flags
 * significant ownership/control for enhanced monitoring.
 */
class RelatedPartyDiligenceService
{
    public function __construct(
        protected MathService $math,
    ) {}

    /**
     * pd-00.md 27.5: Due diligence on related parties
     * Examines and analyses past transactions of specified entities and related parties.
     * Maintains records on the analysis of these transactions.
     */
    public function conductRelatedPartiesDueDiligence(Customer $customer): void
    {
        $relations = CustomerRelation::with('relatedCustomer')
            ->where('customer_id', $customer->id)
            ->get();

        foreach ($relations as $relation) {
            $relatedParty = $relation->relatedCustomer;

            if (! $relatedParty) {
                continue;
            }

            // Analyze past transactions of the related party
            $this->analyzeRelatedPartyTransactions($relatedParty, $relation);

            // pd-00.md 27.5.3: Check beneficial ownership per paragraph 6.2 and CDD requirements
            // Relation types 'beneficial_owner' and 'related_entity' indicate ownership/control
            if (in_array($relation->relation_type, [
                RelationType::BeneficialOwner,
                RelationType::RelatedEntity,
                RelationType::BusinessPartner,
            ], true)) {
                $this->checkOwnershipControl($customer, $relation);
            }
        }
    }

    /**
     * Analyze past transactions of a related party for the last 12 months.
     * Creates a SanctionsAnalysis record per pd-00.md 27.5.2 requirement.
     *
     * @return array<string, mixed>
     */
    private function analyzeRelatedPartyTransactions(Customer $relatedParty, ?CustomerRelation $relation = null): array
    {
        // Get all transactions for the related party in last 12 months
        $transactions = Transaction::where('customer_id', $relatedParty->id)
            ->where('created_at', '>=', now()->subMonths(12))
            ->get();

        $transactionCount = $transactions->count();
        // Sum with bcmath to avoid float precision loss on large monetary totals
        $totalAmountMyr = '0';
        foreach ($transactions as $transaction) {
            $totalAmountMyr = $this->math->add($totalAmountMyr, (string) $transaction->amount_myr);
        }

        // Store analysis via customer relation additional_info
        $analysis = [
            'analysis_date' => now()->toIso8601String(),
            'transaction_count' => $transactionCount,
            'total_amount_myr' => $totalAmountMyr,
            'analysis_type' => 'related_party_due_diligence',
        ];

        $relation ??= CustomerRelation::where('related_customer_id', $relatedParty->id)->first();

        if ($relation) {
            $additionalInfo = $relation->additional_info ?? [];
            $additionalInfo['last_due_diligence_analysis'] = $analysis;
            $relation->update(['additional_info' => $additionalInfo]);
        }

        // Create SanctionsAnalysis record per pd-00.md 27.5.2
        SanctionsAnalysis::create([
            'customer_id' => $relatedParty->id,
            'analysis_type' => 'related_party_due_diligence',
            'transaction_count' => $transactionCount,
            'total_amount_myr' => $totalAmountMyr,
            'analyzed_at' => now(),
        ]);

        return $analysis;
    }

    /**
     * Check ownership/control per pd-00.md 27.5.3 beneficial owner definition.
     * Flags for enhanced monitoring if significant ownership detected (>25%).
     */
    private function checkOwnershipControl(Customer $customer, CustomerRelation $relation): void
    {
        $relatedParty = $relation->relatedCustomer;

        if (! $relatedParty) {
            return;
        }

        // Determine ownership interest
        // 1. Check for an explicit ownership_interest percentage (relation's
        //    additional_info or the related customer, if such data was captured)
        // 2. Otherwise, relation_type of 'beneficial_owner' indicates >25%
        //    ownership per pd-00.md
        $ownershipInterest = 0.0;
        $isSignificantOwnership = false;

        // ownership_interest is not a persisted customer column; it may only
        // ever be captured on the relation's additional_info. getAttribute()
        // keeps the defensive fallback (returns null) without PHPStan
        // inferring a non-existent model property.
        $explicitInterest = $relation->additional_info['ownership_interest']
            ?? $relatedParty->getAttribute('ownership_interest');

        if ($explicitInterest !== null && is_numeric($explicitInterest)) {
            $ownershipInterest = (float) $explicitInterest;
            $isSignificantOwnership = $ownershipInterest > 25.0;
        } elseif ($relation->relation_type === RelationType::BeneficialOwner) {
            // relation_type 'beneficial_owner' per migration indicates >25% ownership
            $ownershipInterest = 26.0; // Presumed >25% for beneficial owner status
            $isSignificantOwnership = true;
        }

        if ($isSignificantOwnership) {
            // Fire the RelatedPartyOwnershipConcern event per pd-00.md 27.5.3
            event(new RelatedPartyOwnershipConcern($customer, $relatedParty, $ownershipInterest));
        }

        // Also flag concerns for frozen/sanctioned related parties
        if ($relatedParty->is_frozen || $relatedParty->sanction_hit) {
            event(new RelatedPartyOwnershipConcern($customer, $relatedParty, $ownershipInterest));
        }
    }
}
