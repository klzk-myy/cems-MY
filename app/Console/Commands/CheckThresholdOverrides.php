<?php

namespace App\Console\Commands;

use App\Models\SystemAlert;
use App\Services\System\SystemAlertService;
use App\Services\ThresholdService;
use Illuminate\Console\Command;

class CheckThresholdOverrides extends Command
{
    protected $signature = 'thresholds:check-overrides
                            {--alert : Raise a deduplicated SystemAlert listing the overridden keys}
                            {--fail : Exit non-zero when overrides are active (deploy-check gating)}';

    protected $description = 'List active threshold DB overrides (deploy check; warn-only by default)';

    public function __construct(protected ThresholdService $thresholdService)
    {
        parent::__construct();
    }

    public function handle(SystemAlertService $alerts): int
    {
        $active = $this->thresholdService->activeOverrides();

        if ($active === []) {
            $this->info('No active threshold overrides.');

            return self::SUCCESS;
        }

        $this->warn(count($active).' threshold(s) are driven by DB overrides — .env changes will not affect them:');
        foreach ($active as $compound => $audit) {
            $this->line("  {$compound} = {$audit->new_value}");
        }

        if ($this->option('alert')) {
            $this->raiseAlert($alerts, array_keys($active));
        }

        return $this->option('fail') ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Raise one unacknowledged warning alert per distinct override set —
     * repeated runs while the same overrides persist do not re-alert.
     *
     * @param  list<string>  $keys
     */
    private function raiseAlert(SystemAlertService $alerts, array $keys): void
    {
        sort($keys);

        $existing = SystemAlert::unacknowledged()
            ->where('source', 'thresholds')
            ->get()
            ->first(function (SystemAlert $alert) use ($keys) {
                $alertKeys = $alert->metadata['overrides'] ?? [];
                sort($alertKeys);

                return $alertKeys === $keys;
            });

        if ($existing !== null) {
            $this->info('An unacknowledged override alert already exists — skipping.');

            return;
        }

        $alerts->warning(
            'Active threshold DB overrides: '.implode(', ', $keys)
                .'. Environment changes will not affect these keys until they are reset.',
            ['source' => 'thresholds', 'metadata' => ['overrides' => $keys]]
        );

        $this->info('SystemAlert raised.');
    }
}
