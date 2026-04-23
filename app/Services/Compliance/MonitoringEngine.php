<?php

namespace App\Services\Compliance;

use App\Services\Compliance\Monitors\BaseMonitor;
use App\Services\Compliance\Monitors\CounterfeitAlertMonitor;
use App\Services\Compliance\Monitors\CurrencyFlowMonitor;
use App\Services\Compliance\Monitors\CustomerLocationAnomalyMonitor;
use App\Services\Compliance\Monitors\SanctionsRescreeningMonitor;
use App\Services\Compliance\Monitors\StrDeadlineMonitor;
use App\Services\Compliance\Monitors\StructuringMonitor;
use App\Services\Compliance\Monitors\VelocityMonitor;
use App\Services\MathService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class MonitoringEngine
{
    protected array $monitors = [];

    protected array $defaultMonitors = [
        VelocityMonitor::class,
        StructuringMonitor::class,
        StrDeadlineMonitor::class,
        SanctionsRescreeningMonitor::class,
        CustomerLocationAnomalyMonitor::class,
        CurrencyFlowMonitor::class,
        CounterfeitAlertMonitor::class,
    ];

    protected MathService $mathService;

    protected ComplianceService $complianceService;

    protected array $failureLog = [];

    public function __construct(MathService $mathService, ComplianceService $complianceService)
    {
        $this->mathService = $mathService;
        $this->complianceService = $complianceService;
        $this->registerDefaultMonitors();
    }

    protected function registerDefaultMonitors(): void
    {
        foreach ($this->defaultMonitors as $monitorClass) {
            $this->registerMonitor($monitorClass);
        }
    }

    public function registerMonitor(string $monitorClass): void
    {
        if (! in_array($monitorClass, $this->monitors, true)) {
            $this->monitors[] = $monitorClass;
        }
    }

    public function getRegisteredMonitors(): array
    {
        return $this->monitors;
    }

    public function getMonitor(string $monitorClass): BaseMonitor
    {
        return new $monitorClass($this->mathService, $this->complianceService);
    }

    public function runAll(): Collection
    {
        $results = collect();
        $this->failureLog = [];

        foreach ($this->monitors as $monitorClass) {
            $monitor = $this->getMonitor($monitorClass);
            try {
                $findings = $monitor->execute();
                Log::info("Monitor {$monitorClass} generated ".count($findings).' findings');
                $results = $results->merge($findings);
            } catch (\Throwable $e) {
                $this->handleMonitorFailure($monitorClass, $e);
            }
        }

        if (! empty($this->failureLog)) {
            $this->alertOnMonitorFailures();
        }

        return $results;
    }

    public function runMonitor(string $monitorClass): Collection
    {
        $monitor = $this->getMonitor($monitorClass);
        try {
            $findings = $monitor->execute();
            Log::info("Monitor {$monitorClass} generated ".count($findings).' findings');

            return collect($findings);
        } catch (\Throwable $e) {
            $this->handleMonitorFailure($monitorClass, $e);
            $this->alertOnMonitorFailures();

            return collect();
        }
    }

    protected function handleMonitorFailure(string $monitorClass, \Throwable $e): void
    {
        $errorMessage = "Monitor {$monitorClass} failed: ".$e->getMessage();

        Log::error($errorMessage, [
            'monitor' => $monitorClass,
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);

        $this->failureLog[] = [
            'monitor' => $monitorClass,
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'timestamp' => now()->toDateTimeString(),
        ];
    }

    protected function alertOnMonitorFailures(): void
    {
        if (empty($this->failureLog)) {
            return;
        }

        $failureCount = count($this->failureLog);
        $monitorNames = array_column($this->failureLog, 'monitor');

        Log::critical("{$failureCount} compliance monitor(s) failed", [
            'failures' => $this->failureLog,
            'monitors' => $monitorNames,
        ]);

        try {
            $this->sendFailureNotification($failureCount, $monitorNames);
        } catch (\Throwable $e) {
            Log::error('Failed to send monitor failure notification: '.$e->getMessage());
        }
    }

    protected function sendFailureNotification(int $failureCount, array $monitorNames): void
    {
        Log::channel('audit')->warning('Compliance Monitor Failures', [
            'failure_count' => $failureCount,
            'failed_monitors' => $monitorNames,
            'timestamp' => now()->toDateTimeString(),
            'severity' => 'CRITICAL',
            'requires_action' => true,
        ]);
    }

    public function getFailureLog(): array
    {
        return $this->failureLog;
    }

    public function clearFailureLog(): void
    {
        $this->failureLog = [];
    }
}
