<?php

namespace App\Console\Commands;

use App\Services\ThresholdService;
use Illuminate\Console\Command;

class ResetThreshold extends Command
{
    protected $signature = 'thresholds:reset
                            {category : Threshold category (e.g. approval)}
                            {key : Threshold key (e.g. auto_approve)}
                            {--reason= : Reason recorded on the reset audit row}';

    protected $description = 'Reset a DB-overridden threshold back to its config value (append-only audit row)';

    public function __construct(protected ThresholdService $thresholdService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $category = (string) $this->argument('category');
        $key = strtolower((string) $this->argument('key'));

        $defaults = $this->thresholdService->configDefaults();
        if (! isset($defaults[$category]) || ! array_key_exists($key, $defaults[$category])) {
            $this->error("Unknown threshold: {$category}.{$key}");

            return self::FAILURE;
        }

        if (! $this->thresholdService->isOverridden($category, $key)) {
            $this->info("{$category}.{$key} has no active DB override — already at its config value.");

            return self::SUCCESS;
        }

        $current = $this->thresholdService->get($category, $key);

        $this->thresholdService->reset(
            $category,
            $key,
            $this->option('reason') ?? 'Reset to config default via thresholds:reset'
        );

        $this->info("{$category}.{$key} reset: {$current} → {$defaults[$category][$key]}");

        return self::SUCCESS;
    }
}
