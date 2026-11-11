<?php

namespace App\Jobs;

use App\Services\Bi\AnalyticsSnapshotBuilder;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Rebuilds BI analytics rollups for a date range.
 *
 * Scheduled nightly with no arguments — rebuilds a rolling window
 * (default 7 days ending today) so late cancellations, reversals, and
 * corrections are absorbed into the snapshots. `analytics:rebuild`
 * dispatches the same job for ad-hoc backfills.
 */
class BuildAnalyticsSnapshots implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Timeout in seconds — a month-long backfill is ~30 day-builds.
     */
    public int $timeout = 600;

    public int $tries = 3;

    /**
     * Keep the uniqueness lock past the timeout so a second dispatch for
     * the same range while a rebuild is running is dropped, not queued.
     */
    public int $uniqueFor = 3600;

    public function __construct(
        public ?string $from = null,
        public ?string $to = null,
        public int $rollingDays = 7,
    ) {}

    public function uniqueId(): string
    {
        return 'analytics-snapshots:'
            .($this->from ?? 'auto').':'
            .($this->to ?? 'auto').':'
            .$this->rollingDays;
    }

    public function handle(AnalyticsSnapshotBuilder $builder): void
    {
        $to = $this->to !== null ? Carbon::parse($this->to) : today();
        $from = $this->from !== null
            ? Carbon::parse($this->from)
            : $to->copy()->subDays($this->rollingDays - 1);

        Log::info('BuildAnalyticsSnapshots started', [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ]);

        $builder->rebuildRange($from, $to);
    }
}
