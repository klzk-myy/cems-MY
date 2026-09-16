<?php

namespace App\Services\Compliance\Parsing;

use App\Exceptions\Domain\SanctionsImportException;

/**
 * Parses CSV sanctions exports (the EU consolidated list) into
 * OpenSanctions-style item arrays.
 */
class CsvSanctionsParser implements SanctionsFeedParser
{
    /**
     * @return iterable<array-key, array<string, mixed>>
     */
    public function parse(string $filepath): iterable
    {
        return $this->parseWithHeader($filepath, true);
    }

    /**
     * @return iterable<array-key, array<string, mixed>>
     */
    public function parseWithHeader(string $filepath, bool $hasHeader): iterable
    {
        $handle = fopen($filepath, 'r');
        if (! $handle) {
            throw new SanctionsImportException("Failed to read import file: {$filepath}", $filepath);
        }

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

                $mapped = $this->mapRow($row, $header);
                if ($mapped !== null) {
                    yield $mapped;
                }
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Map one CSV row (using the header line) into the OpenSanctions-style
     * entry shape. Used for the EU consolidated list export.
     *
     * @param  array<int, string>  $row
     * @param  array<int, int|string>  $header  header names, or positional indexes for headerless files
     * @return array<string, mixed>|null
     */
    protected function mapRow(array $row, array $header): ?array
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
