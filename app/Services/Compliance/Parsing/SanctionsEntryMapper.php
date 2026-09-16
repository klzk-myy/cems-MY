<?php

namespace App\Services\Compliance\Parsing;

use App\Enums\EntityType;
use App\Enums\SanctionStatus;
use App\Models\SanctionList;
use App\Support\NameNormalizer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\LazyCollection;

/**
 * Maps raw OpenSanctions-style items (from any feed parser or delta op)
 * into normalized sanction-entry rows: name normalization, phonetic codes,
 * date parsing, and entity-type mapping.
 */
class SanctionsEntryMapper
{
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
                $parsed = $this->parseEntry($item, $list);
                if ($parsed !== null) {
                    yield $parsed;
                }
            }
        });
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null
     */
    public function parseEntry(array $item, SanctionList $list): ?array
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
        return NameNormalizer::normalize($name);
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
}
