<?php

namespace App\Services\Compliance\Monitors;

use App\Enums\FindingSeverity;
use App\Enums\FindingType;
use App\Models\Customer;
use App\Models\SanctionEntry;
use App\Models\SanctionList;
use Illuminate\Support\Facades\Log;

/**
 * Monitor for re-screening all customers against updated sanctions entries.
 * Runs weekly to ensure customers haven't become sanctioned since last screening.
 */
class SanctionsRescreeningMonitor extends BaseMonitor
{
    protected function getFindingType(): FindingType
    {
        return FindingType::SanctionMatch;
    }

    public function run(): array
    {
        $findings = [];

        // Get the latest sanctions list update time
        $latestSanctionUpdate = $this->getLatestSanctionUpdate();
        if ($latestSanctionUpdate === null) {
            Log::info('SanctionsRescreeningMonitor: No sanction entries found');

            return $findings;
        }

        // Find customers who haven't been screened since the latest update
        $customersToRescreen = $this->getCustomersNeedingRescreening($latestSanctionUpdate);

        foreach ($customersToRescreen as $customer) {
            $finding = $this->checkCustomerSanctions($customer);
            if ($finding !== null) {
                $findings[] = $finding;
            }
        }

        return $findings;
    }

    /**
     * Get the timestamp of the most recent sanction entry or list update.
     */
    protected function getLatestSanctionUpdate(): ?string
    {
        $latestEntry = SanctionEntry::orderByDesc('created_at')->first();
        $latestList = SanctionList::where('is_active', true)->orderByDesc('uploaded_at')->first();

        if ($latestEntry === null && $latestList === null) {
            return null;
        }

        $entryTime = $latestEntry?->created_at;
        $listTime = $latestList?->uploaded_at;

        if ($entryTime === null) {
            return $listTime?->toDateTimeString();
        }
        if ($listTime === null) {
            return $entryTime->toDateTimeString();
        }

        return $entryTime->gt($listTime)
            ? $entryTime->toDateTimeString()
            : $listTime->toDateTimeString();
    }

    /**
     * Get active customers who haven't been screened since the latest sanction update.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function getCustomersNeedingRescreening(string $latestUpdate)
    {
        // Get customers where sanctions_screened_at is null or before latest update
        // Note: If sanctions_screened_at column doesn't exist, we screen all active customers
        return Customer::where('is_active', true)
            ->where(function ($query) use ($latestUpdate) {
                $query->whereNull('sanctions_screened_at')
                    ->orWhere('sanctions_screened_at', '<', $latestUpdate);
            })
            ->get();
    }

    /**
     * Check a customer against sanctions lists.
     */
    protected function checkCustomerSanctions(Customer $customer): ?array
    {
        // Check if customer name matches any sanction entry
        $matches = $this->screenCustomerName($customer->full_name);

        if (empty($matches)) {
            return null;
        }

        return $this->createFinding(
            type: FindingType::SanctionMatch,
            severity: FindingSeverity::Critical,
            subjectType: 'Customer',
            subjectId: $customer->id,
            details: [
                'customer_name' => $customer->full_name,
                'customer_nationality' => $customer->nationality,
                'match_count' => count($matches),
                'matches' => array_slice($matches, 0, 5), // Limit to first 5 matches
                'last_screened_at' => $customer->sanctions_screened_at?->toDateTimeString(),
                'recommendation' => 'Immediate referral to Compliance Officer required',
            ]
        );
    }

    /**
     * Screen a customer name against sanction entries.
     *
     * @return array Array of matches with entry details
     */
    protected function screenCustomerName(string $name): array
    {
        $matches = [];
        $name = strtolower(trim($name));
        $nameParts = explode(' ', $name);

        $entries = SanctionEntry::with('sanctionList')
            ->get();

        foreach ($entries as $entry) {
            $entryName = strtolower($entry->entity_name);
            $aliases = $entry->aliases ?? [];

            // Direct name match
            $score = $this->calculateSimilarity($name, $entryName);

            // Check aliases
            $aliasScore = 0.0;
            foreach ($aliases as $alias) {
                $aliasScore = max($aliasScore, $this->calculateSimilarity($name, strtolower($alias)));
            }

            $maxScore = max($score, $aliasScore);

            if ($maxScore >= 0.85) { // High threshold for sanctions screening
                $matches[] = [
                    'entry_id' => $entry->id,
                    'entity_name' => $entry->entity_name,
                    'entity_type' => $entry->entity_type,
                    'list_name' => $entry->sanctionList?->name ?? 'Unknown',
                    'match_score' => round($maxScore, 2),
                ];
            }
        }

        return $matches;
    }

    /**
     * Calculate string similarity using Levenshtein distance.
     */
    protected function calculateSimilarity(string $str1, string $str2): float
    {
        $distance = levenshtein($str1, $str2);
        $maxLen = max(strlen($str1), strlen($str2));

        if ($maxLen === 0) {
            return 1.0;
        }

        return 1 - ($distance / $maxLen);
    }
}
