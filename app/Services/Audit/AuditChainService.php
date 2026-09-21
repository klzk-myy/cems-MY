<?php

namespace App\Services\Audit;

use App\Enums\SystemLogSeverity;
use App\Exceptions\Domain\AuditIntegrityException;
use App\Models\SystemLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Tamper-evidence machinery for the audit chain: entry hashing, sealing,
 * quarantine, and chain verification. AuditService writes entries; this
 * service owns everything about proving they were not tampered with.
 */
class AuditChainService
{
    /**
     * Hash algorithm versions are encoded inside entry_hash itself (no schema
     * change): v1 rows store a bare 64-char SHA-256 of the legacy metadata-only
     * payload; v2 rows store 'v2:<sha256>' where the payload additionally covers
     * old_values, new_values, severity and ip_address as canonical JSON. New
     * seals use v2; existing sealed rows keep verifying under the v1 formula.
     */
    private const HASH_V2_PREFIX = 'v2:';

    /**
     * previous_hash marker used when an entry seals across a quarantined
     * gap: 'GAP:<quarantinedLogId>'. The marker makes the discontinuity
     * explicit in the chain itself — verifyChainIntegrity reports it as a
     * quarantine boundary instead of a hash mismatch.
     */
    public const GAP_PREFIX = 'GAP:';

    /**
     * seal_status value for a permanently unsealable row. Quarantined rows
     * never receive entry_hash and are excluded from gap detection so later
     * entries can seal past them.
     */
    public const SEAL_STATUS_QUARANTINED = 'quarantined';

    /**
     * Compute SHA-256 hash for a log entry (tamper-evident chain).
     *
     * Versioned: when only the six legacy arguments are provided the bare v1
     * hash is returned (metadata fields only — used by SealAuditHashJob and
     * for verifying historical rows). When row-payload context (old_values,
     * new_values, severity, ip_address) is supplied, a 'v2:'-prefixed hash
     * covering that payload is returned so sealed rows cannot be silently
     * edited. verifyChainIntegrity() branches on the stored prefix.
     *
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function computeEntryHash(
        string $timestamp,
        ?int $userId,
        string $action,
        ?string $entityType,
        ?int $entityId,
        ?string $previousHash,
        ?array $oldValues = null,
        ?array $newValues = null,
        SystemLogSeverity|string|null $severity = null,
        ?string $ipAddress = null
    ): string {
        // Enum-backed reads (SystemLog.severity cast) must reduce to the
        // stored string so v2 hash bytes match pre-cast sealed rows.
        $severity = $severity instanceof SystemLogSeverity ? $severity->value : $severity;
        // Legacy v1 payload: metadata fields only.
        $data = implode('|', [
            $timestamp,
            (string) $userId,
            $action,
            $entityType ?? '',
            $entityId !== null ? (string) $entityId : '',
            $previousHash ?? '',
        ]);

        if ($oldValues === null && $newValues === null && $severity === null && $ipAddress === null) {
            return hash('sha256', $data);
        }

        // v2 payload additionally covers the row data excluded from v1.
        $payload = implode('|', [
            $data,
            $this->canonicalJson($oldValues ?? []),
            $this->canonicalJson($newValues ?? []),
            $severity ?? '',
            $ipAddress ?? '',
        ]);

        return self::HASH_V2_PREFIX.hash('sha256', $payload);
    }

    /**
     * Deterministic JSON encoding for hashed payloads: keys sorted at every
     * nesting depth (not just top level) and unicode/slashes unescaped so
     * byte representation is stable across seal and verification time,
     * regardless of nested-array insertion order.
     *
     * @param  array<string, mixed>  $values
     */
    private function canonicalJson(array $values): string
    {
        $canonicalize = static function (array $data) use (&$canonicalize): array {
            ksort($data);

            foreach ($data as $key => $value) {
                if (is_array($value)) {
                    $data[$key] = $canonicalize($value);
                }
            }

            return $data;
        };

        $encoded = json_encode(
            $canonicalize($values),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );

        return $encoded === false ? '{}' : $encoded;
    }

    /**
     * Synchronously seal a single audit log entry's hash chain.
     */
    public function sealLogEntry(int $logId): bool
    {
        return DB::transaction(function () use ($logId) {
            $predecessorId = SystemLog::where('id', '<', $logId)
                ->whereNotNull('entry_hash')
                ->orderBy('id', 'desc')
                ->value('id');

            $predecessor = null;
            if ($predecessorId) {
                $predecessor = SystemLog::where('id', $predecessorId)->lockForUpdate()->first();

                if (! $predecessor) {
                    throw new AuditIntegrityException("Predecessor log {$predecessorId} disappeared.");
                }

                $unsealedBetween = SystemLog::where('id', '>', $predecessorId)
                    ->where('id', '<', $logId)
                    ->whereNull('entry_hash')
                    ->where(fn ($q) => $q->whereNull('seal_status')
                        ->orWhere('seal_status', '!=', self::SEAL_STATUS_QUARANTINED))
                    ->exists();

                if ($unsealedBetween) {
                    return false;
                }
            }

            $log = SystemLog::where('id', $logId)
                ->whereNull('entry_hash')
                ->lockForUpdate()
                ->first();

            if (! $log) {
                return true;
            }

            // Quarantined rows sitting between the last sealed predecessor
            // and this entry break the link: the previous_hash records the
            // gap boundary explicitly instead of silently skipping them.
            $quarantineBoundary = SystemLog::where('id', '>', $predecessorId ?? 0)
                ->where('id', '<', $logId)
                ->where('seal_status', self::SEAL_STATUS_QUARANTINED)
                ->min('id');

            $previousHash = $quarantineBoundary !== null
                ? self::GAP_PREFIX.$quarantineBoundary
                : ($predecessor->entry_hash ?? null);

            // Seal with the v2 formula: the hash covers old_values,
            // new_values, severity and ip_address so post-seal payload edits
            // no longer verify clean.
            $entryHash = $this->computeEntryHash(
                $log->created_at->toIso8601String(),
                $log->user_id,
                $log->action,
                $log->entity_type,
                $log->entity_id,
                $previousHash,
                $log->old_values,
                $log->new_values,
                $log->severity,
                $log->ip_address
            );

            $log->update([
                'previous_hash' => $previousHash,
                'entry_hash' => $entryHash,
            ]);

            return true;
        });
    }

    /**
     * Verify the integrity of the audit log chain.
     *
     * Each entry is recomputed with the formula matching its stored hash
     * version: 'v2:'-prefixed hashes verify against the v2 payload formula,
     * bare hashes against the legacy v1 metadata-only formula.
     *
     * @return array<string, mixed>
     */
    public function verifyChainIntegrity(?int $limit = null): array
    {
        $previousHash = null;
        $checked = 0;
        $broken = null;
        $isFirstEntryInWindow = true;
        $quarantineBoundaries = [];

        $query = SystemLog::whereNotNull('entry_hash')->orderBy('id', 'asc');

        if ($limit !== null) {
            $lastIds = SystemLog::whereNotNull('entry_hash')
                ->orderBy('id', 'desc')
                ->limit($limit)
                ->pluck('id');

            $query->whereIn('id', $lastIds);
        }

        $query->chunkById(1000, function ($entries) use (&$previousHash, &$checked, &$broken, &$isFirstEntryInWindow, &$quarantineBoundaries) {
            foreach ($entries as $entry) {
                // A GAP:<id> previous_hash marks an explicit quarantine
                // boundary: the entry sealed across a permanently unsealable
                // row. Record it and skip the link check — the entry's own
                // hash still verifies (the marker is part of its payload).
                $isGapBoundary = str_starts_with((string) $entry->previous_hash, self::GAP_PREFIX);

                if ($isGapBoundary) {
                    $quarantineBoundaries[] = [
                        'entry_id' => $entry->id,
                        'quarantined_id' => (int) substr((string) $entry->previous_hash, strlen(self::GAP_PREFIX)),
                    ];
                }

                // The oldest entry in a limited window links to a predecessor
                // outside the window, so seed the expectation from its stored
                // previous_hash: skip the link check for the first entry and
                // compare strictly from the second one onward.
                if (! $isFirstEntryInWindow
                    && ! $isGapBoundary
                    && ! hash_equals((string) $previousHash, (string) $entry->previous_hash)) {
                    $broken = ['valid' => false, 'broken_at' => $entry->id, 'message' => 'Previous hash mismatch.'];

                    return false;
                }
                $isFirstEntryInWindow = false;

                $storedHash = (string) $entry->entry_hash;

                if (str_starts_with($storedHash, self::HASH_V2_PREFIX)) {
                    // v2: recompute with full row payload included.
                    $recomputedHash = $this->computeEntryHash(
                        $entry->created_at->toIso8601String(),
                        $entry->user_id,
                        $entry->action,
                        $entry->entity_type,
                        $entry->entity_id,
                        $entry->previous_hash,
                        $entry->old_values,
                        $entry->new_values,
                        $entry->severity,
                        $entry->ip_address
                    );
                } else {
                    // v1 (legacy): metadata-only payload.
                    $recomputedHash = $this->computeEntryHash(
                        $entry->created_at->toIso8601String(),
                        $entry->user_id,
                        $entry->action,
                        $entry->entity_type,
                        $entry->entity_id,
                        $entry->previous_hash
                    );
                }

                if (! hash_equals($recomputedHash, $storedHash)) {
                    $broken = ['valid' => false, 'broken_at' => $entry->id, 'message' => 'Entry hash mismatch.'];

                    return false;
                }

                $previousHash = $storedHash;
                $checked++;
            }
        });

        if ($broken) {
            return $broken + ['quarantine_boundaries' => $quarantineBoundaries];
        }

        return [
            'valid' => true,
            'broken_at' => null,
            'quarantine_boundaries' => $quarantineBoundaries,
            'message' => "Chain integrity verified: {$checked} entries checked.".
                ($quarantineBoundaries === [] ? '' : ' '.count($quarantineBoundaries).' quarantine boundary(ies) crossed.'),
        ];
    }

    public function getUnsealedCount(): int
    {
        // Quarantined rows are terminal — they will never seal, so they are
        // not counted among entries still awaiting a seal.
        return SystemLog::whereNull('entry_hash')
            ->where(fn ($q) => $q->whereNull('seal_status')
                ->orWhere('seal_status', '!=', self::SEAL_STATUS_QUARANTINED))
            ->count();
    }

    /**
     * Oldest unsealed (non-quarantined) entry timestamp, or null when the
     * chain is fully sealed. Used by audit:watch-unsealed for age alerting.
     */
    public function getOldestUnsealedAt(): ?Carbon
    {
        $ts = SystemLog::whereNull('entry_hash')
            ->where(fn ($q) => $q->whereNull('seal_status')
                ->orWhere('seal_status', '!=', self::SEAL_STATUS_QUARANTINED))
            ->min('created_at');

        return $ts ? Carbon::parse($ts) : null;
    }

    /**
     * Mark a row permanently unsealable. Quarantined rows keep their payload
     * (still auditable evidence) but are excluded from gap detection so the
     * chain resumes past them via a GAP:<id> previous_hash marker.
     */
    public function quarantineEntry(int $logId): void
    {
        SystemLog::where('id', $logId)
            ->whereNull('entry_hash')
            ->update(['seal_status' => self::SEAL_STATUS_QUARANTINED]);
    }
}
