<?php

namespace App\Console\Commands;

use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\SystemAlert;
use App\Services\System\SystemAlertService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CheckRateStalenessCommand extends Command
{
    protected $signature = 'rates:staleness-check';

    protected $description = 'Raise a system alert when exchange rates have not been refreshed within the configured window, or when an active currency has no rate card';

    public function handle(SystemAlertService $alertService): int
    {
        $this->checkStaleness($alertService);
        $this->checkMissingCards($alertService);

        return self::SUCCESS;
    }

    /**
     * Alert when the newest rate refresh is older than the configured window.
     *
     * Staleness is checked per scope: a fresh branch card must not mask a
     * stale company-wide card (the fallback every branch resolves to), and
     * a stale branch card is still served to that branch's readers.
     */
    private function checkStaleness(SystemAlertService $alertService): void
    {
        $maxAgeHours = (int) config('cems.rate_staleness_hours', 8);

        $scopes = ExchangeRate::query()
            ->toBase()
            ->select('branch_id')
            ->selectRaw('MAX(updated_at) as last_update')
            ->groupBy('branch_id')
            ->get();

        if ($scopes->isEmpty()) {
            $this->info('No exchange rates stored yet; skipping staleness check.');

            return;
        }

        $staleScopes = [];
        foreach ($scopes as $scope) {
            $lastUpdate = Carbon::parse($scope->last_update);
            $ageHours = $lastUpdate->diffInHours(now(), false);

            if ($ageHours >= $maxAgeHours) {
                $staleScopes[] = [
                    'branch_id' => $scope->branch_id,
                    'last_updated_at' => $lastUpdate->toIso8601String(),
                    'age_hours' => (int) $ageHours,
                ];
            }
        }

        if (empty($staleScopes)) {
            $this->info('Exchange rates are fresh in every scope.');

            return;
        }

        // Avoid alert storms: only one open staleness alert at a time.
        $alreadyAlerting = SystemAlert::query()
            ->where('source', 'rate_staleness')
            ->unacknowledged()
            ->exists();

        if ($alreadyAlerting) {
            $this->info('Staleness alert already active; not raising a duplicate.');

            return;
        }

        $scopeList = collect($staleScopes)
            ->map(fn (array $s) => ($s['branch_id'] === null ? 'company-wide' : "branch {$s['branch_id']}")." ({$s['age_hours']}h ago)")
            ->implode(', ');

        $alertService->warning(
            "Exchange rates are stale for {$scopeList}, exceeding the {$maxAgeHours}-hour threshold.",
            [
                'source' => 'rate_staleness',
                'metadata' => [
                    'stale_scopes' => $staleScopes,
                    'threshold_hours' => $maxAgeHours,
                ],
            ]
        );

        $this->warn("Exchange rates are stale ({$scopeList}); warning alert raised.");
    }

    /**
     * Alert when an active currency has no rate card.
     *
     * A booking in a currency with no card skips the rate-deviation guard
     * (RateApiService::validateRateDeviation logs the skip and allows the
     * booking), so a missing card is a silent compliance gap rather than a
     * mere setup gap — it must be visible to operators.
     */
    private function checkMissingCards(SystemAlertService $alertService): void
    {
        $activeCodes = Currency::query()
            ->where('is_active', true)
            ->pluck('code')
            ->reject(fn (string $code) => $code === Currency::baseCurrency())
            ->values();

        if ($activeCodes->isEmpty()) {
            return;
        }

        // Company-wide cards are the fallback every branch resolves to; a
        // currency covered only by a branch card still leaves company
        // readers (and other branches) without a rate.
        $covered = ExchangeRate::query()->active()->whereNull('branch_id')->distinct()->pluck('currency_code')->flip();

        $missing = $activeCodes->reject(fn (string $code) => $covered->has($code))->values();

        if ($missing->isEmpty()) {
            $this->info('Every active currency has a rate card.');

            return;
        }

        // Avoid alert storms: one open missing-card alert at a time; it is
        // re-raised only once the previous alert is acknowledged.
        $alreadyAlerting = SystemAlert::query()
            ->where('source', 'rate_missing')
            ->unacknowledged()
            ->exists();

        if ($alreadyAlerting) {
            $this->info('Missing-rate-card alert already active; not raising a duplicate.');

            return;
        }

        $alertService->warning(
            'Active currencies without an exchange-rate card: '.$missing->implode(', ').
            '. Bookings in these currencies bypass the rate-deviation guard.',
            [
                'source' => 'rate_missing',
                'metadata' => [
                    'currencies' => $missing->all(),
                    'count' => $missing->count(),
                ],
            ]
        );

        $this->warn("Currencies missing a rate card: {$missing->implode(', ')}; warning alert raised.");
    }
}
