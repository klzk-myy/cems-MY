<?php

namespace Tests\Unit\Services\Screening;

use App\Services\Screening\NameMatcher;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NameMatcherTest extends TestCase
{
    private NameMatcher $matcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->matcher = new NameMatcher;
    }

    #[Test]
    public function levenshtein_identical_strings_score_one(): void
    {
        $this->assertSame(1.0, $this->matcher->levenshteinSimilarity('john smith', 'john smith'));
    }

    #[Test]
    public function levenshtein_is_multibyte_safe(): void
    {
        $score = $this->matcher->levenshteinSimilarity('müller', 'muller');

        $this->assertGreaterThan(0.5, $score);
        $this->assertLessThan(1.0, $score);
    }

    #[Test]
    public function levenshtein_empty_strings_score_one(): void
    {
        $this->assertSame(1.0, $this->matcher->levenshteinSimilarity('', ''));
    }

    #[Test]
    public function tokenize_normalizes_and_dedupes(): void
    {
        $this->assertSame(
            ['john', 'smith'],
            array_values($this->matcher->tokenize('  John   SMITH john '))
        );
    }

    #[Test]
    public function token_match_score_is_jaccard(): void
    {
        // {john, smith} vs {john, doe} => intersection 1 / union 3
        $this->assertEqualsWithDelta(
            1 / 3,
            $this->matcher->tokenMatchScore(['john', 'smith'], ['john', 'doe']),
            0.0001
        );
        $this->assertSame(0.0, $this->matcher->tokenMatchScore([], ['x']));
    }

    #[Test]
    public function match_input_tokens_requires_threshold(): void
    {
        [$matched, $sum] = $this->matcher->matchInputTokens(['john', 'smith'], ['john', 'smith']);

        $this->assertSame(2, $matched);
        $this->assertSame(2.0, $sum);

        // 'xyz' shares no near-match with the entry tokens.
        [$matched] = $this->matcher->matchInputTokens(['john', 'xyz'], ['john', 'smith']);
        $this->assertSame(1, $matched);
    }

    #[Test]
    public function dates_match_requires_full_date(): void
    {
        $this->assertTrue($this->matcher->datesMatch('1980-05-14', '1980-05-14'));
        $this->assertFalse($this->matcher->datesMatch('1980-05-14', '1980-05-15'));
        $this->assertFalse($this->matcher->datesMatch('1980-05-14', '1981-05-14'));
    }

    #[Test]
    public function date_match_score_is_graded(): void
    {
        $this->assertSame(10.0, $this->matcher->dateMatchScore('1980-05-14', '1980-05-14'));
        $this->assertSame(5.0, $this->matcher->dateMatchScore('1980-05-14', '1980-05-20'));
        $this->assertSame(2.0, $this->matcher->dateMatchScore('1980-05-14', '1980-11-20'));
        $this->assertSame(0.0, $this->matcher->dateMatchScore('1980-05-14', '1981-05-14'));
    }

    #[Test]
    public function nationalities_match_case_insensitively(): void
    {
        $this->assertTrue($this->matcher->nationalitiesMatch('Malaysian', ' malaysian '));
        $this->assertFalse($this->matcher->nationalitiesMatch('Malaysian', 'Singaporean'));
    }

    #[Test]
    public function phonetic_codes_contribute_to_match_score(): void
    {
        $name = 'jon smith';
        $normalized = $this->matcher->normalizeName($name);

        $withoutPhonetic = $this->matcher->calculateMatchScore($normalized, [
            'normalized_name' => 'john smith',
            'soundex_code' => null,
            'metaphone_code' => null,
            'aliases' => [],
            'date_of_birth' => null,
            'nationality' => null,
        ]);

        $withPhonetic = $this->matcher->calculateMatchScore($normalized, [
            'normalized_name' => 'john smith',
            'soundex_code' => soundex($normalized),
            'metaphone_code' => metaphone($normalized),
            'aliases' => [],
            'date_of_birth' => null,
            'nationality' => null,
        ]);

        $this->assertGreaterThan($withoutPhonetic, $withPhonetic);
    }

    #[Test]
    public function dob_and_nationality_add_to_match_score(): void
    {
        $normalized = $this->matcher->normalizeName('john smith');
        $entry = [
            'normalized_name' => 'john smith',
            'soundex_code' => null,
            'metaphone_code' => null,
            'aliases' => [],
            'date_of_birth' => '1980-05-14',
            'nationality' => 'Malaysian',
        ];

        $base = $this->matcher->calculateMatchScore($normalized, $entry);
        $withDob = $this->matcher->calculateMatchScore($normalized, $entry, dob: '1980-05-14');
        $withBoth = $this->matcher->calculateMatchScore($normalized, $entry, dob: '1980-05-14', nationality: 'malaysian');

        $this->assertSame($base + 10.0, $withDob);
        $this->assertSame($base + 15.0, $withBoth);
    }

    #[Test]
    public function adverse_match_score_weighs_alias(): void
    {
        $normalized = $this->matcher->normalizeName('jane doe');

        $withoutAlias = $this->matcher->calculateAdverseMatchScore($normalized, [
            'normalized_name' => 'john doe',
            'alias' => null,
        ]);

        $withAlias = $this->matcher->calculateAdverseMatchScore($normalized, [
            'normalized_name' => 'john doe',
            'alias' => 'jane doe',
        ]);

        $this->assertGreaterThan($withoutAlias, $withAlias);
    }

    #[Test]
    public function entry_tokens_include_aliases(): void
    {
        $tokens = $this->matcher->entryTokens('john smith', ['johnny smyth']);

        $this->assertContains('johnny', $tokens);
        $this->assertContains('smith', $tokens);
    }
}
