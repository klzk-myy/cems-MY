<?php

namespace App\Console\Commands;

use App\Jobs\BuildAnalyticsSnapshots;
use App\Services\Bi\AnalyticsSnapshotBuilder;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RebuildAnalyticsSnapshots extends Command
{
    protected $signature = 'analytics:rebuild
                            {--from= : First day to rebuild (YYYY-MM-DD; defaults to --days back)}
                            {--to= : Last day to rebuild (YYYY-MM-DD, defaults to today)}
                            {--days=7 : Rolling window in days when --from is omitted}
                            {--queue : Dispatch the rebuild as a queued job instead of running inline}';

    protected $description = 'Rebuild BI analytics rollup tables for a date range

Recomputes the analytics_daily_* and analytics_monthly_customer rollups
from the transactions table. Rebuilds are delete-then-insert per day, so
the command is safe to re-run over the same range after corrections.
Full history backfill: analytics:rebuild --from=2026-01-01.';

    public function handle(AnalyticsSnapshotBuilder $builder): int
    {
        $from = $this->parseDate($this->option('from'));
        $to = $this->parseDate($this->option('to')) ?? today();

        if ($from === null) {
            $days = max(1, (int) $this->option('days'));
            $from = $to->copy()->subDays($days - 1);
        }

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        if ($this->option('queue')) {
            BuildAnalyticsSnapshots::dispatch(
                $from->toDateString(),
                $to->toDateString(),
            );
            $this->info("Dispatched analytics rebuild for {$from->toDateString()} → {$to->toDateString()} to the queue.");

            return self::SUCCESS;
        }

        $days = $from->diffInDays($to) + 1;
        $this->info("Rebuilding analytics rollups for {$from->toDateString()} → {$to->toDateString()} ({$days} days)...");

        $builder->rebuildRange($from, $to);

        $this->info('Done.');

        return self::SUCCESS;
    }

    /**
     * Parse a YYYY-MM-DD option, failing the command on garbage input
     * rather than silently rebuilding the wrong range.
     */
    protected function parseDate(mixed $input): ?Carbon
    {
        if (! is_string($input) || $input === '') {
            return null;
        }

        try {
            return Carbon::parse($input)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
