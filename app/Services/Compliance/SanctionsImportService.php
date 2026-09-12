<?php

namespace App\Services\Compliance;

use App\Enums\EntityType;
use App\Enums\SanctionStatus;
use App\Enums\UpdateStatus;
use App\Exceptions\Domain\SanctionsImportException;
use App\Models\SanctionEntry;
use App\Models\SanctionImportLog;
use App\Models\SanctionList;
use App\Services\System\MathService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\LazyCollection;
use SimpleXMLElement;

class SanctionsImportService
{
    protected int $created = 0;

    protected int $updated = 0;

    protected int $deactivated = 0;

    protected int $errors = 0;

    /**
     * Temp file downloaded by fetchSource() for the in-flight import. Deleted
     * once the lazy stream has been consumed — the archive copy persists.
     */
    protected ?string $pendingTempFile = null;

    public function __construct(
        protected MathService $mathService,
        protected SanctionsDownloadService $downloadService,
    ) {}

    public function import(SanctionList $list, bool $manual = false): array
    {
        $this->pendingTempFile = null;

        try {
            return $this->importWithData($list, $this->fetchSource($list), $manual);
        } finally {
            if ($this->pendingTempFile !== null && file_exists($this->pendingTempFile)) {
                unlink($this->pendingTempFile);
            }
            $this->pendingTempFile = null;
        }
    }

    /**
     * Build the attribution columns for a SanctionImportLog row.
     *
     * Manual imports are attributed to the authenticated officer; scheduled
     * (job-driven) imports run without a session, so user_id stays null and
     * triggered_by records the scheduler.
     *
     * @return array{triggered_by: string, user_id: int|null}
     */
    protected function attributionFor(bool $manual): array
    {
        $userId = null;

        if ($manual) {
            $authenticatedId = auth()->id();
            $userId = $authenticatedId === null ? null : (int) $authenticatedId;
        }

        return [
            'triggered_by' => $manual ? 'manual' : 'scheduled',
            'user_id' => $userId,
        ];
    }

    /**
     * @param  iterable<array-key, mixed>|array<string, mixed>  $data  Decoded source payload (`['results' => [...]]` doc) or a lazy stream of flat/nested items.
     */
    public function importWithData(SanctionList $list, iterable $data, bool $manual = false): array
    {
        $this->resetCounters();

        $list->update(['last_attempted_at' => now(), 'update_status' => UpdateStatus::Pending]);

        try {
            $entries = $this->parseEntries($data, $list);
            $result = $this->syncEntries($entries, $list);

            $list->update([
                'last_updated_at' => now(),
                'update_status' => UpdateStatus::Success,
                'last_error_message' => null,
                'entry_count' => $list->entries()->where('status', 'active')->count(),
            ]);

            SanctionImportLog::create([
                'list_id' => $list->id,
                'imported_at' => now(),
                'source_url' => $list->source_url,
                'records_added' => $this->created,
                'records_updated' => $this->updated,
                'records_deactivated' => $this->deactivated,
                'is_manual' => $manual,
                ...$this->attributionFor($manual),
                'status' => UpdateStatus::Success->value,
            ]);

            return $this->enrichResult($result);

        } catch (\Exception $e) {
            $list->update([
                'update_status' => UpdateStatus::Failed,
                'last_error_message' => $e->getMessage(),
            ]);

            SanctionImportLog::create([
                'list_id' => $list->id,
                'imported_at' => now(),
                'source_url' => $list->source_url,
                'records_added' => $this->created,
                'records_updated' => $this->updated,
                'records_deactivated' => $this->deactivated,
                'is_manual' => $manual,
                ...$this->attributionFor($manual),
                'status' => UpdateStatus::Failed->value,
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Import a downloaded JSON file (e.g. OpenSanctions targets.nested.json).
     *
     * This is the canonical import format; the scheduled download jobs delegate
     * here so the whole auto-update pipeline shares one sync path.
     */
    public function importFromJson(string $filepath, int $listId): array
    {
        if (! is_readable($filepath)) {
            throw new SanctionsImportException("Failed to read import file: {$filepath}", $filepath);
        }

        return $this->importWithData(
            SanctionList::findOrFail($listId),
            $this->streamSourceFile($filepath),
            false
        );
    }

    /**
     * Import a downloaded XML file (supports the UN Consolidated List and OFAC
     * SDN structures, normalised into the OpenSanctions entry shape).
     */
    public function importFromXml(string $filepath, int $listId, string $listType = ''): array
    {
        $content = file_get_contents($filepath);
        if ($content === false) {
            throw new SanctionsImportException("Failed to read import file: {$filepath}", $filepath);
        }

        $previousLibxmlSetting = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content);
        if ($xml === false) {
            $messages = array_map(fn ($e) => trim($e->message), libxml_get_errors());
            libxml_clear_errors();
            libxml_use_internal_errors($previousLibxmlSetting);
            throw new SanctionsImportException('Import file is not valid XML'.($messages !== [] ? ': '.implode('; ', $messages) : ''));
        }
        libxml_clear_errors();
        libxml_use_internal_errors($previousLibxmlSetting);

        $records = [];
        foreach ($this->collectXmlRecords($xml) as $record) {
            $parsed = $this->parseXmlEntry($record);
            if ($parsed !== null) {
                $records[] = $parsed;
            }
        }

        return $this->importWithData(SanctionList::findOrFail($listId), ['results' => $records], false);
    }

    /**
     * Import a downloaded CSV file (supports the EU consolidated list export).
     */
    public function importFromCsv(string $filepath, int $listId, bool $hasHeader = true): array
    {
        $handle = fopen($filepath, 'r');
        if (! $handle) {
            throw new SanctionsImportException("Failed to read import file: {$filepath}", $filepath);
        }

        $records = [];
        $header = null;

        try {
            while (($row = fgetcsv($handle)) !== false) {
                if ($header === null) {
                    if ($hasHeader) {
                        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $row);

                        continue;
                    }

                    $header = range(0, max(count($row) - 1, 0));
                }

                $mapped = $this->mapCsvRow($row, $header);
                if ($mapped !== null) {
                    $records[] = $mapped;
                }
            }
        } finally {
            fclose($handle);
        }

        return $this->importWithData(SanctionList::findOrFail($listId), ['results' => $records], false);
    }

    /**
     * Fetch a list's source through the download service so every entry point
     * (scheduled jobs, API trigger, webhook) gets the same URL allowlist,
     * per-hop redirect validation, format check, and archive behavior as the
     * manual web import. Returns a lazy stream of flat items — large lists
     * (OFAC SDN ~80MB JSONL) are never fully materialized in memory.
     *
     * @return iterable<array-key, array<string, mixed>>
     *
     * @phpstan-impure
     */
    public function fetchSource(SanctionList $list): iterable
    {
        $result = $this->downloadService->download(
            $list->source_url,
            $list->slug.'_'.time().'.'.strtolower($list->source_format ?? 'json'),
            $list->source_format ?? 'JSON',
            (int) config('sanctions.download.retry_attempts', 3),
        );

        if (! $result['success']) {
            throw new SanctionsImportException(
                "Failed to fetch sanctions data: {$result['error']}"
            );
        }

        if ($result['filepath'] && file_exists($result['filepath'])) {
            $this->downloadService->archiveFile($result['filepath'], $list->list_type->value ?? 'unknown');
            $this->pendingTempFile = $result['filepath'];
        }

        return $this->streamSourceFile($result['filepath']);
    }

    /**
     * Stream flat entry items from a downloaded source file. Detects
     * single-document JSON vs JSONL (one entity per line — the OpenSanctions
     * targets.nested.json exports); nested FollowTheMoney entities are
     * flattened so parseOpenSanctionsEntry() can consume both shapes.
     *
     * @return iterable<array-key, array<string, mixed>>
     */
    public function streamSourceFile(string $filepath): iterable
    {
        $size = filesize($filepath);
        if ($size !== false && $size > 0 && $size <= 16 * 1024 * 1024) {
            $content = file_get_contents($filepath);
            if ($content !== false) {
                $decoded = json_decode($content, true);
                if (is_array($decoded)) {
                    yield from $decoded['results'] ?? $decoded;

                    return;
                }
            }
        }

        $handle = fopen($filepath, 'r');
        if (! $handle) {
            return;
        }

        try {
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                $item = json_decode($line, true);
                if (! is_array($item)) {
                    return;
                }
                yield $this->flattenNestedEntity($item);
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Flatten an OpenSanctions nested FollowTheMoney entity
     * ({id, caption, schema, properties:{name, alias, birthDate, ...}})
     * into the flat entry shape parseOpenSanctionsEntry() consumes.
     * Flat records are returned unchanged.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    protected function flattenNestedEntity(array $item): array
    {
        $props = $item['properties'] ?? null;
        if (! is_array($props)) {
            return $item;
        }

        $names = $props['name'] ?? [];
        if ($names === [] && isset($item['caption'])) {
            $names = [$item['caption']];
        }

        $aliases = array_merge(
            array_slice($names, 1),
            $props['alias'] ?? [],
            $props['weakAlias'] ?? [],
            $props['previousName'] ?? [],
        );

        $listingDate = $props['listingDate'][0] ?? null;
        if ($listingDate === null) {
            foreach (($props['sanctions'] ?? []) as $sanction) {
                $listingDate = $sanction['properties']['listingDate'][0] ?? null;
                if ($listingDate !== null) {
                    break;
                }
            }
        }

        return [
            'id' => $item['id'] ?? null,
            'name' => $names,
            'entity_type' => $item['schema'] ?? null,
            'birth_date' => $props['birthDate'][0] ?? null,
            'nationality' => $props['nationality'][0]
                ?? $props['citizenship'][0]
                ?? $props['country'][0]
                ?? null,
            'aliases' => $aliases,
            'listing_date' => $listingDate,
            '_source' => $item,
        ];
    }

    /**
     * Lazily parse source items into entry rows. Accepts a decoded
     * `['results' => [...]]` doc or a lazy stream of items — never
     * materializes the full entry set, so large JSONL lists stay
     * memory-bounded.
     *
     * @param  iterable<array-key, mixed>|array<string, mixed>  $data
     * @return LazyCollection<int, array<string, mixed>>
     */
    public function parseEntries(iterable $data, SanctionList $list): LazyCollection
    {
        $results = is_array($data) ? ($data['results'] ?? []) : $data;

        return LazyCollection::make(function () use ($results, $list) {
            foreach ($results as $item) {
                $parsed = $this->parseOpenSanctionsEntry($item, $list);
                if ($parsed !== null) {
                    yield $parsed;
                }
            }
        });
    }

    public function parseOpenSanctionsEntry(array $item, SanctionList $list): ?array
    {
        $names = $item['name'] ?? null;
        if ($names === null) {
            return null;
        }

        $primaryName = is_array($names) ? ($names[0] ?? '') : $names;
        $normalizedName = $this->normalizeName($primaryName);

        if (empty($normalizedName)) {
            return null;
        }

        $aliases = [];
        if (is_array($names) && count($names) > 1) {
            foreach (array_slice($names, 1) as $alias) {
                $normalizedAlias = $this->normalizeName($alias);
                if (! empty($normalizedAlias) && $normalizedAlias !== $normalizedName) {
                    $aliases[] = $alias;
                }
            }
        }

        $aliasData = $item['aliases'] ?? [];
        if (is_array($aliasData)) {
            foreach ($aliasData as $alias) {
                if (is_string($alias)) {
                    $normalizedAlias = $this->normalizeName($alias);
                    if (! empty($normalizedAlias) && $normalizedAlias !== $normalizedName) {
                        $aliases[] = $alias;
                    }
                }
            }
        }

        $birthDate = $this->parseDate($item['birth_date'] ?? null);
        $nationality = $item['nationality'] ?? null;
        $entityType = $this->mapEntityType($item['entity_type'] ?? null);

        return [
            'list_id' => $list->id,
            'reference_number' => $item['id'] ?? null,
            'entity_name' => $primaryName,
            'normalized_name' => $normalizedName,
            'soundex_code' => soundex($normalizedName),
            'metaphone_code' => metaphone($normalizedName),
            'entity_type' => $entityType,
            'aliases' => ! empty($aliases) ? json_encode($aliases) : null,
            'nationality' => is_array($nationality) ? ($nationality[0] ?? null) : $nationality,
            'date_of_birth' => $birthDate,
            'listing_date' => $this->parseDate($item['listing_date'] ?? null),
            'details' => json_encode($item['_source'] ?? $item),
            'status' => SanctionStatus::Active,
        ];
    }

    /**
     * @param  iterable<array-key, array<string, mixed>>  $entries
     */
    public function syncEntries(iterable $entries, SanctionList $list): array
    {
        DB::transaction(function () use ($entries, $list) {
            $existingByRef = SanctionEntry::where('list_id', $list->id)
                ->whereNotNull('reference_number')
                ->get()
                ->keyBy('reference_number');

            $importedRefs = [];
            $seen = 0;

            foreach ($entries as $entryData) {
                $seen++;
                $ref = $entryData['reference_number'] ?? null;

                try {
                    if ($ref && $existingByRef->has($ref)) {
                        $existing = $existingByRef->get($ref);
                        $existing->update([
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
                        $this->updated++;
                    } else {
                        SanctionEntry::create($entryData);
                        $this->created++;
                    }

                    $importedRefs[(string) $ref] = true;
                } catch (\Exception $e) {
                    Log::error('Failed to sync sanction entry', [
                        'reference_number' => $ref,
                        'error' => $e->getMessage(),
                    ]);
                    $this->errors++;
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
                    $this->deactivated++;
                }
            }
        });

        return [
            'created' => $this->created,
            'updated' => $this->updated,
            'deactivated' => $this->deactivated,
            'errors' => $this->errors,
        ];
    }

    public function parseDate(?string $date): ?string
    {
        if (empty($date)) {
            return null;
        }

        $date = trim($date);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }

        if (preg_match('/^\d{4}$/', $date)) {
            return $date.'-01-01';
        }

        if (preg_match('#^(\d{4})[-/](\d{2})[-/](\d{2})$#', $date, $matches)) {
            return sprintf('%04d-%02d-%02d', $matches[1], $matches[2], $matches[3]);
        }

        try {
            $parsed = date_create($date);
            if ($parsed !== false) {
                return date_format($parsed, 'Y-m-d');
            }
        } catch (\Exception $e) {
            Log::debug('Date parsing failed, trying fallback', [
                'date' => $date,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    public function normalizeName(string $name): string
    {
        $name = trim($name);
        $name = mb_strtolower($name, 'UTF-8');
        $name = preg_replace('/\s+/', ' ', $name);
        $name = preg_replace('/[^\p{L}\p{N}\s\-\'\.]/u', '', $name);
        $name = trim($name);

        return $name;
    }

    public function mapEntityType(?string $type): EntityType
    {
        if (empty($type)) {
            return EntityType::Individual;
        }

        $type = strtolower($type);

        $personTypes = ['person', 'individual', 'natural person', 'human'];
        $vesselTypes = ['vessel', 'ship', 'boat'];
        $aircraftTypes = ['aircraft', 'plane', 'airplane'];

        foreach ($personTypes as $personType) {
            if (str_contains($type, $personType)) {
                return EntityType::Individual;
            }
        }

        foreach ($vesselTypes as $vesselType) {
            if (str_contains($type, $vesselType)) {
                return EntityType::Vessel;
            }
        }

        foreach ($aircraftTypes as $aircraftType) {
            if (str_contains($type, $aircraftType)) {
                return EntityType::Aircraft;
            }
        }

        return EntityType::Organization;
    }

    protected function resetCounters(): void
    {
        $this->created = 0;
        $this->updated = 0;
        $this->deactivated = 0;
        $this->errors = 0;
    }

    /**
     * Flatten a rich result for the download-job callers that consume the
     * legacy importFromXml/Json/Csv return shape.
     */
    protected function enrichResult(array $result): array
    {
        return $result + [
            'imported' => $result['created'],
            'removed' => $result['deactivated'],
            'new_entries_detected' => $result['created'],
            'is_significant_change' => $result['created'] > 0,
        ];
    }

    /**
     * Collect record nodes from a sanctions XML document. Handles the UN
     * Consolidated List (INDIVIDUALS/INDIVIDUAL, ENTITIES/ENTITY) and the OFAC
     * SDN list (sdnList/sdnEntry) by recursing until a record-shaped element
     * is found.
     *
     * @return array<int, SimpleXMLElement>
     */
    protected function collectXmlRecords(SimpleXMLElement $node): array
    {
        $records = [];

        foreach ($node->children() as $child) {
            $name = strtolower($child->getName());

            if (in_array($name, ['individual', 'entity', 'sdnentry', 'entry', 'item'], true)) {
                $records[] = $child;

                continue;
            }

            $records = array_merge($records, $this->collectXmlRecords($child));
        }

        return $records;
    }

    /**
     * Normalise a sanctions XML record (UN or OFAC shape) into the
     * OpenSanctions-style array consumed by parseOpenSanctionsEntry().
     */
    protected function parseXmlEntry(SimpleXMLElement $record): ?array
    {
        $value = fn (string $key) => isset($record->{$key}) ? trim((string) $record->{$key}) : null;
        $attr = fn (string $key) => isset($record[$key]) ? trim((string) $record[$key]) : null;

        $referenceNumber = $attr('dataid')
            ?? $attr('uid')
            ?? $value('REFERENCE_NUMBER')
            ?? $value('reference_number')
            ?? null;

        $name = $value('name')
            ?? $value('NAME')
            ?? $value('ENTITY')
            ?? $value('title')
            ?? $this->combineXmlNames($record);

        if ($referenceNumber === null || $name === null) {
            return null;
        }

        $aliases = $this->collectXmlAliases($record);

        return [
            'id' => $referenceNumber,
            'name' => $name,
            'birth_date' => $value('DATE_OF_BIRTH') ?? $value('birth_date') ?? $value('birthDate'),
            'nationality' => $value('NATIONALITY') ?? $value('nationality') ?? $value('NATIONALITY_VALUE'),
            'entity_type' => $value('UN_LIST_TYPE') ?? $value('sdnType') ?? $value('entity_type'),
            'aliases' => $aliases,
        ];
    }

    protected function combineXmlNames(SimpleXMLElement $record): ?string
    {
        $first = isset($record->FIRST_NAME) ? trim((string) $record->FIRST_NAME) : null;
        if ($first === null && isset($record->firstName)) {
            $first = trim((string) $record->firstName);
        }

        $last = isset($record->LAST_NAME) ? trim((string) $record->LAST_NAME) : null;
        if ($last === null && isset($record->lastName)) {
            $last = trim((string) $record->lastName);
        }

        $middle = isset($record->SECOND_NAME) ? trim((string) $record->SECOND_NAME) : null;
        if ($middle === null && isset($record->middleName)) {
            $middle = trim((string) $record->middleName);
        }

        $third = isset($record->THIRD_NAME) ? trim((string) $record->THIRD_NAME) : null;

        if ($first === null && $last === null && $middle === null && $third === null) {
            return null;
        }

        $parts = array_values(array_filter([$last, $first, $middle, $third], fn ($p) => $p !== null && $p !== ''));

        return $parts !== [] ? implode(' ', $parts) : null;
    }

    /**
     * @return array<int, string>
     */
    protected function collectXmlAliases(SimpleXMLElement $record): array
    {
        $aliases = [];

        // UN: <AKA><ALIAS_NAME>...</ALIAS_NAME></AKA>
        foreach ($record->AKA ?? [] as $aka) {
            $aliasName = isset($aka->ALIAS_NAME) ? trim((string) $aka->ALIAS_NAME) : null;
            if (! empty($aliasName)) {
                $aliases[] = $aliasName;
            }
        }

        // OFAC: <akaList><aka><firstName>..</firstName><lastName>..</lastName></aka></akaList>
        foreach ($record->akaList->aka ?? [] as $aka) {
            $first = isset($aka->firstName) ? trim((string) $aka->firstName) : '';
            $last = isset($aka->lastName) ? trim((string) $aka->lastName) : '';
            $aliasName = trim($first.' '.$last);
            if ($aliasName !== '') {
                $aliases[] = $aliasName;
            }
        }

        return $aliases;
    }

    /**
     * Map one CSV row (using the header line) into the OpenSanctions-style
     * entry shape. Used for the EU consolidated list export.
     */
    protected function mapCsvRow(array $row, array $header): ?array
    {
        $get = function (array $keys) use ($row, $header) {
            foreach ($keys as $key) {
                $index = array_search($key, $header, true);
                if ($index !== false && isset($row[$index])) {
                    $value = trim((string) $row[$index]);
                    if ($value !== '') {
                        return $value;
                    }
                }
            }

            return null;
        };

        $name = $get(['name', 'name latin', 'name (original script)', 'title']);
        $reference = $get(['unique id', 'reference number', 'id']);

        if ($name === null || $reference === null) {
            return null;
        }

        $aliases = [];
        $aliasValue = $get(['alias', 'alias latin']);
        if ($aliasValue !== null) {
            $aliases = array_values(array_filter(
                array_map('trim', explode(';', $aliasValue)),
                fn ($a) => $a !== ''
            ));
        }

        return [
            'id' => $reference,
            'name' => $name,
            'birth_date' => $get(['birth date']),
            'nationality' => $get(['nationality']),
            'entity_type' => $get(['type of entity']),
            'aliases' => $aliases !== [] ? $aliases : null,
        ];
    }
}
