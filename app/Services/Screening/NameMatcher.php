<?php

namespace App\Services\Screening;

use App\Support\NameNormalizer;
use Carbon\Carbon;

/**
 * Pure name-matching primitives for sanctions/adverse-media screening:
 * normalization, tokenization, levenshtein similarity, phonetic and
 * date/nationality scoring. No database access — entry data arrives as
 * plain arrays so the matcher is unit-testable in isolation.
 */
final class NameMatcher
{
    /**
     * Minimum levenshtein similarity for a name token to count as a fuzzy
     * match against an entry token during candidate prefiltering.
     */
    public const TOKEN_MATCH_THRESHOLD = 0.8;

    protected bool $useDob;

    protected bool $useNationality;

    public function __construct()
    {
        $this->useDob = (bool) config('sanctions.matching.use_dob', true);
        $this->useNationality = (bool) config('sanctions.matching.use_nationality', true);
    }

    public function normalizeName(string $name): string
    {
        return NameNormalizer::normalize($name);
    }

    /**
     * @return array<int, string>
     */
    public function tokenize(string $text): array
    {
        $tokens = preg_split('/\s+/', NameNormalizer::normalize($text), -1, PREG_SPLIT_NO_EMPTY);

        if ($tokens === false) {
            return [];
        }

        return array_unique($tokens);
    }

    /**
     * Normalized tokens of an entry's name plus all of its aliases.
     *
     * @param  array<int, string>|null  $aliases
     * @return list<string>
     */
    public function entryTokens(?string $normalizedName, ?array $aliases): array
    {
        $tokens = $this->tokenize(mb_strtolower($normalizedName ?? ''));

        if (is_array($aliases)) {
            foreach ($aliases as $alias) {
                $tokens = array_merge(
                    $tokens,
                    $this->tokenize(mb_strtolower(trim((string) $alias)))
                );
            }
        }

        return array_values(array_unique($tokens));
    }

    /**
     * Normalized tokens of an adverse media entry's name plus its alias.
     *
     * @return list<string>
     */
    public function adverseEntryTokens(?string $normalizedName, ?string $alias): array
    {
        $tokens = $this->tokenize(mb_strtolower($normalizedName ?? ''));

        if ($alias) {
            $tokens = array_merge(
                $tokens,
                $this->tokenize(mb_strtolower(trim($alias)))
            );
        }

        return array_values(array_unique($tokens));
    }

    /**
     * Fuzzy-match each input token against the entry's tokens (exact or
     * levenshtein similarity >= TOKEN_MATCH_THRESHOLD).
     *
     * @param  array<int, string>  $inputTokens
     * @param  array<int, string>  $entryTokens
     * @return array{0: int, 1: float} Number of matched input tokens and the
     *                                 summed best similarity per matched token
     *                                 (ranking weight).
     */
    public function matchInputTokens(array $inputTokens, array $entryTokens): array
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

    /**
     * Sanction-entry match score.
     *
     * @param  array{normalized_name: ?string, soundex_code: ?string, metaphone_code: ?string, aliases: mixed, date_of_birth: ?string, nationality: ?string}  $entry
     */
    public function calculateMatchScore(
        string $normalizedName,
        array $entry,
        ?string $dob = null,
        ?string $nationality = null
    ): float {
        $scores = [];

        $levenshteinScore = $this->levenshteinSimilarity(
            $normalizedName,
            mb_strtolower($entry['normalized_name'] ?? '')
        );
        $scores[] = $levenshteinScore * 40;

        $inputTokens = $this->tokenize($normalizedName);
        $entryTokens = $this->tokenize(mb_strtolower($entry['normalized_name'] ?? ''));
        $tokenScore = $this->tokenMatchScore($inputTokens, $entryTokens);
        $scores[] = $tokenScore * 30;

        if (($entry['soundex_code'] ?? null) && ($entry['metaphone_code'] ?? null)) {
            $inputSoundex = soundex($normalizedName);
            $inputMetaphone = metaphone($normalizedName);

            if ($inputSoundex === $entry['soundex_code']) {
                $scores[] = 15.0;
            }
            if ($inputMetaphone === $entry['metaphone_code']) {
                $scores[] = 15.0;
            }
        }

        if (! empty($entry['aliases']) && is_array($entry['aliases'])) {
            foreach ($entry['aliases'] as $alias) {
                $aliasNormalized = NameNormalizer::normalize((string) $alias);
                $aliasScore = $this->levenshteinSimilarity($normalizedName, $aliasNormalized);
                $scores[] = $aliasScore * 20;

                $aliasTokens = $this->tokenize($aliasNormalized);
                $aliasTokenScore = $this->tokenMatchScore($inputTokens, $aliasTokens);
                $scores[] = $aliasTokenScore * 10;
            }
        }

        if ($dob && $this->useDob && ($entry['date_of_birth'] ?? null)) {
            $dobScore = $this->dateMatchScore($dob, $entry['date_of_birth']);

            if ($dobScore > 0.0) {
                $scores[] = $dobScore;
            }
        }

        if ($nationality && $this->useNationality && ($entry['nationality'] ?? null)) {
            if ($this->nationalitiesMatch($nationality, $entry['nationality'])) {
                $scores[] = 5.0;
            }
        }

        $totalScore = array_sum($scores);
        $maxPossibleScore = 100.0;

        return min(($totalScore / $maxPossibleScore) * 100, 100.0);
    }

    /**
     * Match scoring for adverse media candidates using identical weights to
     * calculateMatchScore: levenshtein(40), token overlap(30), phonetics
     * (15+15, computed dynamically from the article name since adverse media
     * rows carry no stored soundex/metaphone columns), alias contributions.
     *
     * @param  array{normalized_name: ?string, alias: ?string}  $entry
     */
    public function calculateAdverseMatchScore(string $normalizedName, array $entry): float
    {
        $scores = [];

        $entryName = mb_strtolower($entry['normalized_name'] ?? '');

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

        $alias = $entry['alias'] ?? null;
        if ($alias && trim($alias) !== '') {
            $aliasNormalized = mb_strtolower(trim($alias));
            $scores[] = $this->levenshteinSimilarity($normalizedName, $aliasNormalized) * 20;
            $scores[] = $this->tokenMatchScore($inputTokens, $this->tokenize($aliasNormalized)) * 10;
        }

        return min((array_sum($scores) / 100.0) * 100, 100.0);
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

        return mb_str_split($value);
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

    /**
     * @param  array<int, string>  $tokens1
     * @param  array<int, string>  $tokens2
     */
    public function tokenMatchScore(array $tokens1, array $tokens2): float
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

    public function datesMatch(string $date1, string $date2): bool
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
    public function dateMatchScore(string $date1, string $date2): float
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

    public function nationalitiesMatch(string $nat1, string $nat2): bool
    {
        return strcasecmp(trim($nat1), trim($nat2)) === 0;
    }
}
