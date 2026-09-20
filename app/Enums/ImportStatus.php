<?php

namespace App\Enums;

/**
 * Outcome of a bulk-import run (sanction_import_logs.status,
 * adverse_media_import_logs.status). Distinct from UpdateStatus, which
 * tracks a list's refresh lifecycle (never_run/pending) rather than a run's
 * outcome.
 */
enum ImportStatus: string
{
    case Success = 'success';
    case Partial = 'partial';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Success => 'Success',
            self::Partial => 'Partial',
            self::Failed => 'Failed',
        };
    }

    /**
     * Derive the run outcome from row counters: clean imports succeed, runs
     * that still wrote rows partially succeed, total skips fail.
     */
    public static function fromCounts(int $skipped, int $added, int $updated): self
    {
        return match (true) {
            $skipped === 0 => self::Success,
            ($added + $updated) > 0 => self::Partial,
            default => self::Failed,
        };
    }
}
