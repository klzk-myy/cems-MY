<?php

namespace App\Services\Compliance\Parsing;

/**
 * Parses a downloaded sanctions source file into a stream of raw
 * OpenSanctions-style item arrays, which SanctionsEntryMapper then
 * normalizes into sanction-entry rows.
 *
 * @phpstan-type RawItem array<string, mixed>
 */
interface SanctionsFeedParser
{
    /**
     * @return iterable<array-key, array<string, mixed>>
     */
    public function parse(string $filepath): iterable;
}
