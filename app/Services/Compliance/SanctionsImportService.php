<?php

namespace App\Services\Compliance;

use App\Enums\EntityType;
use App\Enums\SanctionStatus;
use App\Enums\UpdateStatus;
use App\Events\SanctionsListUpdated;
use App\Exceptions\Domain\SanctionsImportException;
use App\Models\SanctionImportLog;
use App\Models\SanctionList;
use App\Services\Compliance\Parsing\CsvSanctionsParser;
use App\Services\Compliance\Parsing\OpenSanctionsJsonParser;
use App\Services\Compliance\Parsing\SanctionsEntryMapper;
use App\Services\Compliance\Parsing\XmlSanctionsParser;
use App\Support\ActorContext;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\LazyCollection;

class SanctionsImportService
{
    /**
     * Temp file downloaded by fetchSource() for the in-flight import. Deleted
     * once the lazy stream has been consumed — the archive copy persists.
     */
    protected ?string $pendingTempFile = null;

    /**
     * Dataset version reported by the source's index.json for the in-flight
     * import. Stored on the list after a successful sync (full or delta).
     */
    protected ?string $pendingDatasetVersion = null;

    public function __construct(
        protected SanctionsDownloadService $downloadService,
        protected OpenSanctionsJsonParser $jsonParser,
        protected XmlSanctionsParser $xmlParser,
        protected CsvSanctionsParser $csvParser,
        protected SanctionsEntryMapper $mapper,
        protected SanctionsEntrySynchronizer $synchronizer,
    ) {}

    public function import(SanctionList $list, bool $manual = false): array
    {
        $this->pendingTempFile = null;
        $this->pendingDatasetVersion = null;

        $index = $this->fetchDatasetIndex($list);
        $this->pendingDatasetVersion = is_array($index) ? ($index['version'] ?? null) : null;

        try {
            $deltaResult = $this->importFromDelta($list, $index, $manual);
            if ($deltaResult !== null) {
                return $deltaResult;
            }

            $result = $this->importWithData($list, $this->fetchSource($list), $manual);
            $this->commitDatasetVersion($list);

            return $result;
        } finally {
            if ($this->pendingTempFile !== null && file_exists($this->pendingTempFile)) {
                unlink($this->pendingTempFile);
            }
            $this->pendingTempFile = null;
            $this->pendingDatasetVersion = null;
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
            $authenticatedId = ActorContext::capture()->userId;
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
        $previousVersion = $list->last_dataset_version;

        $list->update(['last_attempted_at' => now(), 'update_status' => UpdateStatus::Pending]);

        try {
            $entries = $this->mapper->parseEntries($data, $list);
            $result = $this->synchronizer->syncEntries($entries, $list);

            $list->update([
                'last_updated_at' => now(),
                'update_status' => UpdateStatus::Success,
                'last_error_message' => null,
                'entry_count' => $list->entries()->where('status', SanctionStatus::Active->value)->count(),
            ]);

            SanctionImportLog::create([
                'list_id' => $list->id,
                'imported_at' => now(),
                'source_url' => $list->source_url,
                'records_added' => $result['created'],
                'records_updated' => $result['updated'],
                'records_deactivated' => $result['deactivated'],
                'is_manual' => $manual,
                ...$this->attributionFor($manual),
                'status' => UpdateStatus::Success->value,
            ]);

            $this->dispatchListUpdated($list, $previousVersion, $result);

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
                'records_added' => 0,
                'records_updated' => 0,
                'records_deactivated' => 0,
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
            $this->jsonParser->parse($filepath),
            false
        );
    }

    /**
     * Import a downloaded XML file (supports the UN Consolidated List and OFAC
     * SDN structures, normalised into the OpenSanctions entry shape).
     */
    public function importFromXml(string $filepath, int $listId, string $listType = ''): array
    {
        // Materialize eagerly so unreadable/invalid files throw before the
        // import bookkeeping begins, exactly as the pre-split implementation.
        $records = iterator_to_array($this->xmlParser->parse($filepath), false);

        return $this->importWithData(SanctionList::findOrFail($listId), ['results' => $records], false);
    }

    /**
     * Import a downloaded CSV file (supports the EU consolidated list export).
     */
    public function importFromCsv(string $filepath, int $listId, bool $hasHeader = true): array
    {
        $records = iterator_to_array($this->csvParser->parseWithHeader($filepath, $hasHeader), false);

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

        return $this->jsonParser->parse($result['filepath']);
    }

    /**
     * Fetch the dataset's index.json (same directory as the source export).
     * Returns null when the source URL has no file basename or the artifact
     * is unreachable — callers fall back to a full sync.
     *
     * @return array<string, mixed>|null
     */
    protected function fetchDatasetIndex(SanctionList $list): ?array
    {
        if (! config('sanctions.delta.enabled', true)) {
            return null;
        }

        $url = preg_replace('#/[^/]+$#', '/index.json', (string) $list->source_url);

        if ($url === null || $url === $list->source_url) {
            return null;
        }

        return $this->fetchJsonArtifact($url);
    }

    /**
     * Download a small JSON artifact through the shared validated download
     * path (allowlist + redirect checks) and decode it. Metadata files are
     * deleted after reading — only list exports are archived.
     *
     * @return array<string, mixed>|null
     */
    protected function fetchJsonArtifact(string $url): ?array
    {
        try {
            $result = $this->downloadService->download(
                $url,
                'meta_'.uniqid().'.json',
                'JSON',
                1
            );
        } catch (\Throwable) {
            return null;
        }

        if (! ($result['success'] ?? false) || empty($result['filepath'])) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($result['filepath']), true);
        unlink($result['filepath']);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Apply OpenSanctions per-version delta files between the list's stored
     * dataset version and the current one. Returns null to signal a full-sync
     * fallback (no manifest coverage, too many versions, or apply failure).
     *
     * @param  array<string, mixed>|null  $index
     * @return array<string, mixed>|null
     */
    protected function importFromDelta(SanctionList $list, ?array $index, bool $manual): ?array
    {
        $version = $index['version'] ?? null;
        $deltaUrl = $index['delta_url'] ?? null;

        if (! is_string($version) || ! is_string($deltaUrl)) {
            return null;
        }

        $current = $list->last_dataset_version;

        if ($current === $version) {
            return $this->markChecked($list, $manual);
        }

        $manifest = $this->fetchJsonArtifact($deltaUrl);
        $versions = is_array($manifest) ? ($manifest['versions'] ?? null) : null;

        if (! is_array($versions) || $current === null || ! isset($versions[$current])) {
            return null;
        }

        $pending = array_filter(
            array_keys($versions),
            fn (string $v) => strcmp($v, $current) > 0 && strcmp($v, $version) <= 0
        );
        sort($pending);

        if ($pending === [] || count($pending) > (int) config('sanctions.delta.max_versions', 50)) {
            return null;
        }

        $totals = ['created' => 0, 'updated' => 0, 'deactivated' => 0, 'errors' => 0];
        $list->update(['last_attempted_at' => now(), 'update_status' => UpdateStatus::Pending]);

        try {
            foreach ($pending as $pendingVersion) {
                $counts = $this->applyDeltaVersion($list, $versions[$pendingVersion]);
                foreach ($counts as $key => $count) {
                    $totals[$key] += $count;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Sanctions delta apply failed; falling back to full sync', [
                'list_id' => $list->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        $list->update([
            'last_dataset_version' => $version,
            'last_updated_at' => now(),
            'update_status' => UpdateStatus::Success,
            'last_error_message' => null,
            'entry_count' => $list->entries()->where('status', SanctionStatus::Active->value)->count(),
        ]);

        SanctionImportLog::create([
            'list_id' => $list->id,
            'imported_at' => now(),
            'source_url' => $list->source_url,
            'records_added' => $totals['created'],
            'records_updated' => $totals['updated'],
            'records_deactivated' => $totals['deactivated'],
            'is_manual' => $manual,
            ...$this->attributionFor($manual),
            'status' => UpdateStatus::Success->value,
        ]);

        $this->dispatchListUpdated($list, $current, $totals);

        return $this->enrichResult($totals);
    }

    /**
     * Notify listeners that the list contents changed so affected customers
     * can be re-screened. Fan-out failures must not fail an import whose
     * results were already committed.
     *
     * @param  array{created: int, updated: int, deactivated: int, errors: int}  $counts
     */
    protected function dispatchListUpdated(SanctionList $list, ?string $previousVersion, array $counts): void
    {
        if ($counts['created'] + $counts['updated'] + $counts['deactivated'] === 0) {
            return;
        }

        try {
            SanctionsListUpdated::dispatch(
                $list->slug,
                $previousVersion,
                $this->pendingDatasetVersion ?? $list->last_dataset_version,
                $counts['created'],
                $counts['deactivated']
            );
        } catch (\Throwable $e) {
            Log::warning('SanctionsListUpdated dispatch failed', [
                'list_id' => $list->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Record an unchanged check: the source version equals the stored version,
     * so no download or sync was needed.
     *
     * @return array<string, mixed>
     */
    protected function markChecked(SanctionList $list, bool $manual): array
    {
        $list->update([
            'last_attempted_at' => now(),
            'update_status' => UpdateStatus::Success,
            'last_error_message' => null,
        ]);

        SanctionImportLog::create([
            'list_id' => $list->id,
            'imported_at' => now(),
            'source_url' => $list->source_url,
            'records_added' => 0,
            'records_updated' => 0,
            'records_deactivated' => 0,
            'is_manual' => $manual,
            ...$this->attributionFor($manual),
            'status' => UpdateStatus::Success->value,
        ]);

        return $this->enrichResult([
            'created' => 0,
            'updated' => 0,
            'deactivated' => 0,
            'errors' => 0,
        ]);
    }

    /**
     * Download one version's entities.delta.json and apply its ops.
     *
     * @return array{created: int, updated: int, deactivated: int, errors: int}
     */
    protected function applyDeltaVersion(SanctionList $list, string $url): array
    {
        $result = $this->downloadService->download(
            $url,
            'delta_'.uniqid().'.json',
            'JSON',
            1
        );

        if (! ($result['success'] ?? false) || empty($result['filepath'])) {
            throw new SanctionsImportException("Failed to fetch delta file: {$url}");
        }

        try {
            return $this->synchronizer->applyDeltaOps($this->streamDeltaOps($result['filepath']), $list);
        } finally {
            if (file_exists($result['filepath'])) {
                unlink($result['filepath']);
            }
        }
    }

    /**
     * Stream delta ops ({"op":"ADD"|"DEL","entity":{...}}) from a JSONL file.
     *
     * @return iterable<array-key, array<string, mixed>>
     */
    protected function streamDeltaOps(string $filepath): iterable
    {
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

                $decoded = json_decode($line, true);
                if (is_array($decoded) && isset($decoded['op'])) {
                    yield $decoded;
                }
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Store the dataset version fetched during import() on a successful sync.
     */
    protected function commitDatasetVersion(SanctionList $list): void
    {
        if ($this->pendingDatasetVersion !== null) {
            $list->update(['last_dataset_version' => $this->pendingDatasetVersion]);
        }
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

    // ---- Delegates preserved for existing callers/tests --------------------

    /**
     * Stream flat entry items from a downloaded source file.
     *
     * @return iterable<array-key, array<string, mixed>>
     */
    public function streamSourceFile(string $filepath): iterable
    {
        return $this->jsonParser->parse($filepath);
    }

    /**
     * @param  iterable<array-key, mixed>|array<string, mixed>  $data
     * @return LazyCollection<int, array<string, mixed>>
     */
    public function parseEntries(iterable $data, SanctionList $list): LazyCollection
    {
        return $this->mapper->parseEntries($data, $list);
    }

    public function parseOpenSanctionsEntry(array $item, SanctionList $list): ?array
    {
        return $this->mapper->parseEntry($item, $list);
    }

    /**
     * @param  iterable<array-key, array<string, mixed>>  $entries
     */
    public function syncEntries(iterable $entries, SanctionList $list): array
    {
        return $this->synchronizer->syncEntries($entries, $list);
    }

    public function parseDate(?string $date): ?string
    {
        return $this->mapper->parseDate($date);
    }

    public function normalizeName(string $name): string
    {
        return $this->mapper->normalizeName($name);
    }

    public function mapEntityType(?string $type): EntityType
    {
        return $this->mapper->mapEntityType($type);
    }
}
