<?php

namespace App\Console\Commands;

use App\Models\SystemAlert;
use App\Models\SystemHealthCheck;
use App\Services\MonitorService;
use Illuminate\Console\Command;

class MonitorStatusCommand extends Command
{
    protected $signature = 'monitor:status
                            {--checks : Show detailed check history}
                            {--alerts : Show unacknowledged alerts}
                            {--limit=10 : Number of history entries to show}';

    protected $description = 'Show current system monitoring status';

    protected MonitorService $monitorService;

    public function __construct(MonitorService $monitorService)
    {
        parent::__construct();
        $this->monitorService = $monitorService;
    }

    public function handle(): int
    {
        $summary = $this->monitorService->getStatusSummary();

        // Display overall status
        $this->displayOverallStatus($summary);

        // Display individual checks
        $this->displayChecks($summary['checks']);

        // Display detailed check history if requested
        if ($this->option('checks')) {
            $this->displayCheckHistory();
        }

        // Display alerts if requested
        if ($this->option('alerts')) {
            $this->displayAlerts();
        }

        // Return appropriate exit code
        return match ($summary['overall_status']) {
            'critical' => 2,
            'warning' => 1,
            default => 0,
        };
    }

    protected function displayOverallStatus(array $summary): void
    {
        $status = $summary['overall_status'];
        $statusColor = match ($status) {
            'ok' => 'green',
            'warning' => 'yellow',
            'critical' => 'red',
            default => 'gray',
        };

        $this->newLine();
        $this->line('╔══════════════════════════════════════════╗');
        $this->line("║  <fg={$statusColor}>SYSTEM STATUS: ".strtoupper($status).str_repeat(' ', 20 - strlen($status)).'</>  ║');
        $this->line('╚══════════════════════════════════════════╝');
        $this->newLine();

        // Summary table
        $rows = [];
        foreach ($summary['summary'] as $statusType => $count) {
            $color = match ($statusType) {
                'ok' => 'green',
                'warning' => 'yellow',
                'critical' => 'red',
                'unknown' => 'gray',
                default => 'white',
            };
            $rows[] = [ucfirst($statusType), "<fg={$color}>{$count}</>"];
        }

        $this->table(['Status', 'Count'], $rows);

        if ($summary['last_check']) {
            $this->line("Last check: {$summary['last_check']->diffForHumans()}");
        } else {
            $this->warn('No health checks have been run yet');
        }

        $this->newLine();
    }

    protected function displayChecks(array $checks): void
    {
        $this->line('Current Check Status:');
        $this->line(str_repeat('─', 60));

        foreach ($checks as $name => $check) {
            if ($check === null) {
                $this->line("  <fg=gray>{$name}: Not checked yet</>");

                continue;
            }

            $statusIcon = match ($check->status) {
                'ok' => '<fg=green>✓</>',
                'warning' => '<fg=yellow>⚠</>',
                'critical' => '<fg=red>✗</>',
                default => '<fg=gray>?</>',
            };

            $this->line("  {$statusIcon} <fg=white>".ucfirst(str_replace('_', ' ', $name)).":</> {$check->message}");
            $this->line("     <fg=gray>Checked: {$check->checked_at->diffForHumans()}</>");
        }

        $this->newLine();
    }

    protected function displayCheckHistory(): void
    {
        $limit = $this->option('limit');

        $this->line('Recent Check History:');
        $this->line(str_repeat('─', 60));

        $checks = SystemHealthCheck::latest()
            ->limit($limit)
            ->get();

        if ($checks->isEmpty()) {
            $this->warn('No check history available');

            return;
        }

        $rows = [];
        foreach ($checks as $check) {
            $statusColor = match ($check->status) {
                'ok' => 'green',
                'warning' => 'yellow',
                'critical' => 'red',
                default => 'gray',
            };

            $rows[] = [
                $check->check_name,
                "<fg={$statusColor}>{$check->status}</>",
                $check->message,
                $check->checked_at->format('Y-m-d H:i:s'),
            ];
        }

        $this->table(['Check', 'Status', 'Message', 'Time'], $rows);
        $this->newLine();
    }

    protected function displayAlerts(): void
    {
        $limit = $this->option('limit');

        $this->line('Unacknowledged Alerts:');
        $this->line(str_repeat('─', 60));

        $alerts = SystemAlert::unacknowledged()
            ->latest()
            ->limit($limit)
            ->get();

        if ($alerts->isEmpty()) {
            $this->info('No unacknowledged alerts');

            return;
        }

        $rows = [];
        foreach ($alerts as $alert) {
            $levelColor = match ($alert->level) {
                'critical' => 'red',
                'warning' => 'yellow',
                'info' => 'blue',
                default => 'gray',
            };

            $message = $alert->message;
            if (strlen($message) > 50) {
                $message = substr($message, 0, 47).'...';
            }

            $rows[] = [
                $alert->id,
                "<fg={$levelColor}>{$alert->level}</>",
                $message,
                $alert->source ?? 'system',
                $alert->created_at->diffForHumans(),
            ];
        }

        $this->table(['ID', 'Level', 'Message', 'Source', 'Time'], $rows);

        $counts = SystemAlert::getUnacknowledgedCounts();
        $this->newLine();
        $this->line("Total unacknowledged: {$counts['total']} (Critical: {$counts['critical']}, Warning: {$counts['warning']})");
        $this->newLine();
    }
}
