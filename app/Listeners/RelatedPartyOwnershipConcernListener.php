<?php

namespace App\Listeners;

use App\Enums\AlertPriority;
use App\Enums\AlertStatus;
use App\Enums\FindingSeverity;
use App\Enums\FindingStatus;
use App\Enums\FindingType;
use App\Events\RelatedPartyOwnershipConcern;
use App\Models\Compliance\Alert;
use App\Models\Compliance\ComplianceFinding;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;

class RelatedPartyOwnershipConcernListener
{
    public function __invoke(RelatedPartyOwnershipConcern $event): void
    {
        DB::transaction(function () use ($event) {
            // Serialise on the customer row and dedup per (customer, related
            // party): concurrent evaluations of the same pair would otherwise
            // stack duplicate findings and alerts for one concern.
            Customer::whereKey($event->customer->id)->lockForUpdate()->firstOrFail();

            $alreadyOpen = ComplianceFinding::where('subject_type', 'Customer')
                ->where('subject_id', $event->customer->id)
                ->where('finding_type', FindingType::AggregateTransaction->value)
                ->where('status', FindingStatus::New->value)
                ->where('details->related_party_id', $event->relatedParty->id)
                ->exists();

            if ($alreadyOpen) {
                return;
            }

            ComplianceFinding::create([
                'subject_type' => 'Customer',
                'subject_id' => $event->customer->id,
                'finding_type' => FindingType::AggregateTransaction,
                'severity' => FindingSeverity::Medium,
                'details' => [
                    'related_party' => $event->relatedParty->full_name,
                    'related_party_id' => $event->relatedParty->id,
                    'ownership_interest' => $event->ownershipInterest,
                    'customer_name' => $event->customer->full_name,
                ],
                'status' => FindingStatus::New->value,
                'generated_at' => now(),
            ]);

            Alert::create([
                'customer_id' => $event->customer->id,
                'priority' => AlertPriority::Medium,
                'type' => 'Related_Party_Ownership',
                'reason' => 'Related party ownership concern detected: '.$event->relatedParty->full_name.' ('.round($event->ownershipInterest * 100, 1).'% ownership)',
                'status' => AlertStatus::Open,
                'risk_score' => $this->calculateRiskScore($event->ownershipInterest),
            ]);
        });
    }

    /**
     * Calculate risk score based on ownership interest percentage.
     */
    private function calculateRiskScore(float $ownershipInterest): int
    {
        $percentage = $ownershipInterest * 100;

        if ($percentage >= 50) {
            return 80;
        }

        if ($percentage >= 25) {
            return 60;
        }

        return 40;
    }
}
