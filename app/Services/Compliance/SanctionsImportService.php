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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use SimpleXMLElement;

class SanctionsImportService
{
    protected int $created = 0;

    protected int $updated = 0;

    protected int $deactivated = 0;

    protected int $errors = 0;

    public function __construct(
        protected MathService $mathService,
    ) {}

    public function import(SanctionList $list, bool $manual = false): array
    {
        $data = $this->fetchSource($list->source_url);

        return $this->importWithData($list, $data, $manual);
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

    public function importWithData(SanctionList $list, array $data, bool $manual = false): array
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
        $content = file_get_contents($filepath);
        if ($content === false) {
            throw new SanctionsImportException("Failed to read import file: {$filepath}", $filepath);
        }

        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new SanctionsImportException('Import file is not valid JSON: '.json_last_error_msg());
        }

        return $this->importWithData(SanctionList::findOrFail($listId), $data, false);
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

    public function fetchSource(string $url): array
    {
        $maxRetries = 3;
        $retryDelay = 5;
        $timeout = 60;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $response = Http::timeout($timeout)->get($url);

                if ($response->successful()) {
                    $data = $response->json();

                    if (! is_array($data)) {
                        Log::warning('OpenSanctions import: unexpected data structure', [
                            'url' => $url,
                            'type' => get_debug_type($data),
                        ]);

                        return [];
                    }

                    if (! isset($data['results'])) {
                        Log::warning('OpenSanctions import: unexpected data structure', [
                            'url' => $url,
                            'keys' => array_keys($data),
                        ]);
                    }

                    return $data;
                }

                Log::warning("OpenSanctions fetch attempt {$attempt} failed", [
                    'url' => $url,
                    'status' => $response->status(),
                ]);

            } catch (\Exception $e) {
                Log::warning("OpenSanctions fetch attempt {$attempt} exception", [
                    'url' => $url,
                    'error' => $e->getMessage(),
                ]);

                if ($attempt === $maxRetries) {
                    throw new SanctionsImportException(
                        "Failed to fetch sanctions data after {$maxRetries} attempts: {$e->getMessage()}"
                    );
                }
            }

            if ($attempt < $maxRetries) {
                sleep($retryDelay);
            }
        }

        throw new SanctionsImportException("Failed to fetch sanctions data after {$maxRetries} attempts");
    }

    public function parseEntries(array $data, SanctionList $list): Collection
    {
        $results = $data['results'] ?? [];
        $entries = collect();

        foreach ($results as $item) {
            $parsed = $this->parseOpenSanctionsEntry($item, $list);
            if ($parsed !== null) {
                $entries->push($parsed);
            }
        }

        return $entries;
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
            'entity_type' => $entityType,
            'aliases' => ! empty($aliases) ? json_encode($aliases) : null,
            'nationality' => is_array($nationality) ? ($nationality[0] ?? null) : $nationality,
            'date_of_birth' => $birthDate,
            'details' => json_encode($item),
            'status' => SanctionStatus::Active,
        ];
    }

    public function syncEntries(Collection $entries, SanctionList $list): array
    {
        // Safety guard: if the source produced zero parseable entries but the
        // list currently holds active entries, refuse to run the deactivation
        // sweep. Otherwise a temporarily broken upstream (or a format the
        // parser does not understand) would silently deactivate the entire
        // sanctions list - a catastrophic data-loss scenario for screening.
        if ($entries->isEmpty()
            && $list->entries()->where('status', SanctionStatus::Active->value)->exists()) {
            throw new SanctionsImportException(
                'Import produced no valid entries while the list has active entries; '.
                'refusing to deactivate the existing list. Existing entries preserved.'
            );
        }

        DB::transaction(function () use ($entries, $list) {
            $existingByRef = SanctionEntry::where('list_id', $list->id)
                ->whereNotNull('reference_number')
                ->get()
                ->keyBy('reference_number');

            $importedRefs = [];

            foreach ($entries as $entryData) {
                $ref = $entryData['reference_number'] ?? null;

                try {
                    if ($ref && $existingByRef->has($ref)) {
                        $existing = $existingByRef->get($ref);
                        $existing->update([
                            'entity_name' => $entryData['entity_name'],
                            'normalized_name' => $entryData['normalized_name'],
                            'entity_type' => $entryData['entity_type'],
                            'aliases' => $entryData['aliases'],
                            'nationality' => $entryData['nationality'],
                            'date_of_birth' => $entryData['date_of_birth'],
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
