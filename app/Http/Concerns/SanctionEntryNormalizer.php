<?php

namespace App\Http\Concerns;

trait SanctionEntryNormalizer
{
    protected function normalizeEntityName(string $entityName): array
    {
        return [
            'normalized_name' => strtolower(preg_replace('/[^\p{L}\s]/u', '', $entityName)),
            'soundex_code' => soundex($entityName),
            'metaphone_code' => metaphone($entityName),
        ];
    }
}
