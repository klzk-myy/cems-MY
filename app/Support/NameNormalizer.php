<?php

namespace App\Support;

/**
 * Canonical name normalizer for sanctions screening.
 *
 * Every path that stores or queries sanction-entry names (OpenSanctions
 * import, manual entry CRUD, customer screening input) must produce the
 * same normalized form — divergence means the LIKE prefilter and token
 * ranking silently miss real matches.
 *
 * Semantics: lowercase, apostrophes dropped (they sit inside name tokens:
 * O'Malley -> omalley), all other punctuation becomes a space (Smith-Jones
 * -> smith jones), digits kept, whitespace collapsed. Punctuation-as-space
 * is deliberate: hyphenated and spaced spellings of the same name must
 * normalize identically.
 */
class NameNormalizer
{
    public static function normalize(string $name): string
    {
        $name = mb_strtolower(trim($name), 'UTF-8');
        $name = str_replace(["'", '’', 'ʼ', '`'], '', $name);
        $name = (string) preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $name);

        return trim((string) preg_replace('/\s+/', ' ', $name));
    }

    /**
     * Normalized name plus phonetic codes, shaped for SanctionEntry storage.
     *
     * @return array{normalized_name: string, soundex_code: string|null, metaphone_code: string|null}
     */
    public static function profile(string $name): array
    {
        $normalized = self::normalize($name);

        return [
            'normalized_name' => $normalized,
            'soundex_code' => $normalized !== '' ? soundex($normalized) : null,
            'metaphone_code' => $normalized !== '' ? metaphone($normalized) : null,
        ];
    }
}
