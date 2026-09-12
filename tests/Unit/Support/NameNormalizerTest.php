<?php

namespace Tests\Unit\Support;

use App\Support\NameNormalizer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NameNormalizerTest extends TestCase
{
    #[Test]
    public function it_normalizes_case_and_whitespace(): void
    {
        $this->assertSame('john doe', NameNormalizer::normalize('  JOHN   Doe  '));
    }

    #[Test]
    public function it_treats_punctuation_as_token_separators(): void
    {
        $this->assertSame('john doe smith', NameNormalizer::normalize('John-Doe Smith'));
        $this->assertSame('john doe jr', NameNormalizer::normalize('John Doe Jr.'));
        $this->assertSame('john doe', NameNormalizer::normalize('John-Doe'));
        $this->assertSame('john doe', NameNormalizer::normalize('John Doe'));
    }

    #[Test]
    public function it_drops_apostrophes_inside_tokens(): void
    {
        $this->assertSame('john omalley', NameNormalizer::normalize('John O’Malley'));
        $this->assertSame('john omalley', NameNormalizer::normalize("John O'Malley"));
    }

    #[Test]
    public function it_keeps_digits_and_unicode_letters(): void
    {
        $this->assertSame('agent 47', NameNormalizer::normalize('Agent 47'));
        $this->assertSame('mohd رزق', NameNormalizer::normalize('Mohd رزق'));
    }

    #[Test]
    public function profile_returns_normalized_name_and_phonetic_codes(): void
    {
        $profile = NameNormalizer::profile('John Doe');

        $this->assertSame('john doe', $profile['normalized_name']);
        $this->assertSame(soundex('john doe'), $profile['soundex_code']);
        $this->assertSame(metaphone('john doe'), $profile['metaphone_code']);
    }

    #[Test]
    public function profile_returns_null_phonetics_for_empty_names(): void
    {
        $profile = NameNormalizer::profile('!!!');

        $this->assertSame('', $profile['normalized_name']);
        $this->assertNull($profile['soundex_code']);
        $this->assertNull($profile['metaphone_code']);
    }
}
