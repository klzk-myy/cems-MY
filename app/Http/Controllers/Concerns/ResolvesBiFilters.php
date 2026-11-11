<?php

namespace App\Http\Controllers\Concerns;

use App\Services\Bi\AnalyticsSnapshotBuilder;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Shared BI-page helpers: the lenient start_date/end_date convention used
 * across the report pages (unparseable input falls back to the trailing
 * default window; a reversed range is swapped so an inverted whereBetween
 * never silently reads as "no activity"), plus the debounced same-day
 * rollup refresh that keeps BI pages near-live between nightly rebuilds.
 */
trait ResolvesBiFilters
{
    /**
     * Debounce window for the daily-rollup refresh triggered by page loads.
     */
    protected const BI_REFRESH_DEBOUNCE_SECONDS = 300;

    /**
     * Rebuild today's daily rollups at most once per debounce window.
     * Cache::add wins the race — only the first request inside the window
     * pays for the three aggregate queries.
     */
    protected function refreshTodaysRollups(AnalyticsSnapshotBuilder $snapshotBuilder): void
    {
        if (Cache::add('bi:rollup-refresh', true, self::BI_REFRESH_DEBOUNCE_SECONDS)) {
            $snapshotBuilder->refreshToday();
        }
    }

    /**
     * Normalize the requested [from, to] period.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function resolveBiPeriod(Request $request, ?Carbon $defaultFrom = null): array
    {
        $from = $this->parseBiDate($request->input('start_date')) ?? ($defaultFrom ?? today()->subDays(29));
        $to = $this->parseBiDate($request->input('end_date')) ?? today();

        if ($from->greaterThan($to)) {
            return [$to, $from];
        }

        return [$from, $to];
    }

    /**
     * Parse a date input, returning null when it is unparseable.
     */
    protected function parseBiDate(mixed $input): ?Carbon
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
