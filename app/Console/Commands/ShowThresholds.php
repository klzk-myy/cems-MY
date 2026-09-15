<?php

namespace App\Console\Commands;

use App\Services\ThresholdService;
use App\Support\ThresholdMetadata;
use Illuminate\Console\Command;

class ShowThresholds extends Command
{
    protected $signature = 'thresholds:show
                            {--category= : Limit output to a single category}
                            {--overrides : Show only keys with an active DB override}';

    protected $description = 'Show effective threshold values, their source (db override vs config), and last change';

    public function __construct(protected ThresholdService $thresholdService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        // File/env defaults, bypassing runtime config mutations from set().
        $categories = $this->thresholdService->configDefaults();

        if ($category = $this->option('category')) {
            if (! isset($categories[$category])) {
                $this->error("Unknown category: {$category}");

                return self::FAILURE;
            }
            $categories = [$category => $categories[$category]];
        }

        $overrides = $this->thresholdService->latestOverrides();
        $activeOverrides = $this->thresholdService->activeOverrides();

        $rows = [];
        $overrideCount = 0;

        foreach ($categories as $category => $keys) {
            foreach ($keys as $key => $configValue) {
                $compound = "{$category}.{$key}";
                $override = $overrides[$compound] ?? null;
                $isOverridden = isset($activeOverrides[$compound]);
                $source = $isOverridden ? 'db' : 'config';
                $active = $isOverridden ? $override->new_value : $configValue;

                if ($isOverridden) {
                    $overrideCount++;
                }

                if ($this->option('overrides') && ! $isOverridden) {
                    continue;
                }

                $meta = ThresholdMetadata::key($category, $key);

                $rows[] = [
                    $compound,
                    $meta['label'],
                    (string) $configValue,
                    (string) $active,
                    $source,
                    $override?->changed_at->format('Y-m-d H:i') ?? '—',
                    $override?->user->email ?? $override->changed_by ?? '—',
                    $override->change_reason ?? '—',
                ];
            }
        }

        if ($rows === []) {
            $this->info('No thresholds matched.');

            return self::SUCCESS;
        }

        $this->table(
            ['Key', 'Label', 'Config', 'Active', 'Source', 'Last Change', 'Changed By', 'Reason'],
            $rows
        );

        if ($overrideCount > 0) {
            $this->warn("{$overrideCount} value(s) are driven by DB overrides — env changes will NOT affect them.");
        }

        return self::SUCCESS;
    }
}
