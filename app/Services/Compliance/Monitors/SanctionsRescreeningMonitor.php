<?php

namespace App\Services\Compliance\Monitors;

use App\Enums\FindingSeverity;
use App\Enums\FindingType;
use App\Models\Customer;
use App\Models\SanctionEntry;
use App\Models\SanctionList;
use App\Services\CustomerScreeningService;
use Illuminate\Support\Facades\Log;

/**
 * Monitor for re-screening all customers against updated sanctions entries.
 * Runs weekly to ensure customers haven't become sanctioned since last screening.
 */
class SanctionsRescreeningMonitor extends BaseMonitor
{
    protected CustomerScreeningService $screeningService;

    public function __construct(CustomerScreeningService $screeningService)
    {
        $this->screeningService = $screeningService;
    }

    protected function getFindingType(): FindingType
    {
        return FindingType::SanctionMatch;
    }

    public function run(): array
    {
        $findings = [];

        $latestSanctionUpdate = $this->getLatestSanctionUpdate();
        if ($latestSanctionUpdate === null) {
            Log::info('SanctionsRescreeningMonitor: No sanction entries found');

            return $findings;
        }

        $customersToRescreen = $this->getCustomersNeedingRescreening($latestSanctionUpdate);

        foreach ($customersToRescreen as $customer) {
            $finding = $this->checkCustomerSanctions($customer);
            if ($finding !== null) {
                $findings[] = $finding;
            }
        }

        return $findings;
    }

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

    protected function getCustomersNeedingRescreening(string $latestUpdate)
    {
        return Customer::where('is_active', true)
            ->where(function ($query) use ($latestUpdate) {
                $query->whereNull('sanctions_screened_at')
                    ->orWhere('sanctions_screened_at', '<', $latestUpdate);
            })
            ->get();
    }

    protected function checkCustomerSanctions(Customer $customer): ?array
    {
        $response = $this->screeningService->screenCustomer($customer);

        if ($response->action === 'clear') {
            return null;
        }

        $matchDetails = $response->matches->map(function ($match) {
            return [
                'entry_id' => $match->entryId,
                'entity_name' => $match->entryName,
                'entity_type' => $match->entityType,
                'list_name' => $match->listName ?? 'Unknown',
                'match_score' => round($match->score, 2),
            ];
        })->toArray();

        return $this->createFinding(
            type: FindingType::SanctionMatch,
            severity: FindingSeverity::Critical,
            subjectType: 'Customer',
            subjectId: $customer->id,
            details: [
                'customer_name' => $customer->full_name,
                'customer_nationality' => $customer->nationality,
                'match_count' => count($matchDetails),
                'matches' => array_slice($matchDetails, 0, 5),
                'confidence_score' => round($response->confidenceScore, 2),
                'action' => $response->action,
                'last_screened_at' => $customer->sanctions_screened_at?->toDateTimeString(),
                'recommendation' => 'Immediate referral to Compliance Officer required',
            ]
        );
    }
}
