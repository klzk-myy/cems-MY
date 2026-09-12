<?php

namespace App\Services;

use App\Enums\RelationType;
use App\Enums\RiskRating;
use App\Enums\SystemAlertLevel;
use App\Events\RelatedPartyOwnershipConcern;
use App\Models\AdverseMediaEntry;
use App\Models\Customer;
use App\Models\CustomerRelation;
use App\Models\SanctionEntry;
use App\Models\SanctionsAnalysis;
use App\Models\ScreeningResult;
use App\Models\SystemAlert;
use App\Models\Transaction;
use App\Notifications\SanctionsMatchNotification;
use App\Services\Compliance\AlertTriageService;
use App\Services\Contracts\CustomerScreeningServiceInterface;
use App\Services\System\MathService;
use App\Support\LikeEscaper;
use App\Support\NameNormalizer;
use App\ValueObjects\ScreeningMatch;
use App\ValueObjects\ScreeningResponse;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomerScreeningService implements CustomerScreeningServiceInterface
{
    /**
     * Upper bound of sanction entries fetched by the SQL prefilter before
     * in-memory candidate ranking (entries are then ranked by descending
     * token overlap and truncated to max_candidates).
     */
    protected const CANDIDATE_POOL_LIMIT = 1000;

    /**
     * Minimum levenshtein similarity for a customer name token to count as a
     * fuzzy match against an entry name token during candidate prefiltering.
     */
    protected const TOKEN_MATCH_THRESHOLD = 0.8;

    /**
     * Candidate source types persisted on screening_results.source.
     */
    public const SOURCE_SANCTIONS = 'sanctions';

    public const SOURCE_ADVERSE_MEDIA = 'adverse_media';

    protected float $thresholdFlag;

    protected float $thresholdBlock;

    protected bool $useDob;

    protected bool $useNationality;

    protected int $maxCandidates;

    public function __construct(
        protected MathService $math,
        protected ?ThresholdService $thresholdService = null,
    ) {
        $this->thresholdService ??= app(ThresholdService::class);
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

            $this->stampScreenedAt($customer->id);

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
        ?int $customerId = null,
        bool $persist = true
    ): ScreeningResponse {
        $normalizedName = $this->normalizeName($name);
        $candidates = $this->findCandidates($normalizedName);

        /** @var Collection<int, ScreeningMatch> $matches */
        $matches = new Collection;
        /** @var Collection<int, ScreeningMatch> $adverseMatches */
        $adverseMatches = new Collection;
        $highestScore = 0.0;
        $highestAdverseScore = 0.0;

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

        foreach ($this->findAdverseMediaCandidates($normalizedName) as $entry) {
            $score = $this->calculateAdverseMatchScore($normalizedName, $entry);

            if ($score >= $this->thresholdFlag) {
                $matchedFields = ['name'];

                if ($entry->alias
                    && $this->levenshteinSimilarity($normalizedName, mb_strtolower(trim($entry->alias))) >= self::TOKEN_MATCH_THRESHOLD
                ) {
                    $matchedFields[] = 'alias';
                }

                $adverseMatches->push(new ScreeningMatch(
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

                $highestAdverseScore = max($highestAdverseScore, $score);
            }
        }

        // Sanctions keep block semantics (>= threshold_block). Adverse media
        // hits are always review-level: they escalate to 'flag' but never to
        // 'block' because press allegations are not listing determinations.
        $action = 'clear';
        if ($matches->isNotEmpty()) {
            $action = $highestScore >= $this->thresholdBlock ? 'block' : 'flag';
        } elseif ($adverseMatches->isNotEmpty()) {
            $action = 'flag';
        }

        $hasSanctionHits = $matches->isNotEmpty();
        $bestSanctionEntryId = $hasSanctionHits ? $matches->first()?->entryId : null;
        $bestAdverseEntryId = $adverseMatches
            ->sortByDesc(fn (ScreeningMatch $match) => $match->matchScore)
            ->first()
            ?->entryId;

        $result = null;
        if ($persist) {
            $result = $this->createResult(
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

        foreach ($customers as $customer) {
            $results->push($this->screenCustomer($customer));
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

            $officers = app(AlertTriageService::class)->getAvailableOfficers();

            foreach ($officers as $officer) {
                try {
                    $officer->notify(new SanctionsMatchNotification(
                        $latestEntry ?? new SanctionEntry,
                        "Confirmed {$listType} match on {$customer->full_name} — BNM FIU report due within 24 hours"
                    ));
                } catch (\Throwable $e) {
                    Log::warning('Failed to notify officer of confirmed sanctions match', [
                        'officer_id' => $officer->id,
                        'customer_id' => $customer->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to resolve officers for sanctions match escalation', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);
        }
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
        $inputTokens = $this->tokenize($normalizedName);

        if ($inputTokens === []) {
            return new Collection;
        }

        // Token-level SQL prefilter: an entry qualifies when ANY customer
        // name token occurs in its normalized name or aliases. The previous
        // full-name substring match missed real entries whose names are
        // longer or decorated versions of the customer's name.
        $pool = SanctionEntry::query()
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
            $entryTokens = $this->entryTokens($entry);

            if ($entryTokens === []) {
                continue;
            }

            [$matchedTokens, $overlapScore] = $this->matchInputTokens($inputTokens, $entryTokens);

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
     * Normalized tokens of an adverse media entry's name plus its alias.
     *
     * @return list<string>
     */
    protected function adverseEntryTokens(AdverseMediaEntry $entry): array
    {
        $tokens = $this->tokenize(mb_strtolower($entry->normalized_name ?? ''));

        if ($entry->alias) {
            $tokens = array_merge(
                $tokens,
                $this->tokenize(mb_strtolower(trim($entry->alias)))
            );
        }

        return array_values(array_unique($tokens));
    }

    /**
     * Adverse media candidate pool, built identically to findCandidates:
     * token-level SQL prefilter against active entries, then in-memory
     * fuzzy token ranking truncated to max_candidates.
     *
     * @return Collection<int, AdverseMediaEntry>
     */
    protected function findAdverseMediaCandidates(string $normalizedName): Collection
    {
        $inputTokens = $this->tokenize($normalizedName);

        if ($inputTokens === []) {
            return new Collection;
        }

        $pool = AdverseMediaEntry::query()
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
            $entryTokens = $this->adverseEntryTokens($entry);

            if ($entryTokens === []) {
                continue;
            }

            [$matchedTokens, $overlapScore] = $this->matchInputTokens($inputTokens, $entryTokens);

            if ($matchedTokens < count($inputTokens)) {
                continue;
            }

            $ranked[] = ['entry' => $entry, 'score' => $overlapScore];
        }

        usort($ranked, fn (array $a, array $b) => $b['score'] <=> $a['score']);

        return new Collection(array_slice(array_column($ranked, 'entry'), 0, $this->maxCandidates));
    }

    /**
     * Match scoring for adverse media candidates using identical weights to
     * calculateMatchScore: levenshtein(40), token overlap(30), phonetics
     * (15+15, computed dynamically from the article name since adverse media
     * rows carry no stored soundex/metaphone columns), alias contributions.
     */
    protected function calculateAdverseMatchScore(string $normalizedName, AdverseMediaEntry $entry): float
    {
        $scores = [];

        $entryName = mb_strtolower($entry->normalized_name ?? '');

        $scores[] = $this->levenshteinSimilarity($normalizedName, $entryName) * 40;

        $inputTokens = $this->tokenize($normalizedName);
        $entryTokens = $this->tokenize($entryName);
        $scores[] = $this->tokenMatchScore($inputTokens, $entryTokens) * 30;

        if ($entryName !== '') {
            $inputSoundex = soundex($normalizedName);
            $inputMetaphone = metaphone($normalizedName);

            if ($inputSoundex === soundex($entryName)) {
                $scores[] = 15.0;
            }
            if ($inputMetaphone === metaphone($entryName)) {
                $scores[] = 15.0;
            }
        }

        if ($entry->alias && trim($entry->alias) !== '') {
            $aliasNormalized = mb_strtolower(trim($entry->alias));
            $scores[] = $this->levenshteinSimilarity($normalizedName, $aliasNormalized) * 20;
            $scores[] = $this->tokenMatchScore($inputTokens, $this->tokenize($aliasNormalized)) * 10;
        }

        return min((array_sum($scores) / 100.0) * 100, 100.0);
    }

    /**
     * Normalized tokens of an entry's name plus all of its aliases.
     */
    protected function entryTokens(SanctionEntry $entry): array
    {
        $tokens = $this->tokenize(mb_strtolower($entry->normalized_name ?? ''));

        if (is_array($entry->aliases)) {
            foreach ($entry->aliases as $alias) {
                $tokens = array_merge(
                    $tokens,
                    $this->tokenize(mb_strtolower(trim((string) $alias)))
                );
            }
        }

        return array_values(array_unique($tokens));
    }

    /**
     * Fuzzy-match each input token against the entry's tokens (exact or
     * levenshtein similarity >= TOKEN_MATCH_THRESHOLD).
     *
     * @return array{0: int, 1: float} Number of matched input tokens and the
     *                                 summed best similarity per matched token
     *                                 (ranking weight).
     */
    protected function matchInputTokens(array $inputTokens, array $entryTokens): array
    {
        $matchedCount = 0;
        $similaritySum = 0.0;

        foreach ($inputTokens as $inputToken) {
            $best = 0.0;

            foreach ($entryTokens as $entryToken) {
                if ($inputToken === $entryToken) {
                    $best = 1.0;

                    break;
                }

                $similarity = $this->levenshteinSimilarity($inputToken, $entryToken);

                if ($similarity > $best) {
                    $best = $similarity;
                }
            }

            if ($best >= self::TOKEN_MATCH_THRESHOLD) {
                $matchedCount++;
                $similaritySum += $best;
            }
        }

        return [$matchedCount, $similaritySum];
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
                $aliasNormalized = NameNormalizer::normalize((string) $alias);
                $aliasScore = $this->levenshteinSimilarity($normalizedName, $aliasNormalized);
                $scores[] = $aliasScore * 20;

                $aliasTokens = $this->tokenize($aliasNormalized);
                $aliasTokenScore = $this->tokenMatchScore($inputTokens, $aliasTokens);
                $scores[] = $aliasTokenScore * 10;
            }
        }

        if ($dob && $this->useDob && $entry->date_of_birth) {
            $dobScore = $this->dateMatchScore($dob, $entry->date_of_birth->format('Y-m-d'));

            if ($dobScore > 0.0) {
                $scores[] = $dobScore;
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
        // Native levenshtein()/strlen() are byte-based and corrupt (or
        // outright reject) multibyte names; compare character arrays instead.
        $aChars = $this->stringToChars($a);
        $bChars = $this->stringToChars($b);
        $maxLen = max(count($aChars), count($bChars));

        if ($maxLen === 0) {
            return 1.0;
        }

        $distance = $this->levenshteinDistance($aChars, $bChars);

        return 1.0 - ($distance / $maxLen);
    }

    /**
     * @return list<string> Individual characters of a (multibyte) string.
     */
    protected function stringToChars(string $value): array
    {
        if ($value === '') {
            return [];
        }

        $chars = mb_str_split($value);

        return $chars;
    }

    /**
     * Levenshtein distance over character arrays so it is safe for
     * multibyte strings and for lengths beyond levenshtein()'s 255-byte cap.
     *
     * @param  list<string>  $a
     * @param  list<string>  $b
     */
    protected function levenshteinDistance(array $a, array $b): int
    {
        $bLength = count($b);

        if ($a === []) {
            return $bLength;
        }

        if ($b === []) {
            return count($a);
        }

        $previousRow = range(0, $bLength);

        foreach ($a as $i => $aChar) {
            $currentRow = [$i + 1];

            for ($j = 0; $j < $bLength; $j++) {
                $cost = $aChar === $b[$j] ? 0 : 1;
                $currentRow[$j + 1] = min(
                    $currentRow[$j] + 1,
                    $previousRow[$j + 1] + 1,
                    $previousRow[$j] + $cost
                );
            }

            $previousRow = $currentRow;
        }

        return $previousRow[$bLength];
    }

    protected function tokenize(string $text): array
    {
        $tokens = preg_split('/\s+/', NameNormalizer::normalize($text), -1, PREG_SPLIT_NO_EMPTY);

        if ($tokens === false) {
            return [];
        }

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

        // Full date comparison: the previous year+month check ignored the
        // day of month entirely.
        return $d1->year === $d2->year && $d1->month === $d2->month && $d1->day === $d2->day;
    }

    /**
     * Graded date-of-birth contribution to the match score:
     * exact full date = full points, year+month = half, year-only = minimal.
     * The maximum possible contribution (10.0) is unchanged so overall
     * flag/block threshold semantics stay intact.
     */
    protected function dateMatchScore(string $date1, string $date2): float
    {
        if ($this->datesMatch($date1, $date2)) {
            return 10.0;
        }

        $d1 = Carbon::parse($date1);
        $d2 = Carbon::parse($date2);

        if ($d1->year === $d2->year && $d1->month === $d2->month) {
            return 5.0;
        }

        if ($d1->year === $d2->year) {
            return 2.0;
        }

        return 0.0;
    }

    protected function nationalitiesMatch(string $nat1, string $nat2): bool
    {
        return strcasecmp(trim($nat1), trim($nat2)) === 0;
    }

    protected function normalizeName(string $name): string
    {
        return NameNormalizer::normalize($name);
    }

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
     */
    private function analyzeRelatedPartyTransactions(Customer $relatedParty, ?CustomerRelation $relation = null): array
    {
        // Get all transactions for the related party in last 12 months
        $transactions = Transaction::where('customer_id', $relatedParty->id)
            ->where('created_at', '>=', now()->subMonths(12))
            ->get();

        $transactionCount = $transactions->count();
        // Sum with bcmath to avoid float precision loss on large monetary totals
        $totalAmount = '0';
        foreach ($transactions as $transaction) {
            $totalAmount = $this->math->add($totalAmount, (string) $transaction->amount_local);
        }

        // Store analysis via customer relation additional_info
        $analysis = [
            'analysis_date' => now()->toIso8601String(),
            'transaction_count' => $transactionCount,
            'total_amount_myrr' => $totalAmount,
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
            'total_amount' => $totalAmount,
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
}
