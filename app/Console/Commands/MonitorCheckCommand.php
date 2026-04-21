<?php

namespace App\Console\Commands;

use App\Services\SystemAlertService;
use App\Services\SystemHealthService;
use Illuminate\Console\Command;

class MonitorCheckCommand extends Command
{
    protected $signature = 'monitor:check
                            {--alert : Send alerts for warning/critical status}
                            {--output=table : Output format (table, json, text)}';

    protected $description = 'Run all system health checks';

    protected SystemHealthService $monitorService;

    protected SystemAlertService $alertService;

    public function __construct(
        SystemHealthService $monitorService,
        SystemAlertService $alertService
    ) {
        parent::__construct();
        $this->monitorService = $monitorService;
        $this->alertService = $alertService;
    }

    public function handle(): int
    {
        $this->info('Running system health checks...\n');

        $results = $this->monitorService->runAllChecks();
        $overallStatus = $this->monitorService->getOverallStatus();

        // Display results
        $this->displayResults($results);

        // Send alerts if requested
        if ($this->option('alert')) {
            $this->sendAlerts($results);
        }

        // Return appropriate exit code
        return match ($overallStatus) {
            'critical' => 2,
            'warning' => 1,
            default => 0,
        };
    }

    protected function displayResults(array $results): void
    {
        $format = $this->option('output');

        if ($format === 'json') {
            $this->line(json_encode($results, JSON_PRETTY_PRINT));

            return;
        }

        if ($format === 'text') {
            foreach ($results as $name => $result) {
                $statusIcon = match ($result['status']) {
                    'ok' => '✓',
                    'warning' => '⚠',
                    'critical' => '✗',
                    default => '?',
                };
                $this->line("{$statusIcon} {$name}: {$result['message']}");
            }

            return;
        }

        // Default table output
        $rows = [];
        foreach ($results as $name => $result) {
            $statusColor = match ($result['status']) {
                'ok' => 'green',
                'warning' => 'yellow',
                'critical' => 'red',
                default => 'gray',
            };

            $rows[] = [
                ucfirst(str_replace('_', ' ', $name)),
                "<fg={$statusColor}>{$result['status']}</>",
                $result['message'],
            ];
        }

        $this->table(['Check', 'Status', 'Message'], $rows);

        $overallStatus = $this->monitorService->getOverallStatus();
        $overallColor = match ($overallStatus) {
            'ok' => 'green',
            'warning' => 'yellow',
            'critical' => 'red',
            default => 'gray',
        };

        $this->newLine();
        $this->line("<fg={$overallColor}>Overall Status: {$overallStatus}</>");
    }

    protected function sendAlerts(array $results): void
    {
        $sent = 0;

        foreach ($results as $name => $result) {
            if ($result['status'] === 'critical') {
                $this->alertService->critical(
                    "CRITICAL: {$result['message']}",
                    [
                        'source' => "monitor:{$name}",
                        'metadata' => $result['details'] ?? [],
                    ]
                );
                $sent++;
            } elseif ($result['status'] === 'warning') {
                $this->alertService->warning(
                    "WARNING: {$result['message']}",
                    [
                        'source' => "monitor:{$name}",
                        'metadata' => $result['details'] ?? [],
                    ]
                );
                $sent++;
            }
        }

        if ($sent > 0) {
            $this->newLine();
            $this->info("Sent {$sent} alert(s)");
        }
    }
}
