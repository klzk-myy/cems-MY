<?php

namespace App\Console\Commands;

use App\Services\AlertService;
use Illuminate\Console\Command;

class AlertSendCommand extends Command
{
    protected $signature = 'alert:send
                            {message : The alert message}
                            {--level=info : Alert level (info, warning, critical)}
                            {--source=cli : Source of the alert}
                            {--recipient= : Email recipient (overrides default)}';

    protected $description = 'Send a system alert';

    protected AlertService $alertService;

    public function __construct(AlertService $alertService)
    {
        parent::__construct();
        $this->alertService = $alertService;
    }

    public function handle(): int
    {
        $message = $this->argument('message');
        $level = $this->option('level');
        $source = $this->option('source');

        // Validate level
        if (! in_array($level, ['info', 'warning', 'critical'])) {
            $this->error("Invalid alert level: {$level}. Must be one of: info, warning, critical");

            return 1;
        }

        $options = [
            'source' => $source,
        ];

        // Add recipient if specified
        if ($this->option('recipient')) {
            $options['recipients'] = [$this->option('recipient')];
        }

        // Send alert based on level
        $alert = match ($level) {
            'critical' => $this->alertService->critical($message, $options),
            'warning' => $this->alertService->warning($message, $options),
            default => $this->alertService->info($message, $options),
        };

        // Determine status color
        $color = match ($level) {
            'critical' => 'red',
            'warning' => 'yellow',
            default => 'green',
        };

        $this->newLine();
        $this->line("<fg={$color}>✓ Alert sent successfully</>");
        $this->line("  Level: {$level}");
        $this->line("  Message: {$message}");
        $this->line("  Source: {$source}");
        $this->line("  Alert ID: {$alert->id}");

        return 0;
    }
}
