<?php

namespace App\Services;

use App\Models\SanctionEntry;
use App\Models\SanctionImportLog;
use App\Models\SanctionList;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
        $this->resetCounters();

        $list->update(['last_attempted_at' => now(), 'update_status' => 'pending']);

        try {
            $data = $this->fetchSource($list->source_url);
            $entries = $this->parseEntries($data, $list);
            $result = $this->syncEntries($entries, $list);

            $list->update([
                'last_updated_at' => now(),
                'update_status' => 'success',
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
                'status' => 'success',
            ]);

            return $result;

        } catch (\Exception $e) {
            $list->update([
                'update_status' => 'failed',
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
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function fetchSource(string $url): array
    {
        $maxRetries = 3;
        $retryDelay = 5;
        $timeout = 60;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $response = Http::timeout($timeout)
                    ->retry(2, $retryDelay)
                    ->get($url);

                if ($response->successful()) {
                    $data = $response->json();

                    if (! isset($data['results']) && ! is_array($data)) {
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
                    throw new \RuntimeException(
                        "Failed to fetch sanctions data after {$maxRetries} attempts: {$e->getMessage()}"
                    );
                }
            }
        }

        throw new \RuntimeException("Failed to fetch sanctions data after {$maxRetries} attempts");
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
            'status' => 'active',
        ];
    }

    public function syncEntries(Collection $entries, SanctionList $list): array
    {
        $existingByRef = SanctionEntry::where('list_id', $list->id)
            ->whereNotNull('reference_number')
            ->get()
            ->keyBy('reference_number');

        $importedRefs = [];

        foreach ($entries as $entryData) {
            $ref = $entryData['reference_number'] ?? null;
            $importedRefs[$ref] = true;

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
                        'status' => 'active',
                    ]);
                    $this->updated++;
                } else {
                    SanctionEntry::create($entryData);
                    $this->created++;
                }
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
            if ($existing->status === 'active') {
                $existing->update(['status' => 'inactive']);
                $this->deactivated++;
            }
        }

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

    public function mapEntityType(?string $type): string
    {
        if (empty($type)) {
            return 'Individual';
        }

        $type = strtolower($type);

        $personTypes = ['person', 'individual', 'natural person', 'human'];
        $entityTypes = ['organization', 'entity', 'company', 'corporation', 'vessel', 'aircraft'];

        foreach ($personTypes as $personType) {
            if (str_contains($type, $personType)) {
                return 'Individual';
            }
        }

        foreach ($entityTypes as $entityType) {
            if (str_contains($type, $entityType)) {
                return 'Entity';
            }
        }

        return 'Individual';
    }

    protected function resetCounters(): void
    {
        $this->created = 0;
        $this->updated = 0;
        $this->deactivated = 0;
        $this->errors = 0;
    }
}
