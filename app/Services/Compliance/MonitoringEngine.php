<?php

namespace App\Services\Compliance;

use App\Enums\UserRole;
use App\Models\SystemAlert;
use App\Models\User;
use App\Notifications\SystemHealthAlertNotification;
use App\Services\Compliance\Monitors\BaseMonitor;
use App\Services\Compliance\Monitors\CounterfeitAlertMonitor;
use App\Services\Compliance\Monitors\CurrencyFlowMonitor;
use App\Services\Compliance\Monitors\CustomerLocationAnomalyMonitor;
use App\Services\Compliance\Monitors\SanctionsRescreeningMonitor;
use App\Services\Compliance\Monitors\StructuringMonitor;
use App\Services\Compliance\Monitors\VelocityMonitor;
use App\Services\System\MathService;
use App\Services\System\SystemAlertService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class MonitoringEngine
{
    protected array $monitors = [];

    protected array $defaultMonitors = [
        VelocityMonitor::class,
        StructuringMonitor::class,
        SanctionsRescreeningMonitor::class,
        CustomerLocationAnomalyMonitor::class,
        CurrencyFlowMonitor::class,
        CounterfeitAlertMonitor::class,
    ];

    protected MathService $mathService;

    protected ComplianceService $complianceService;

    protected SystemAlertService $alertService;

    protected array $failureLog = [];

    /**
     * In-memory dedup so a monitor that fails repeatedly only raises one
     * Critical alert per engine instance (one run-cycle in scheduled usage),
     * mirroring the BaseMonitor finding-dedup philosophy.
     *
     * @var array<string, true>
     */
    protected array $alertedMonitors = [];

    protected int $consecutiveFailures = 0;

    protected const CIRCUIT_BREAKER_THRESHOLD = 3;

    protected const CIRCUIT_BREAKER_RESET_AFTER = 60; // seconds

    protected ?int $circuitBrokenAt = null;

    public function __construct(MathService $mathService, ComplianceService $complianceService, SystemAlertService $alertService)
    {
        $this->mathService = $mathService;
        $this->complianceService = $complianceService;
        $this->alertService = $alertService;
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
        // Resolve via the container so each monitor receives its own declared
        // dependencies. Constructing with a fixed ($mathService, $complianceService)
        // argument list throws ArgumentCountError/TypeError for monitors with
        // 1-arg or 3-arg constructors (SanctionsRescreening, CustomerLocation,
        // Velocity, Structuring, ...), silently disabling the whole sweep.
        $monitor = app()->make($monitorClass);

        if (! $monitor instanceof BaseMonitor) {
            throw new \UnexpectedValueException("{$monitorClass} is not a BaseMonitor");
        }

        return $monitor;
    }

    protected function isCircuitBroken(): bool
    {
        if ($this->consecutiveFailures < self::CIRCUIT_BREAKER_THRESHOLD) {
            return false;
        }

        // Check if we should reset the circuit breaker
        if ($this->circuitBrokenAt !== null) {
            $elapsed = time() - $this->circuitBrokenAt;
            if ($elapsed >= self::CIRCUIT_BREAKER_RESET_AFTER) {
                // Reset after cooldown period
                $this->consecutiveFailures = 0;
                $this->circuitBrokenAt = null;
                Log::info('MonitoringEngine circuit breaker reset after cooldown');

                return false;
            }
        }

        return true;
    }

    protected function recordFailure(): void
    {
        $this->consecutiveFailures++;
        if ($this->consecutiveFailures >= self::CIRCUIT_BREAKER_THRESHOLD) {
            $this->circuitBrokenAt = time();
            Log::critical('MonitoringEngine circuit breaker triggered - too many consecutive monitor failures');
        }
    }

    protected function recordSuccess(): void
    {
        $this->consecutiveFailures = 0;
        $this->circuitBrokenAt = null;
    }

    public function runAll(): Collection
    {
        $results = collect();
        $this->failureLog = [];

        // Check circuit breaker before running monitors
        if ($this->isCircuitBroken()) {
            Log::warning('MonitoringEngine circuit breaker is open, skipping all monitors', [
                'consecutive_failures' => $this->consecutiveFailures,
                'broken_at' => $this->circuitBrokenAt,
            ]);

            return $results;
        }

        foreach ($this->monitors as $monitorClass) {
            $monitor = $this->getMonitor($monitorClass);
            try {
                $findings = $monitor->execute();
                $this->recordSuccess();
                Log::info("Monitor {$monitorClass} generated ".count($findings).' findings');
                $results = $results->merge($findings);
            } catch (\Throwable $e) {
                $this->recordFailure();
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

    /**
     * @param  array<int, string>  $monitorNames
     */
    protected function sendFailureNotification(int $failureCount, array $monitorNames): void
    {
        Log::channel('audit')->warning('Compliance Monitor Failures', [
            'failure_count' => $failureCount,
            'failed_monitors' => $monitorNames,
            'timestamp' => now()->toDateTimeString(),
            'severity' => 'CRITICAL',
            'requires_action' => true,
        ]);

        $errorsByMonitor = [];
        foreach ($this->failureLog as $failure) {
            $errorsByMonitor[$failure['monitor']][] = [
                'exception' => $failure['exception'] ?? null,
                'message' => $failure['message'] ?? null,
            ];
        }

        $newlyAlerted = [];
        $raisedAlert = null;

        foreach ($monitorNames as $monitorName) {
            if (isset($this->alertedMonitors[$monitorName])) {
                continue;
            }

            $this->alertedMonitors[$monitorName] = true;
            $newlyAlerted[] = $monitorName;

            try {
                $alert = $this->alertService->critical(
                    "Compliance monitor failed: {$monitorName}",
                    [
                        'source' => 'compliance_monitor',
                        'metadata' => [
                            'monitor' => $monitorName,
                            'errors' => $errorsByMonitor[$monitorName] ?? [],
                            'failure_count' => $failureCount,
                        ],
                    ]
                );
                $raisedAlert ??= $alert;
            } catch (\Throwable $e) {
                Log::error("Failed to raise system alert for monitor {$monitorName}: ".$e->getMessage());
            }
        }

        if ($newlyAlerted === [] || $raisedAlert === null) {
            return;
        }

        $this->notifyOfficersOfMonitorFailure($raisedAlert, $newlyAlerted);
    }

    /**
     * Escalate engine failures to compliance officers so a silent monitoring
     * outage is not only visible in logs.
     *
     * @param  array<int, string>  $monitorNames
     */
    protected function notifyOfficersOfMonitorFailure(SystemAlert $systemAlert, array $monitorNames): void
    {
        try {
            $officers = User::query()
                ->whereIn('role', [UserRole::ComplianceOfficer->value, UserRole::Manager->value])
                ->where('is_active', true)
                ->get();

            foreach ($officers as $officer) {
                try {
                    $officer->notify(new SystemHealthAlertNotification($systemAlert));
                } catch (\Throwable $e) {
                    Log::warning('Failed to notify officer of monitor failure', [
                        'officer_id' => $officer->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('Failed to resolve officers for monitor failure notification: '.$e->getMessage());
        }
    }

    public function getFailureLog(): array
    {
        return $this->failureLog;
    }

    public function clearFailureLog(): void
    {
        $this->failureLog = [];
        $this->alertedMonitors = [];
    }
}
