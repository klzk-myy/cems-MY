<?php

namespace App\Services;

use App\Models\AdverseMediaEntry;
use App\Models\AdverseMediaImportLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * File-driven import of adverse media entries (CSV or JSON).
 *
 * Input is consumed as a stream: CSV is parsed row-by-row with fgetcsv and
 * committed in fixed-size chunks (idempotent upsert by record_hash makes a
 * partial-then-retry import safe). JSON payloads are materialized once with
 * a size guard (see JSON_MEMORY_GUARD_BYTES) because streaming arbitrary
 * JSON without a schema-aware parser is not worth the complexity here.
 *
 * Rows are upserted by record_hash = sha256(name|source|url) so re-importing
 * the same file is idempotent: existing rows are updated, new rows created,
 * unparseable rows skipped (counted in the import log).
 */
class AdverseMediaImportService
{
    protected const COMMIT_CHUNK_SIZE = 500;

    protected const JSON_MEMORY_GUARD_BYTES = 52428800; // 50 MB

    /**
     * Expected input columns: name (required), article_title/title (required,
     * either key), source (required), url, snippet, published_at, severity.
     *
     * @return array{added: int, updated: int, skipped: int, status: string}
     */
    public function importFromContents(string $contents, ?string $importedFile = null): array
    {
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            throw new \RuntimeException('Unable to open temporary stream for import');
        }

        fwrite($stream, $contents);
        rewind($stream);

        try {
            return $this->importFromStream($stream, $importedFile);
        } finally {
            fclose($stream);
        }
    }

    /**
     * @return array{added: int, updated: int, skipped: int, status: string}
     */
    public function importFromFile(string $path): array
    {
        $stream = fopen($path, 'r');

        if ($stream === false) {
            throw new \RuntimeException("Unable to open file for import: {$path}");
        }

        try {
            return $this->importFromStream($stream, basename($path));
        } finally {
            fclose($stream);
        }
    }

    /**
     * @param  resource  $stream
     * @return array{added: int, updated: int, skipped: int, status: string}
     */
    protected function importFromStream($stream, ?string $importedFile): array
    {
        $firstByte = fread($stream, 1);

        if ($firstByte !== false) {
            rewind($stream);
        }

        $isJson = $firstByte === '[' || $firstByte === '{';

        $added = 0;
        $updated = 0;
        $skipped = 0;

        if ($isJson) {
            [$added, $updated, $skipped] = $this->importJsonStream($stream);
        } else {
            [$added, $updated, $skipped] = $this->importCsvStream($stream);
        }

        $status = match (true) {
            $skipped === 0 => 'success',
            ($added + $updated) > 0 => 'partial',
            default => 'failed',
        };

        AdverseMediaImportLog::create([
            'imported_file' => $importedFile,
            'imported_at' => now(),
            'records_added' => $added,
            'records_updated' => $updated,
            'records_deactivated' => 0,
            'records_skipped' => $skipped,
            'status' => $status,
            'triggered_by' => 'manual',
            'user_id' => auth()->id(),
        ]);

        return [
            'added' => $added,
            'updated' => $updated,
            'skipped' => $skipped,
            'status' => $status,
        ];
    }

    /**
     * Stream CSV rows off the file handle, committing one transaction per
     * chunk so large feeds never hold a long write lock or balloon memory.
     *
     * @param  resource  $stream
     * @return array{0: int, 1: int, 2: int} [added, updated, skipped]
     */
    protected function importCsvStream($stream): array
    {
        $header = fgetcsv($stream);

        if ($header === false) {
            return [0, 0, 0];
        }

        $header = array_map(fn (?string $column) => mb_strtolower(trim((string) $column)), $header);

        /** @var list<array<string, mixed>> $chunk */
        $chunk = [];
        [$added, $updated, $skipped] = [0, 0, 0];

        while (($raw = fgetcsv($stream)) !== false) {
            if ($raw === [null]) {
                continue;
            }

            if (count(array_filter($raw, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }

            $row = [];

            foreach ($header as $index => $column) {
                $row[$column] = $raw[$index] ?? null;
            }

            $chunk[] = $row;

            if (count($chunk) >= static::COMMIT_CHUNK_SIZE) {
                [$chunkAdded, $chunkUpdated, $chunkSkipped] = $this->flushChunk($chunk);
                $added += $chunkAdded;
                $updated += $chunkUpdated;
                $skipped += $chunkSkipped;
                $chunk = [];
            }
        }

        if ($chunk !== []) {
            [$chunkAdded, $chunkUpdated, $chunkSkipped] = $this->flushChunk($chunk);
            $added += $chunkAdded;
            $updated += $chunkUpdated;
            $skipped += $chunkSkipped;
        }

        return [$added, $updated, $skipped];
    }

    /**
     * Materialize a JSON payload (array of objects or single object) and run
     * it through the same chunked commit path. Streaming arbitrary JSON is
     * not supported; instead guard memory and warn when the payload is huge.
     *
     * @param  resource  $stream
     * @return array{0: int, 1: int, 2: int} [added, updated, skipped]
     */
    protected function importJsonStream($stream): array
    {
        $contents = stream_get_contents($stream);

        if ($contents === false || trim($contents) === '') {
            return [0, 0, 0];
        }

        if (strlen($contents) > self::JSON_MEMORY_GUARD_BYTES) {
            Log::warning('adverse-media import: JSON payload exceeds memory guard', [
                'bytes' => strlen($contents),
                'guard_bytes' => self::JSON_MEMORY_GUARD_BYTES,
            ]);
        }

        $decoded = json_decode($contents, true);

        if (! is_array($decoded)) {
            return [0, 0, 0];
        }

        if (isset($decoded[0]) && ! is_array($decoded[0])) {
            return [0, 0, 0];
        }

        $rows = array_is_list($decoded) ? $decoded : [$decoded];

        [$added, $updated, $skipped] = [0, 0, 0];

        /** @var list<array<string, mixed>> $chunk */
        foreach (array_chunk($rows, static::COMMIT_CHUNK_SIZE) as $chunk) {
            [$chunkAdded, $chunkUpdated, $chunkSkipped] = $this->flushChunk($chunk);
            $added += $chunkAdded;
            $updated += $chunkUpdated;
            $skipped += $chunkSkipped;
        }

        return [$added, $updated, $skipped];
    }

    /**
     * Upsert one chunk inside its own transaction. Idempotency by
     * record_hash means a failed later chunk leaves earlier chunks valid and
     * the whole import safely re-runnable.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array{0: int, 1: int, 2: int} [added, updated, skipped]
     */
    protected function flushChunk(array $rows): array
    {
        $added = 0;
        $updated = 0;
        $skipped = 0;

        DB::transaction(function () use ($rows, &$added, &$updated, &$skipped) {
            foreach ($rows as $row) {
                $record = $this->normalizeRow($row);

                if ($record === null) {
                    $skipped++;

                    continue;
                }

                $existing = AdverseMediaEntry::where('record_hash', $record['record_hash'])->first();

                if ($existing) {
                    $existing->update($record);
                    $updated++;
                } else {
                    AdverseMediaEntry::create($record);
                    $added++;
                }
            }
        });

        return [$added, $updated, $skipped];
    }

    /**
     * Validate and normalize a raw row; null when the row must be skipped.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    protected function normalizeRow(array $row): ?array
    {
        $name = isset($row['name']) ? trim((string) $row['name']) : '';
        $articleTitle = trim((string) ($row['article_title'] ?? $row['title'] ?? ''));
        $source = isset($row['source']) ? trim((string) $row['source']) : '';

        if ($name === '' || $articleTitle === '' || $source === '') {
            return null;
        }

        $severity = mb_strtolower(trim((string) ($row['severity'] ?? AdverseMediaEntry::SEVERITY_MEDIUM)));

        if (! in_array($severity, AdverseMediaEntry::SEVERITIES, true)) {
            return null;
        }

        $url = isset($row['url']) && trim((string) $row['url']) !== '' ? trim((string) $row['url']) : null;

        // Only http(s) URLs are stored: the value is later rendered as a
        // clickable link for compliance staff, and schemes like javascript:
        // must never survive an import.
        if ($url !== null && ! preg_match('#^https?://#i', $url)) {
            $url = null;
        }
        $snippet = isset($row['snippet']) && trim((string) $row['snippet']) !== ''
            ? trim((string) $row['snippet'])
            : null;
        $publishedAt = $this->parsePublishedAt($row['published_at'] ?? null);
        $alias = isset($row['alias']) && trim((string) $row['alias']) !== '' ? trim((string) $row['alias']) : null;

        return [
            'name' => $name,
            'normalized_name' => mb_strtolower((string) preg_replace('/\s+/', ' ', $name)),
            'alias' => $alias,
            'article_title' => $articleTitle,
            'source' => $source,
            'url' => $url,
            'snippet' => $snippet,
            'published_at' => $publishedAt,
            'severity' => $severity,
            'is_active' => true,
            'record_hash' => AdverseMediaEntry::buildRecordHash($name, $source, $url),
        ];
    }

    protected function parsePublishedAt(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        try {
            return Carbon::parse(trim((string) $value))->toDateString();
        } catch (\Exception) {
            return null;
        }
    }
}
