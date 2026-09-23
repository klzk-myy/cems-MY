<?php

namespace App\Services\Compliance\Parsing;

/**
 * Streams flat entry items from a downloaded OpenSanctions source file.
 * Detects single-document JSON vs JSONL (one entity per line — the
 * targets.nested.json exports); nested FollowTheMoney entities are
 * flattened so the entry mapper can consume both shapes.
 */
class OpenSanctionsJsonParser implements SanctionsFeedParser
{
    /**
     * Malformed JSONL lines skipped during the last parse. Exposed for
     * import monitoring so a partially corrupt feed is visible without
     * aborting the whole import.
     */
    private int $malformedLines = 0;

    /**
     * @return iterable<array-key, array<string, mixed>>
     */
    public function parse(string $filepath): iterable
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
                    // One malformed line must not discard the rest of the
                    // feed — skip it and keep parsing.
                    $this->malformedLines++;

                    continue;
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
     * into the flat entry shape the mapper consumes.
     * Flat records are returned unchanged.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    public function flattenNestedEntity(array $item): array
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
}
