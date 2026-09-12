<?php

namespace App\Http\Concerns;

use App\Support\NameNormalizer;

trait SanctionEntryNormalizer
{
    protected function normalizeEntityName(string $entityName): array
    {
        return NameNormalizer::profile($entityName);
    }
}
