<?php

namespace App\Services\Compliance;

use App\Enums\SanctionStatus;
use App\Exceptions\Domain\SanctionsImportException;
use App\Models\SanctionEntry;
use App\Models\SanctionList;
use App\Services\Compliance\Parsing\OpenSanctionsJsonParser;
use App\Services\Compliance\Parsing\SanctionsEntryMapper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Writes parsed sanction entries to the database: full-sync upsert +
 * deactivation sweep, and delta ADD/DEL op application. Stateless — every
 * operation returns its own counts so callers own the import bookkeeping.
 */
class SanctionsEntrySynchronizer
{
    public function __construct(
        protected SanctionsEntryMapper $mapper,
        protected OpenSanctionsJsonParser $jsonParser,
    ) {}

    /**
     * @param  iterable<array-key, array<string, mixed>>  $entries
     * @return array{created: int, updated: int, deactivated: int, errors: int}
     */
    public function syncEntries(iterable $entries, SanctionList $list): array
    {
        $counts = ['created' => 0, 'updated' => 0, 'deactivated' => 0, 'errors' => 0];

        DB::transaction(function () use ($entries, $list, &$counts) {
            // Partial-column hydration only: loading full models (details
            // JSON included) for the entire list defeats the lazy stream's
            // memory discipline on OFAC-scale feeds.
            $existingByRef = SanctionEntry::where('list_id', $list->id)
                ->whereNotNull('reference_number')
                ->get(['id', 'reference_number', 'status'])
                ->keyBy('reference_number');

            $importedRefs = [];
            $seen = 0;

            foreach ($entries as $entryData) {
                $seen++;
                $ref = $entryData['reference_number'] ?? null;

                try {
                    $this->upsertEntry($entryData, $existingByRef, $counts);
                    $importedRefs[(string) $ref] = true;
                } catch (\Exception $e) {
                    Log::error('Failed to sync sanction entry', [
                        'reference_number' => $ref,
                        'error' => $e->getMessage(),
                    ]);
                    $counts['errors']++;
                }
            }

            // Safety guard: if the source produced zero parseable entries but
            // the list currently holds active entries, abort the transaction
            // (rolls back any creates) rather than deactivate the whole list —
            // a broken upstream must never wipe the screening dataset. With
            // lazy entry streams, emptiness is only known after iteration.
            if ($seen === 0
                && $list->entries()->where('status', SanctionStatus::Active->value)->exists()) {
                throw new SanctionsImportException(
                    'Import produced no valid entries while the list has active entries; '.
                    'refusing to deactivate the existing list. Existing entries preserved.'
                );
            }

            $refsToDeactivate = $existingByRef->keys()->filter(fn ($ref) => ! isset($importedRefs[$ref]));
            foreach ($refsToDeactivate as $ref) {
                $existing = $existingByRef->get($ref);
                if ($existing->status === SanctionStatus::Active) {
                    $existing->update(['status' => SanctionStatus::Inactive]);
                    $counts['deactivated']++;
                }
            }
        });

        return $counts;
    }

    /**
     * Apply a delta file's ops inside one transaction: ADD upserts through the
     * shared entry path; DEL deactivates by reference_number.
     *
     * @param  iterable<array-key, array<string, mixed>>  $ops
     * @return array{created: int, updated: int, deactivated: int, errors: int}
     */
    public function applyDeltaOps(iterable $ops, SanctionList $list): array
    {
        $counts = ['created' => 0, 'updated' => 0, 'deactivated' => 0, 'errors' => 0];

        DB::transaction(function () use ($ops, $list, &$counts) {
            // Partial-column hydration only — see syncEntries().
            $existingByRef = SanctionEntry::where('list_id', $list->id)
                ->whereNotNull('reference_number')
                ->get(['id', 'reference_number', 'status'])
                ->keyBy('reference_number');

            foreach ($ops as $op) {
                $entity = $op['entity'] ?? [];

                try {
                    if (($op['op'] ?? null) === 'DEL') {
                        $ref = $entity['id'] ?? null;
                        if ($ref !== null && $existingByRef->has((string) $ref)) {
                            $existing = $existingByRef->get((string) $ref);
                            if ($existing->status === SanctionStatus::Active) {
                                $existing->update(['status' => SanctionStatus::Inactive]);
                                $counts['deactivated']++;
                            }
                        }

                        continue;
                    }

                    if (($op['op'] ?? null) !== 'ADD') {
                        continue;
                    }

                    $entryData = $this->mapper->parseEntry(
                        $this->jsonParser->flattenNestedEntity($entity),
                        $list
                    );

                    if ($entryData === null) {
                        continue;
                    }

                    $this->upsertEntry($entryData, $existingByRef, $counts);
                } catch (\Exception $e) {
                    Log::error('Failed to apply delta op', [
                        'op' => $op['op'] ?? null,
                        'entity_id' => $entity['id'] ?? null,
                        'error' => $e->getMessage(),
                    ]);
                    $counts['errors']++;
                }
            }
        });

        return $counts;
    }

    /**
     * Create or update one entry by reference_number, keeping the ref map
     * current so later ops in the same run see just-created rows. Shared by
     * full sync (syncEntries) and delta apply (applyDeltaOps).
     *
     * @param  array<string, mixed>  $entryData
     * @param  Collection<string, SanctionEntry>  $existingByRef
     * @param  array{created: int, updated: int, deactivated: int, errors: int}  $counts
     */
    protected function upsertEntry(array $entryData, Collection $existingByRef, array &$counts): void
    {
        $ref = $entryData['reference_number'] ?? null;

        if ($ref && $existingByRef->has($ref)) {
            $existingByRef->get($ref)->update([
                'entity_name' => $entryData['entity_name'],
                'normalized_name' => $entryData['normalized_name'],
                'soundex_code' => $entryData['soundex_code'] ?? null,
                'metaphone_code' => $entryData['metaphone_code'] ?? null,
                'entity_type' => $entryData['entity_type'],
                'aliases' => $entryData['aliases'],
                'nationality' => $entryData['nationality'],
                'date_of_birth' => $entryData['date_of_birth'],
                'listing_date' => $entryData['listing_date'] ?? null,
                'details' => $entryData['details'],
                'status' => SanctionStatus::Active,
            ]);
            $counts['updated']++;

            return;
        }

        $entry = SanctionEntry::create($entryData);
        if ($ref) {
            $existingByRef->put((string) $ref, $entry);
        }
        $counts['created']++;
    }
}
