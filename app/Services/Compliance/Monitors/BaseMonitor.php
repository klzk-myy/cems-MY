<?php

namespace App\Services\Compliance\Monitors;

use App\Enums\FindingSeverity;
use App\Enums\FindingStatus;
use App\Enums\FindingType;
use App\Models\Compliance\ComplianceFinding;
use App\Notifications\Compliance\ComplianceFindingNotification;
use App\Services\Compliance\AlertTriageService;
use App\Services\System\MathService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Abstract base class for compliance monitors.
 *
 * All monitors should extend this class and implement the run() and getFindingType() methods.
 */
abstract class BaseMonitor
{
    protected MathService $math;

    protected AlertTriageService $alertTriage;

    public function __construct(MathService $math, AlertTriageService $alertTriage)
    {
        $this->math = $math;
        $this->alertTriage = $alertTriage;
    }

    /**
     * Run the monitor and return findings as arrays.
     *
     * @return array Array of finding data arrays
     */
    abstract public function run(): array;

    /**
     * Return the FindingType for this monitor.
     */
    abstract protected function getFindingType(): FindingType;

    /**
     * Return default severity (can be overridden).
     */
    protected function getDefaultSeverity(): FindingSeverity
    {
        return $this->getFindingType()->defaultSeverity();
    }

    /**
     * Create a finding data array (not yet saved).
     */
    protected function createFinding(
        FindingType $type,
        FindingSeverity $severity,
        string $subjectType,
        int $subjectId,
        array $details
    ): array {
        return [
            'finding_type' => $type->value,
            'severity' => $severity->value,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'details' => $details,
            'status' => FindingStatus::New->value,
            'generated_at' => now(),
        ];
    }

    /**
     * Store a finding in the database, unless an open finding of the same
     * type already exists for the subject (dedup: recurring monitors with
     * overlapping windows would otherwise re-fire the same finding daily,
     * including critical sanction matches — those still flow through and are
     * deduplicated the same way).
     *
     * When suppressing, the new run's details are merged into the open
     * finding instead of being discarded, so recurring detections accumulate
     * their evidence (matches, scores, last-seen timestamp) on one finding.
     */
    protected function storeFinding(array $findingData): ?ComplianceFinding
    {
        $existing = ComplianceFinding::openDuplicateOf(
            $findingData['finding_type'],
            $findingData['subject_type'],
            (int) $findingData['subject_id']
        )->first();

        if ($existing === null) {
            $finding = ComplianceFinding::create($findingData);
            $this->notifyComplianceOfficers($finding);

            return $finding;
        }

        $existing->details = $this->mergeFindingDetails(
            is_array($existing->details) ? $existing->details : [],
            is_array($findingData['details'] ?? null) ? $findingData['details'] : []
        );
        // save() bumps updated_at so reviewers can see the finding is still active.
        $existing->save();

        return null;
    }

    /**
     * Notify compliance officers about a newly created finding.
     *
     * Only called after actual creation — merged duplicates never re-notify.
     * The notification itself is queued; failures are logged and swallowed so
     * a broken mail/broadcast backend can never fail a monitor run.
     */
    private function notifyComplianceOfficers(ComplianceFinding $finding): void
    {
        try {
            $notified = $this->alertTriage->notifyAvailableOfficers(new ComplianceFindingNotification($finding));

            if ($notified === 0) {
                Log::warning('No active compliance officers to notify of finding', [
                    'finding_id' => $finding->id,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to dispatch compliance finding notification', [
                'finding_id' => $finding->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Merge a re-detection's details into the existing open finding.
     *
     * - Collections of entries (e.g. sanction "matches") are unioned,
     *   deduplicating by entry identity so repeat hits don't stack copies.
     * - Numeric score/count values keep the higher reading so a signal never
     *   regresses between runs.
     * - Every other value adopts the latest run's observation.
     * - last_detected_at records when the finding was most recently seen.
     *
     * @param  array  $current  Details persisted on the open finding
     * @param  array  $incoming  Details from the current monitor run
     */
    protected function mergeFindingDetails(array $current, array $incoming): array
    {
        foreach ($incoming as $key => $value) {
            $currentValue = $current[$key] ?? null;

            if ($this->isEntryList($value) && $this->isEntryList($currentValue)) {
                $current[$key] = $this->mergeEntryLists($currentValue, $value);

                continue;
            }

            if (
                is_numeric($value)
                && is_numeric($currentValue)
                && (str_contains($key, 'score') || str_contains($key, 'count'))
            ) {
                $current[$key] = max((float) $currentValue, (float) $value);

                continue;
            }

            $current[$key] = $value;
        }

        $current['last_detected_at'] = now()->toDateTimeString();

        return $current;
    }

    /**
     * True for sequentially-keyed arrays whose elements are all arrays
     * (i.e. lists of structured entries, not scalar lists).
     *
     * @param  mixed  $value
     */
    private function isEntryList($value): bool
    {
        if (! is_array($value) || $value === [] || ! array_is_list($value)) {
            return false;
        }

        foreach ($value as $entry) {
            if (! is_array($entry)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Append new entries to existing ones without duplicating identities.
     */
    private function mergeEntryLists(array $existingEntries, array $newEntries): array
    {
        $seen = [];

        foreach ($existingEntries as $entry) {
            $seen[$this->entryIdentity($entry)] = true;
        }

        foreach ($newEntries as $entry) {
            $identity = $this->entryIdentity($entry);

            if (! isset($seen[$identity])) {
                $existingEntries[] = $entry;
                $seen[$identity] = true;
            }
        }

        return $existingEntries;
    }

    /**
     * Stable identity for a detail entry: prefer an explicit identifier,
     * fall back to a canonical serialization of the whole entry.
     */
    private function entryIdentity(array $entry): string
    {
        foreach (['entry_id', 'transaction_id', 'id'] as $idKey) {
            if (array_key_exists($idKey, $entry)) {
                return $idKey.':'.$entry[$idKey];
            }
        }

        ksort($entry);

        return md5((string) json_encode($entry));
    }

    /**
     * Execute: run monitor and store all findings.
     *
     * @return array Array of stored ComplianceFinding models (duplicates skipped)
     */
    public function execute(): array
    {
        $findings = $this->run();

        return DB::transaction(function () use ($findings) {
            $stored = [];
            foreach ($findings as $finding) {
                $storedFinding = $this->storeFinding($finding);

                if ($storedFinding !== null) {
                    $stored[] = $storedFinding;
                }
            }

            return $stored;
        });
    }
}
