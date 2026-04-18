<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\SanctionEntry;
use App\Models\ScreeningResult;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class UnifiedSanctionScreeningService
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

    public function screenCustomer(Customer $customer, ?string $notes = null): ScreeningResponse
    {
        if ($customer->sanction_hit) {
            $result = $this->createResult(
                customerId: $customer->id,
                screenedName: $customer->full_name,
                entryId: null,
                score: 100.0,
                action: 'block',
                matchedFields: ['sanction_hit_flag'],
                notes: $notes
            );

            return ScreeningResponse::fromResult($result);
        }

        return $this->screenName(
            name: $customer->full_name,
            dob: $customer->date_of_birth?->format('Y-m-d'),
            nationality: $customer->nationality,
            customerId: $customer->id,
            notes: $notes
        );
    }

    public function screenName(
        string $name,
        ?string $dob = null,
        ?string $nationality = null,
        ?int $customerId = null,
        ?string $notes = null
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
            matchedFields: $matches->map(fn (ScreeningMatch $m) => $m->matchedFields)->flatten()->toArray(),
            notes: $notes
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
            customerId: $customerId,
            notes: "Transaction #{$transaction->id}"
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

    protected function createResult(
        ?int $customerId,
        string $screenedName,
        ?int $entryId,
        float $score,
        string $action,
        array $matchedFields,
        ?string $notes = null
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
