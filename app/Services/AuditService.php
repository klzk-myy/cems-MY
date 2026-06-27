<?php

namespace App\Services;

use App\Jobs\Audit\SealAuditHashJob;
use App\Models\SystemLog;
use App\Services\Concerns\AuditsCompliance;
use App\Services\Concerns\AuditsCustomers;
use App\Services\Concerns\AuditsMfaSession;
use App\Services\Concerns\AuditsReporting;
use App\Services\Concerns\AuditsSystem;
use App\Services\Concerns\AuditsTransactions;
use App\Services\Contracts\AuditServiceInterface;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Request;

class AuditService implements AuditServiceInterface
{
    use AuditsCompliance;
    use AuditsCustomers;
    use AuditsMfaSession;
    use AuditsReporting;
    use AuditsSystem;
    use AuditsTransactions;

    /**
     * Compute SHA-256 hash for a log entry (tamper-evident chain)
     *
     * Each log entry's hash is computed from:
     * - timestamp (created_at)
     * - user_id
     * - action
     * - entity_type
     * - entity_id
     * - previous_hash (chain link to prior entry)
     */
    public function computeEntryHash(
        string $timestamp,
        ?int $userId,
        string $action,
        ?string $entityType,
        ?int $entityId,
        ?string $previousHash
    ): string {
        $data = implode('|', [
            $timestamp,
            (string) $userId,
            $action,
            $entityType ?? '',
            $entityId !== null ? (string) $entityId : '',
            $previousHash ?? '',
        ]);

        return hash('sha256', $data);
    }

    /**
     * Log with severity level (tamper-evident with hash chaining)
     *
     * Hash sealing is done asynchronously via SealAuditHashJob to avoid
     * global lock contention. The entry is created with null hash values
     * and sealed by the queued job.
     */
    public function logWithSeverity(
        string $action,
        array $data = [],
        string $severity = 'INFO'
    ): SystemLog {
        $userId = $data['user_id'] ?? auth()->id();

        // Create log entry with null hash (will be sealed async)
        $log = SystemLog::create([
            'user_id' => $userId,
            'action' => $action,
            'severity' => $severity,
            'entity_type' => $data['entity_type'] ?? null,
            'entity_id' => $data['entity_id'] ?? null,
            'old_values' => ! empty($data['old_values'] ?? []) ? $data['old_values'] : null,
            'new_values' => ! empty($data['new_values'] ?? []) ? $data['new_values'] : null,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'session_id' => session()->getId(),
            'previous_hash' => null,
            'entry_hash' => null,
        ]);

        // Dispatch async job to seal the hash chain
        SealAuditHashJob::dispatch($log->id);

        return $log;
    }

    /**
     * Log standard action
     */
    public function log(
        string $action,
        ?int $userId = null,
        ?string $entityType = null,
        ?int $entityId = null,
        array $oldValues = [],
        array $newValues = []
    ): SystemLog {
        return $this->logWithSeverity(
            $action,
            [
                'user_id' => $userId,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'old_values' => $oldValues,
                'new_values' => $newValues,
            ],
            'INFO'
        );
    }

    /**
     * Log transaction action
     */
    public function logTransaction(
        string $action,
        int $transactionId,
        array $data = []
    ): SystemLog {
        $severity = $data['severity'] ?? 'INFO';

        return $this->logWithSeverity(
            $action,
            [
                'entity_type' => 'Transaction',
                'entity_id' => $transactionId,
                'old_values' => $data['old'] ?? [],
                'new_values' => $data['new'] ?? [],
            ],
            $severity
        );
    }

    /**
     * Log customer action
     */
    public function logCustomer(
        string $action,
        int $customerId,
        array $data = []
    ): SystemLog {
        $severity = $data['severity'] ?? 'INFO';

        return $this->logWithSeverity(
            $action,
            [
                'entity_type' => 'Customer',
                'entity_id' => $customerId,
                'old_values' => $data['old'] ?? [],
                'new_values' => $data['new'] ?? [],
            ],
            $severity
        );
    }

    /**
     * Verify the integrity of the audit log chain.
     *
     * Checks that each entry's stored hash matches the recomputed hash
     * based on its actual data and the previous entry's hash.
     *
     * @param  int|null  $limit  Number of recent entries to verify (null = all)
     * @return array{valid: bool, broken_at: int|null, message: string}
     */
    public function verifyChainIntegrity(?int $limit = null): array
    {
        $previousHash = null;
        $checked = 0;
        $broken = null;

        $query = SystemLog::whereNotNull('entry_hash')->orderBy('id', 'asc');

        if ($limit !== null) {
            $lastIds = SystemLog::whereNotNull('entry_hash')
                ->orderBy('id', 'desc')
                ->limit($limit)
                ->pluck('id');

            $query->whereIn('id', $lastIds);
        }

        $query->chunkById(1000, function ($entries) use (&$previousHash, &$checked, &$broken) {
            foreach ($entries as $entry) {
                // Verify the previous_hash chain link using timing-safe comparison
                if (! hash_equals((string) $previousHash, (string) $entry->previous_hash)) {
                    $broken = ['valid' => false, 'broken_at' => $entry->id, 'message' => 'Previous hash mismatch.'];

                    return false;
                }

                // Recompute the entry hash and verify it matches
                $recomputedHash = $this->computeEntryHash(
                    $entry->created_at->toIso8601String(),
                    $entry->user_id,
                    $entry->action,
                    $entry->entity_type,
                    $entry->entity_id,
                    $entry->previous_hash
                );

                if (! hash_equals($recomputedHash, (string) $entry->entry_hash)) {
                    $broken = ['valid' => false, 'broken_at' => $entry->id, 'message' => 'Entry hash mismatch.'];

                    return false;
                }

                $previousHash = $entry->entry_hash;
                $checked++;
            }
        });

        if ($broken) {
            return $broken;
        }

        return [
            'valid' => true,
            'broken_at' => null,
            'message' => "Chain integrity verified: {$checked} entries checked.",
        ];
    }

    /**
     * Get count of unsealed audit log entries.
     * Useful for monitoring/alerting on the async hash sealing pipeline.
     */
    public function getUnsealedCount(): int
    {
        return SystemLog::whereNull('entry_hash')->count();
    }

    /**
     * Batch insert multiple audit log entries.
     *
     * More efficient than individual inserts for bulk operations.
     * Creates entries with null hashes - SealAuditHashJob will seal them async.
     *
     * @param  array  $logs  Array of log data arrays
     * @return bool True if insert succeeded
     */
    public function logBatch(array $logs): bool
    {
        if (empty($logs)) {
            return true;
        }

        $now = now();
        $ipAddress = Request::ip();
        $userAgent = Request::userAgent();
        $sessionId = session()->getId();

        $batchData = array_map(function ($log) use ($now, $ipAddress, $userAgent, $sessionId) {
            return [
                'user_id' => $log['user_id'] ?? auth()->id(),
                'action' => $log['action'],
                'severity' => $log['severity'] ?? 'INFO',
                'entity_type' => $log['entity_type'] ?? null,
                'entity_id' => $log['entity_id'] ?? null,
                'old_values' => ! empty($log['old_values'] ?? []) ? $log['old_values'] : null,
                'new_values' => ! empty($log['new_values'] ?? []) ? $log['new_values'] : null,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'session_id' => $sessionId,
                'previous_hash' => null,
                'entry_hash' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, $logs);

        // Insert batch
        $inserted = SystemLog::insert($batchData);

        if ($inserted) {
            // Dispatch async jobs to seal hashes in chunks
            // Get the IDs of the inserted records
            $lastId = SystemLog::max('id');
            $count = count($logs);
            $firstId = $lastId - $count + 1;

            $chunks = collect(range($firstId, $firstId + $count - 1))->chunk(100);
            foreach ($chunks as $chunk) {
                Bus::batch(
                    $chunk->map(fn ($id) => new SealAuditHashJob($id))->toArray()
                )->dispatch();
            }
        }

        return $inserted;
    }
}
