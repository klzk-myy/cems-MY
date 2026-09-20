<?php

namespace App\Services;

use App\Models\AdverseMediaEntry;
use App\Models\Customer;
use App\Models\SanctionEntry;
use App\Models\ScreeningResult;
use App\Models\Transaction;
use App\Services\Contracts\CustomerScreeningServiceInterface;
use App\Services\Screening\NameMatcher;
use App\Services\Screening\RelatedPartyDiligenceService;
use App\Services\Screening\ScreeningEnforcementService;
use App\Support\LikeEscaper;
use App\ValueObjects\ScreeningMatch;
use App\ValueObjects\ScreeningResponse;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class CustomerScreeningService implements CustomerScreeningServiceInterface
{
    /**
     * Upper bound of sanction entries fetched by the SQL prefilter before
     * in-memory candidate ranking (entries are then ranked by descending
     * token overlap and truncated to max_candidates).
     */
    protected const CANDIDATE_POOL_LIMIT = 1000;

    /**
     * Candidate source types persisted on screening_results.source.
     */
    public const SOURCE_SANCTIONS = 'sanctions';

    public const SOURCE_ADVERSE_MEDIA = 'adverse_media';

    protected float $thresholdFlag;

    protected float $thresholdBlock;

    protected int $maxCandidates;

    public function __construct(
        protected NameMatcher $nameMatcher,
        protected ScreeningEnforcementService $enforcementService,
        protected RelatedPartyDiligenceService $relatedPartyDiligence,
    ) {
        $this->thresholdFlag = (float) config('sanctions.matching.threshold_flag', 75.0);
        $this->thresholdBlock = (float) config('sanctions.matching.threshold_block', 90.0);
        $this->maxCandidates = (int) config('sanctions.matching.max_candidates', 100);
    }

    public function screenCustomer(Customer $customer): ScreeningResponse
    {
        return $this->screenCustomerWithPools($customer);
    }

    public function screenName(
        string $name,
        ?string $dob = null,
        ?string $nationality = null,
        ?int $customerId = null,
        bool $persist = true
    ): ScreeningResponse {
        return $this->screenNameInternal($name, $dob, $nationality, $customerId, $persist);
    }

    public function screenTransaction(Transaction $transaction): ScreeningResponse
    {
        $customerId = $transaction->customer_id;
        $customerName = $transaction->customer->full_name ?? 'Unknown Customer';

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
        $customers = Customer::whereIn('id', $customerIds)->get();

        // Single prefilter over the UNION of all customers' name tokens
        // instead of hydrating the entire sanctions/adverse-media corpus.
        // The union is a superset of each customer's individual prefilter;
        // the per-customer in-memory ranking then applies the real filter,
        // so results are identical while memory stays bounded.
        $allTokens = $customers
            ->flatMap(fn (Customer $customer) => $this->nameMatcher->tokenize(
                $this->nameMatcher->normalizeName((string) $customer->full_name)
            ))
            ->unique()
            ->values()
            ->all();

        $sanctionPool = $this->tokenPrefilteredPool(
            SanctionEntry::query()->with('sanctionList'),
            $allTokens,
            'normalized_name',
            'aliases'
        );
        $adversePool = $this->tokenPrefilteredPool(
            AdverseMediaEntry::query()->where('is_active', true),
            $allTokens,
            'normalized_name',
            'alias'
        );

        foreach ($customers as $customer) {
            $results->push($this->screenCustomerWithPools($customer, $sanctionPool, $adversePool));
        }

        return $results;
    }

    /**
     * Entries matching ANY of the given tokens in any of the given columns —
     * the same LIKE-escaped prefilter findCandidates applies per customer.
     *
     * @param  array<int, string>  $tokens
     * @return Collection<int, Model>
     */
    private function tokenPrefilteredPool(
        Builder $query,
        array $tokens,
        string ...$columns
    ): Collection {
        if ($tokens === []) {
            return new Collection;
        }

        return $query
            ->where(function ($q) use ($tokens, $columns) {
                foreach ($tokens as $token) {
                    $escapedToken = LikeEscaper::escape($token);

                    foreach ($columns as $column) {
                        $q->orWhereRaw("{$column} LIKE ? ESCAPE ?", ["%{$escapedToken}%", '\\']);
                    }
                }
            })
            ->orderBy('id')
            ->get();
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

    public function handleConfirmedMatch(Customer $customer, string $listType): array
    {
        return $this->enforcementService->handleConfirmedMatch($customer, $listType);
    }

    public function handleConfirmedAdverseMatch(Customer $customer, string $severity = AdverseMediaEntry::SEVERITY_MEDIUM): array
    {
        return $this->enforcementService->handleConfirmedAdverseMatch($customer, $severity);
    }

    public function conductRelatedPartiesDueDiligence(Customer $customer): void
    {
        $this->relatedPartyDiligence->conductRelatedPartiesDueDiligence($customer);
    }

    public function levenshteinSimilarity(string $a, string $b): float
    {
        return $this->nameMatcher->levenshteinSimilarity($a, $b);
    }

    /**
     * @param  Collection<int, SanctionEntry>|null  $sanctionPool
     * @param  Collection<int, AdverseMediaEntry>|null  $adversePool
     */
    private function screenCustomerWithPools(
        Customer $customer,
        ?Collection $sanctionPool = null,
        ?Collection $adversePool = null
    ): ScreeningResponse {
        if ($customer->sanction_hit) {
            $result = $this->createResult(
                customerId: $customer->id,
                screenedName: $customer->full_name,
                entryId: null,
                score: 100.0,
                action: 'block',
                matchedFields: ['sanction_hit_flag']
            );

            $this->stampScreenedAt($customer->id);

            return ScreeningResponse::fromResult($result);
        }

        return $this->screenNameInternal(
            name: $customer->full_name,
            dob: $customer->date_of_birth?->format('Y-m-d'),
            nationality: $customer->nationality,
            customerId: $customer->id,
            persist: true,
            sanctionPool: $sanctionPool,
            adversePool: $adversePool,
        );
    }

    /**
     * @param  Collection<int, SanctionEntry>|null  $sanctionPool
     * @param  Collection<int, AdverseMediaEntry>|null  $adversePool
     */
    private function screenNameInternal(
        string $name,
        ?string $dob,
        ?string $nationality,
        ?int $customerId,
        bool $persist,
        ?Collection $sanctionPool = null,
        ?Collection $adversePool = null,
    ): ScreeningResponse {
        $normalizedName = $this->nameMatcher->normalizeName($name);

        [$matches, $highestScore] = $this->collectSanctionMatches($normalizedName, $dob, $nationality, $sanctionPool);
        [$adverseMatches, $highestAdverseScore] = $this->collectAdverseMatches($normalizedName, $adversePool);

        // Sanctions keep block semantics (>= threshold_block). Adverse media
        // hits are always review-level: they escalate to 'flag' but never to
        // 'block' because press allegations are not listing determinations.
        $action = 'clear';
        if ($matches->isNotEmpty()) {
            $action = $highestScore >= $this->thresholdBlock ? 'block' : 'flag';
        } elseif ($adverseMatches->isNotEmpty()) {
            $action = 'flag';
        }

        $result = null;
        if ($persist) {
            $result = $this->persistScreeningResult(
                $customerId,
                $name,
                $matches,
                $adverseMatches,
                $highestScore,
                $highestAdverseScore,
                $action
            );

            // Record the successful screening so rescreening schedulers
            // (compliance:rescreen, SanctionsRescreeningMonitor) only pick up
            // customers whose screening has gone stale.
            $this->stampScreenedAt($customerId);
        }

        return new ScreeningResponse(
            action: $action,
            confidenceScore: max($highestScore, $highestAdverseScore),
            matches: $matches->concat($adverseMatches),
            screenedAt: Carbon::now(),
            resultId: $result?->id,
        );
    }

    /**
     * @param  Collection<int, SanctionEntry>|null  $pool
     * @return array{0: Collection<int, ScreeningMatch>, 1: float}
     */
    private function collectSanctionMatches(
        string $normalizedName,
        ?string $dob,
        ?string $nationality,
        ?Collection $pool
    ): array {
        $matches = new Collection;
        $highestScore = 0.0;

        foreach ($this->findCandidates($normalizedName, $pool) as $entry) {
            $score = $this->nameMatcher->calculateMatchScore(
                $normalizedName,
                $this->sanctionEntryScoreData($entry),
                $dob,
                $nationality
            );

            if ($score < $this->thresholdFlag) {
                continue;
            }

            $matchedFields = ['name'];

            if ($dob && $entry->date_of_birth) {
                if ($this->nameMatcher->datesMatch($dob, $entry->date_of_birth->format('Y-m-d'))) {
                    $matchedFields[] = 'dob';
                }
            }

            if ($nationality && $entry->nationality) {
                if ($this->nameMatcher->nationalitiesMatch($nationality, $entry->nationality)) {
                    $matchedFields[] = 'nationality';
                }
            }

            if ($entry->soundex_code && $entry->metaphone_code) {
                $matchedFields[] = 'phonetic';
            }

            $matches->push(ScreeningMatch::fromEntry($entry, $score, $matchedFields));
            $highestScore = max($highestScore, $score);
        }

        return [$matches, $highestScore];
    }

    /**
     * @param  Collection<int, AdverseMediaEntry>|null  $pool
     * @return array{0: Collection<int, ScreeningMatch>, 1: float}
     */
    private function collectAdverseMatches(string $normalizedName, ?Collection $pool): array
    {
        $matches = new Collection;
        $highestScore = 0.0;

        foreach ($this->findAdverseMediaCandidates($normalizedName, $pool) as $entry) {
            $score = $this->nameMatcher->calculateAdverseMatchScore(
                $normalizedName,
                ['normalized_name' => $entry->normalized_name, 'alias' => $entry->alias]
            );

            if ($score < $this->thresholdFlag) {
                continue;
            }

            $matchedFields = ['name'];

            if ($entry->alias
                && $this->nameMatcher->levenshteinSimilarity($normalizedName, mb_strtolower(trim($entry->alias))) >= NameMatcher::TOKEN_MATCH_THRESHOLD
            ) {
                $matchedFields[] = 'alias';
            }

            $matches->push(new ScreeningMatch(
                entryId: $entry->id,
                entityName: $entry->name,
                listName: 'Adverse Media',
                listSource: self::SOURCE_ADVERSE_MEDIA,
                matchScore: $score,
                matchedFields: $matchedFields,
                listingDate: null,
                dateOfBirth: null,
                nationality: null,
            ));

            $highestScore = max($highestScore, $score);
        }

        return [$matches, $highestScore];
    }

    /**
     * @param  Collection<int, ScreeningMatch>  $matches
     * @param  Collection<int, ScreeningMatch>  $adverseMatches
     */
    private function persistScreeningResult(
        ?int $customerId,
        string $name,
        Collection $matches,
        Collection $adverseMatches,
        float $highestScore,
        float $highestAdverseScore,
        string $action
    ): ScreeningResult {
        $hasSanctionHits = $matches->isNotEmpty();
        $bestSanctionEntryId = $hasSanctionHits ? $matches->first()?->entryId : null;
        $bestAdverseEntryId = $adverseMatches
            ->sortByDesc(fn (ScreeningMatch $match) => $match->matchScore)
            ->first()
            ?->entryId;

        return $this->createResult(
            customerId: $customerId,
            screenedName: $name,
            entryId: $bestSanctionEntryId,
            score: max($highestScore, $highestAdverseScore),
            action: $action,
            matchedFields: $matches
                ->concat($adverseMatches)
                ->map(fn (ScreeningMatch $m) => $m->matchedFields)
                ->flatten()
                ->toArray(),
            source: $adverseMatches->isNotEmpty() && ! $hasSanctionHits
                ? self::SOURCE_ADVERSE_MEDIA
                : self::SOURCE_SANCTIONS,
            adverseMediaEntryId: $bestAdverseEntryId
        );
    }

    /**
     * Entry fields consumed by NameMatcher::calculateMatchScore, as a plain
     * array so the matcher stays free of model dependencies.
     *
     * @return array{normalized_name: ?string, soundex_code: ?string, metaphone_code: ?string, aliases: mixed, date_of_birth: ?string, nationality: ?string}
     */
    private function sanctionEntryScoreData(SanctionEntry $entry): array
    {
        return [
            'normalized_name' => $entry->normalized_name,
            'soundex_code' => $entry->soundex_code,
            'metaphone_code' => $entry->metaphone_code,
            'aliases' => $entry->aliases,
            'date_of_birth' => $entry->date_of_birth?->format('Y-m-d'),
            'nationality' => $entry->nationality,
        ];
    }

    /**
     * @param  Collection<int, SanctionEntry>|null  $pool
     * @return Collection<int, SanctionEntry>
     */
    protected function findCandidates(string $normalizedName, ?Collection $pool = null): Collection
    {
        $inputTokens = $this->nameMatcher->tokenize($normalizedName);

        if ($inputTokens === []) {
            return new Collection;
        }

        // Token-level SQL prefilter: an entry qualifies when ANY customer
        // name token occurs in its normalized name or aliases. The previous
        // full-name substring match missed real entries whose names are
        // longer or decorated versions of the customer's name. A preloaded
        // pool (batchScreen) skips the prefilter entirely; the in-memory
        // token ranking below applies the same filter.
        $pool ??= SanctionEntry::query()
            ->where(function ($query) use ($inputTokens) {
                // Explicit ESCAPE clause so escaped wildcards are treated
                // literally on every driver (SQLite has no default LIKE
                // escape character).
                foreach ($inputTokens as $token) {
                    $escapedToken = LikeEscaper::escape($token);

                    $query->orWhereRaw('normalized_name LIKE ? ESCAPE ?', ["%{$escapedToken}%", '\\'])
                        ->orWhereRaw('aliases LIKE ? ESCAPE ?', ["%{$escapedToken}%", '\\']);
                }
            })
            ->with('sanctionList')
            // Deterministic order so pool truncation is stable before the
            // in-memory ranking below selects the best candidates.
            ->orderBy('id')
            ->limit(self::CANDIDATE_POOL_LIMIT)
            ->get();

        $ranked = [];

        foreach ($pool as $entry) {
            $entryTokens = $this->nameMatcher->entryTokens($entry->normalized_name, $entry->aliases);

            if ($entryTokens === []) {
                continue;
            }

            [$matchedTokens, $overlapScore] = $this->nameMatcher->matchInputTokens($inputTokens, $entryTokens);

            // Every customer name token must fuzzy-match some entry token;
            // otherwise the entry cannot be a plausible match.
            if ($matchedTokens < count($inputTokens)) {
                continue;
            }

            $ranked[] = ['entry' => $entry, 'score' => $overlapScore];
        }

        usort($ranked, fn (array $a, array $b) => $b['score'] <=> $a['score']);

        return new Collection(array_slice(array_column($ranked, 'entry'), 0, $this->maxCandidates));
    }

    /**
     * Adverse media candidate pool, built identically to findCandidates:
     * token-level SQL prefilter against active entries, then in-memory
     * fuzzy token ranking truncated to max_candidates.
     *
     * @param  Collection<int, AdverseMediaEntry>|null  $pool
     * @return Collection<int, AdverseMediaEntry>
     */
    protected function findAdverseMediaCandidates(string $normalizedName, ?Collection $pool = null): Collection
    {
        $inputTokens = $this->nameMatcher->tokenize($normalizedName);

        if ($inputTokens === []) {
            return new Collection;
        }

        $pool ??= AdverseMediaEntry::query()
            ->where('is_active', true)
            ->where(function ($query) use ($inputTokens) {
                foreach ($inputTokens as $token) {
                    $escapedToken = LikeEscaper::escape($token);

                    $query->orWhereRaw('normalized_name LIKE ? ESCAPE ?', ["%{$escapedToken}%", '\\'])
                        ->orWhereRaw('alias LIKE ? ESCAPE ?', ["%{$escapedToken}%", '\\']);
                }
            })
            ->orderBy('id')
            ->limit(self::CANDIDATE_POOL_LIMIT)
            ->get();

        $ranked = [];

        foreach ($pool as $entry) {
            $entryTokens = $this->nameMatcher->adverseEntryTokens($entry->normalized_name, $entry->alias);

            if ($entryTokens === []) {
                continue;
            }

            [$matchedTokens, $overlapScore] = $this->nameMatcher->matchInputTokens($inputTokens, $entryTokens);

            if ($matchedTokens < count($inputTokens)) {
                continue;
            }

            $ranked[] = ['entry' => $entry, 'score' => $overlapScore];
        }

        usort($ranked, fn (array $a, array $b) => $b['score'] <=> $a['score']);

        return new Collection(array_slice(array_column($ranked, 'entry'), 0, $this->maxCandidates));
    }

    /**
     * Stamp customers.sanctions_screened_at after a successful screening so
     * rescreening workflows can detect stale customers. No-op for name-only
     * screens without a customer context.
     */
    protected function stampScreenedAt(?int $customerId): void
    {
        if ($customerId === null) {
            return;
        }

        Customer::whereKey($customerId)->update(['sanctions_screened_at' => now()]);
    }

    protected function createResult(
        ?int $customerId,
        string $screenedName,
        ?int $entryId,
        float $score,
        string $action,
        array $matchedFields,
        string $source = self::SOURCE_SANCTIONS,
        ?int $adverseMediaEntryId = null
    ): ScreeningResult {
        $matchType = 'levenshtein';

        return ScreeningResult::create([
            'customer_id' => $customerId,
            'screened_name' => $screenedName,
            'sanction_entry_id' => $entryId,
            'adverse_media_entry_id' => $adverseMediaEntryId,
            'source' => $source,
            'match_type' => $matchType,
            'match_score' => $score / 100,
            'result' => $action,
            'action_taken' => $action,
            'matched_fields' => $matchedFields,
        ]);
    }
}
