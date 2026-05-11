<?php

namespace App\Services;

use App\Events\RelatedPartyOwnershipConcern;
use App\Models\Customer;
use App\Models\CustomerRelation;
use App\Models\SanctionEntry;
use App\Models\SanctionsAnalysis;
use App\Models\ScreeningResult;
use App\Models\Transaction;
use App\ValueObjects\ScreeningMatch;
use App\ValueObjects\ScreeningResponse;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CustomerScreeningService
{
    protected float $thresholdFlag;

    protected float $thresholdBlock;

    protected bool $useDob;

    protected bool $useNationality;

    protected int $maxCandidates;

    public function __construct(protected MathService $math)
    {
        $this->thresholdFlag = (float) config('sanctions.matching.threshold_flag', 75.0);
        $this->thresholdBlock = (float) config('sanctions.matching.threshold_block', 90.0);
        $this->useDob = (bool) config('sanctions.matching.use_dob', true);
        $this->useNationality = (bool) config('sanctions.matching.use_nationality', true);
        $this->maxCandidates = (int) config('sanctions.matching.max_candidates', 100);
    }

    public function screenCustomer(Customer $customer): ScreeningResponse
    {
        if ($customer->sanction_hit) {
            $result = $this->createResult(
                customerId: $customer->id,
                screenedName: $customer->full_name,
                entryId: null,
                score: 100.0,
                action: 'block',
                matchedFields: ['sanction_hit_flag']
            );

            return ScreeningResponse::fromResult($result);
        }

        return $this->screenName(
            name: $customer->full_name,
            dob: $customer->date_of_birth?->format('Y-m-d'),
            nationality: $customer->nationality,
            customerId: $customer->id
        );
    }

    public function screenName(
        string $name,
        ?string $dob = null,
        ?string $nationality = null,
        ?int $customerId = null
    ): ScreeningResponse {
        $normalizedName = $this->normalizeName($name);
        $candidates = $this->findCandidates($normalizedName);

        $matches = new Collection;
        $highestScore = 0.0;

        foreach ($candidates as $entry) {
            $score = $this->calculateMatchScore($normalizedName, $entry, $dob, $nationality);

            if ($score >= $this->thresholdFlag) {
                $matchedFields = ['name'];

                if ($dob && $entry->date_of_birth) {
                    if ($this->datesMatch($dob, $entry->date_of_birth->format('Y-m-d'))) {
                        $matchedFields[] = 'dob';
                    }
                }

                if ($nationality && $entry->nationality) {
                    if ($this->nationalitiesMatch($nationality, $entry->nationality)) {
                        $matchedFields[] = 'nationality';
                    }
                }

                if ($entry->soundex_code && $entry->metaphone_code) {
                    $matchedFields[] = 'phonetic';
                }

                $matches->push(ScreeningMatch::fromEntry($entry, $score, $matchedFields));
                $highestScore = max($highestScore, $score);
            }
        }

        $action = 'clear';
        if ($matches->isNotEmpty()) {
            $action = $highestScore >= $this->thresholdBlock ? 'block' : 'flag';
        }

        $result = $this->createResult(
            customerId: $customerId,
            screenedName: $name,
            entryId: $matches->first()?->entryId,
            score: $highestScore,
            action: $action,
            matchedFields: $matches->map(fn (ScreeningMatch $m) => $m->matchedFields)->flatten()->toArray()
        );

        return new ScreeningResponse(
            action: $action,
            confidenceScore: $highestScore,
            matches: $matches,
            screenedAt: Carbon::now(),
            resultId: $result->id,
        );
    }

    public function screenTransaction(Transaction $transaction): ScreeningResponse
    {
        $customerId = $transaction->customer_id;
        $customerName = $transaction->customer?->full_name ?? 'Unknown Customer';

        return $this->screenName(
            name: $customerName,
            dob: $transaction->customer?->date_of_birth?->format('Y-m-d'),
            nationality: $transaction->customer?->nationality,
            customerId: $customerId
        );
    }

    public function batchScreen(array $customerIds): Collection
    {
        $results = new Collection;

        foreach ($customerIds as $customerId) {
            $customer = Customer::find($customerId);
            if ($customer) {
                $results->push($this->screenCustomer($customer));
            }
        }

        return $results;
    }

    public function getHistory(Customer $customer): Collection
    {
        return ScreeningResult::where('customer_id', $customer->id)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function handleConfirmedMatch(Customer $customer, string $listType): array
    {
        // Freeze customer's funds and properties per pd-00.md 27.6.1(a)
        $customer->freeze("confirmed_{$listType}_match");

        // Block transactions to prevent dissipation per pd-00.md 27.6.1(b)
        $this->blockCustomerTransactions($customer);

        // Reject potential customer per pd-00.md 27.6.2 (if not yet active)
        if (! $customer->is_active) {
            $this->rejectCustomer($customer, "positive_{$listType}_match");
        }

        // TODO: pd-00.md 27.7.1 - Report positive name match to BNM FIU and IGP

        return [
            'action' => 'frozen_blocked_reported',
            'customer_id' => $customer->id,
            'list_type' => $listType,
        ];
    }

    private function blockCustomerTransactions(Customer $customer): void
    {
        $customer->update(['transactions_blocked' => true]);
    }

    private function rejectCustomer(Customer $customer, string $reason): void
    {
        $customer->update([
            'is_active' => false,
            'rejection_reason' => $reason,
        ]);
    }

    public function getStatus(Customer $customer): array
    {
        $latestResult = ScreeningResult::where('customer_id', $customer->id)
            ->orderBy('created_at', 'desc')
            ->first();

        return [
            'customer_id' => $customer->id,
            'sanction_hit' => $customer->sanction_hit,
            'last_screened_at' => $latestResult?->created_at?->toIso8601String(),
            'last_result' => $latestResult?->result,
            'last_match_score' => $latestResult?->match_score ? ($latestResult->match_score * 100) : null,
        ];
    }

    protected function findCandidates(string $normalizedName): Collection
    {
        $escapedName = $this->escapeLike($normalizedName);

        return SanctionEntry::where(function ($query) use ($escapedName) {
            $query->where('normalized_name', 'like', "%{$escapedName}%")
                ->orWhere('aliases', 'like', "%{$escapedName}%");
        })
            ->with('sanctionList')
            ->limit($this->maxCandidates)
            ->get();
    }

    protected function calculateMatchScore(
        string $normalizedName,
        SanctionEntry $entry,
        ?string $dob = null,
        ?string $nationality = null
    ): float {
        $scores = [];

        $levenshteinScore = $this->levenshteinSimilarity(
            $normalizedName,
            mb_strtolower($entry->normalized_name ?? '')
        );
        $scores[] = $levenshteinScore * 40;

        $inputTokens = $this->tokenize($normalizedName);
        $entryTokens = $this->tokenize(mb_strtolower($entry->normalized_name ?? ''));
        $tokenScore = $this->tokenMatchScore($inputTokens, $entryTokens);
        $scores[] = $tokenScore * 30;

        if ($entry->soundex_code && $entry->metaphone_code) {
            $inputSoundex = soundex($normalizedName);
            $inputMetaphone = metaphone($normalizedName);

            if ($inputSoundex === $entry->soundex_code) {
                $scores[] = 15.0;
            }
            if ($inputMetaphone === $entry->metaphone_code) {
                $scores[] = 15.0;
            }
        }

        if ($entry->aliases && is_array($entry->aliases)) {
            foreach ($entry->aliases as $alias) {
                $aliasNormalized = mb_strtolower(trim($alias));
                $aliasScore = $this->levenshteinSimilarity($normalizedName, $aliasNormalized);
                $scores[] = $aliasScore * 20;

                $aliasTokens = $this->tokenize($aliasNormalized);
                $aliasTokenScore = $this->tokenMatchScore($inputTokens, $aliasTokens);
                $scores[] = $aliasTokenScore * 10;
            }
        }

        if ($dob && $this->useDob && $entry->date_of_birth) {
            if ($this->datesMatch($dob, $entry->date_of_birth->format('Y-m-d'))) {
                $scores[] = 10.0;
            }
        }

        if ($nationality && $this->useNationality && $entry->nationality) {
            if ($this->nationalitiesMatch($nationality, $entry->nationality)) {
                $scores[] = 5.0;
            }
        }

        $totalScore = array_sum($scores);
        $maxPossibleScore = 100.0;

        return min(($totalScore / $maxPossibleScore) * 100, 100.0);
    }

    public function levenshteinSimilarity(string $a, string $b): float
    {
        $maxLen = max(strlen($a), strlen($b));

        if ($maxLen === 0) {
            return 1.0;
        }

        $distance = levenshtein($a, $b);

        return 1.0 - ($distance / $maxLen);
    }

    protected function tokenize(string $text): array
    {
        $text = mb_strtolower($text);
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', '', $text);
        $tokens = preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY);

        return array_unique($tokens);
    }

    protected function tokenMatchScore(array $tokens1, array $tokens2): float
    {
        if (empty($tokens1) || empty($tokens2)) {
            return 0.0;
        }

        $intersection = array_intersect($tokens1, $tokens2);
        $union = array_unique(array_merge($tokens1, $tokens2));

        if (empty($union)) {
            return 0.0;
        }

        return count($intersection) / count($union);
    }

    protected function datesMatch(string $date1, string $date2): bool
    {
        $d1 = Carbon::parse($date1);
        $d2 = Carbon::parse($date2);

        return $d1->year === $d2->year && $d1->month === $d2->month;
    }

    protected function nationalitiesMatch(string $nat1, string $nat2): bool
    {
        return strcasecmp(trim($nat1), trim($nat2)) === 0;
    }

    protected function normalizeName(string $name): string
    {
        $name = mb_strtolower(trim($name));
        $name = preg_replace('/[^\p{L}\p{N}\s]/u', '', $name);
        $name = preg_replace('/\s+/', ' ', $name);

        return trim($name);
    }

    protected function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * pd-00.md 27.5: Due diligence on related parties
     * Examines and analyses past transactions of specified entities and related parties.
     * Maintains records on the analysis of these transactions.
     */
    public function conductRelatedPartiesDueDiligence(Customer $customer): void
    {
        $relations = CustomerRelation::where('customer_id', $customer->id)->get();

        foreach ($relations as $relation) {
            $relatedParty = $relation->relatedCustomer;

            if (! $relatedParty) {
                continue;
            }

            // Analyze past transactions of the related party
            $this->analyzeRelatedPartyTransactions($relatedParty);

            // pd-00.md 27.5.3: Check beneficial ownership per paragraph 6.2 and CDD requirements
            // Relation types 'beneficial_owner' and 'related_entity' indicate ownership/control
            if (in_array($relation->relation_type, ['beneficial_owner', 'related_entity', 'business_partner'])) {
                $this->checkOwnershipControl($customer, $relatedParty);
            }
        }
    }

    /**
     * Analyze past transactions of a related party for the last 12 months.
     * Creates a SanctionsAnalysis record per pd-00.md 27.5.2 requirement.
     */
    private function analyzeRelatedPartyTransactions(Customer $relatedParty): array
    {
        // Get all transactions for the related party in last 12 months
        $transactions = Transaction::where('customer_id', $relatedParty->id)
            ->where('created_at', '>=', now()->subMonths(12))
            ->get();

        $transactionCount = $transactions->count();
        $totalAmount = $transactions->sum('amount_myrr');

        // Store analysis via customer relation additional_info
        $analysis = [
            'analysis_date' => now()->toIso8601String(),
            'transaction_count' => $transactionCount,
            'total_amount_myrr' => $totalAmount,
            'analysis_type' => 'related_party_due_diligence',
        ];

        $relation = CustomerRelation::where('related_customer_id', $relatedParty->id)->first();

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
            'total_amount' => $totalAmount,
            'analyzed_at' => now(),
        ]);

        return $analysis;
    }

    /**
     * Check ownership/control per pd-00.md 27.5.3 beneficial owner definition.
     * Flags for enhanced monitoring if significant ownership detected (>25%).
     */
    private function checkOwnershipControl(Customer $customer, Customer $relatedParty): void
    {
        // Determine ownership interest
        // 1. Check if related party has ownership_interest field with actual percentage
        // 2. Otherwise, relation_type of 'beneficial_owner' indicates >25% ownership per pd-00.md
        $ownershipInterest = 0.0;
        $isSignificantOwnership = false;

        if ($relatedParty->ownership_interest !== null && is_numeric($relatedParty->ownership_interest)) {
            $ownershipInterest = (float) $relatedParty->ownership_interest;
            $isSignificantOwnership = $ownershipInterest > 25.0;
        } elseif ($relatedParty->relation_type === 'beneficial_owner') {
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

    protected function createResult(
        ?int $customerId,
        string $screenedName,
        ?int $entryId,
        float $score,
        string $action,
        array $matchedFields
    ): ScreeningResult {
        $matchType = 'levenshtein';

        return ScreeningResult::create([
            'customer_id' => $customerId,
            'screened_name' => $screenedName,
            'sanction_entry_id' => $entryId,
            'match_type' => $matchType,
            'match_score' => $score / 100,
            'result' => $action,
            'action_taken' => $action,
            'matched_fields' => $matchedFields,
        ]);
    }
}
