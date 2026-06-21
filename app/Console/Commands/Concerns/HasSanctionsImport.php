<?php

namespace App\Console\Commands\Concerns;

use App\Models\SanctionList;

trait HasSanctionsImport
{
    protected function getSanctionList(string $name): ?SanctionList
    {
        return SanctionList::where('name', $name)->first();
    }

    protected function getEnabledSanctionLists()
    {
        return SanctionList::where('is_active', true)->get();
    }

    protected function formatImportResult(array $result): string
    {
        return "Added: {$result['added']}, Updated: {$result['updated']}, Deactivated: {$result['deactivated']}";
    }
}
