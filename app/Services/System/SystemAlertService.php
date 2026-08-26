<?php

namespace App\Services\System;

use App\Enums\SystemAlertLevel;
use App\Models\SystemAlert;
use App\Notifications\SystemAlertNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SystemAlertService
{
    public function __construct(
        protected SystemHealthService $monitorService,
    ) {}

    /**
     * Send an alert
     */
    public function send(string $message, string $level = SystemAlertLevel::Info->value, array $options = []): SystemAlert
    {
        return DB::transaction(function () use ($message, $level, $options) {
            $alert = SystemAlert::create([
                'level' => $level,
                'message' => $message,
                'source' => $options['source'] ?? null,
                'metadata' => $options['metadata'] ?? null,
                'created_at' => now(),
            ]);

            if (in_array($level, [SystemAlertLevel::Warning->value, SystemAlertLevel::Critical->value])) {
                $this->sendEmail($alert, $options);
            }

            $this->logAlert($alert);

            return $alert;
        });
    }

    /**
     * Send an info alert
     */
    public function info(string $message, array $options = []): SystemAlert
    {
        return $this->send($message, SystemAlertLevel::Info->value, $options);
    }

    /**
     * Send a warning alert
     */
    public function warning(string $message, array $options = []): SystemAlert
    {
        return $this->send($message, SystemAlertLevel::Warning->value, $options);
    }

    /**
     * Send a critical alert
     */
    public function critical(string $message, array $options = []): SystemAlert
    {
        return $this->send($message, SystemAlertLevel::Critical->value, $options);
    }

    /**
     * Send alert via email
     */
    protected function sendEmail(SystemAlert $alert, array $options): void
    {
        try {
            $recipients = $options['recipients'] ?? $this->getDefaultRecipients();

            // Defensive: strip empty entries so a blank SYSTEM_ALERT_RECIPIENTS
            // or caller-supplied empties never mail a blank address.
            $recipients = array_values(array_filter(
                (array) $recipients,
                fn ($recipient) => is_string($recipient) && trim($recipient) !== ''
            ));

            if (empty($recipients)) {
                Log::warning('No recipients configured for alerts');

                return;
            }

            $subject = $this->buildEmailSubject($alert);
            $body = $this->buildEmailBody($alert);

            NotificationDispatcher::dispatchToAll(
                collect($recipients),
                new SystemAlertNotification($alert),
                ['mail']
            );

            $metadata = $alert->metadata ?? [];
            $metadata['email_sent'] = true;
            $metadata['email_sent_at'] = now()->toIso8601String();
            $metadata['email_recipients'] = $recipients;
            $alert->update(['metadata' => $metadata]);

        } catch (\Exception $e) {
            Log::error('Failed to send alert email: '.$e->getMessage());

            // Update metadata with failure
            $metadata = $alert->metadata ?? [];
            $metadata['email_sent'] = false;
            $metadata['email_error'] = $e->getMessage();
            $alert->update(['metadata' => $metadata]);
        }
    }

    /**
     * Log alert to application log
     */
    protected function logAlert(SystemAlert $alert): void
    {
        $logMessage = '[ALERT: '.$alert->level->value.'] '.$alert->message;

        match ($alert->level) {
            SystemAlertLevel::Critical => Log::critical($logMessage, [
                'alert_id' => $alert->id,
                'source' => $alert->source,
            ]),
            SystemAlertLevel::Warning => Log::warning($logMessage, [
                'alert_id' => $alert->id,
                'source' => $alert->source,
            ]),
            default => Log::info($logMessage, [
                'alert_id' => $alert->id,
                'source' => $alert->source,
            ]),
        };
    }

    /**
     * Get default email recipients from config
     */
    protected function getDefaultRecipients(): array
    {
        $recipients = config('monitoring.alert_recipients');

        if (is_string($recipients)) {
            return [$recipients];
        }

        return $recipients ?? [];
    }

    /**
     * Build email subject
     */
    protected function buildEmailSubject(SystemAlert $alert): string
    {
        $prefix = match ($alert->level) {
            SystemAlertLevel::Critical => '[CRITICAL]',
            SystemAlertLevel::Warning => '[WARNING]',
            default => '[INFO]',
        };

        $appName = config('app.name', 'CEMS-MY');

        return "{$prefix} {$appName} System Alert";
    }

    /**
     * Build email body
     */
    protected function buildEmailBody(SystemAlert $alert): string
    {
        $appName = htmlspecialchars(config('app.name', 'CEMS-MY'), ENT_QUOTES, 'UTF-8');
        $url = htmlspecialchars(config('app.url', 'http://localhost'), ENT_QUOTES, 'UTF-8');
        $source = htmlspecialchars($alert->source ?? 'N/A', ENT_QUOTES, 'UTF-8');
        $level = htmlspecialchars($alert->level->value, ENT_QUOTES, 'UTF-8');
        $time = $alert->created_at->format('Y-m-d H:i:s');
        $message = htmlspecialchars($alert->message, ENT_QUOTES, 'UTF-8');
        $alertId = $alert->id;

        $body = <<<EOT
System Alert from {$appName}
========================================

Level: {$level}
Time: {$time}
Source: {$source}

Message:
{$message}

EOT;

        if (! empty($alert->metadata)) {
            $body .= "\nDetails:\n";
            foreach ($alert->metadata as $key => $value) {
                if (! in_array($key, ['email_sent', 'email_sent_at', 'email_recipients'])) {
                    $encoded = json_encode($value);
                    $body .= '  '.htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8').': '.htmlspecialchars($encoded === false ? '' : $encoded, ENT_QUOTES, 'UTF-8')."\n";
                }
            }
        }

        $body .= <<<EOT


========================================
View alerts: {$url}/system/alerts
Acknowledge: {$url}/system/alerts/{$alertId}/acknowledge
EOT;

        return $body;
    }

    /**
     * Acknowledge an alert
     */
    public function acknowledge(int $alertId, int $userId): bool
    {
        $alert = SystemAlert::find($alertId);

        if (! $alert) {
            return false;
        }

        $alert->acknowledge($userId);

        return true;
    }

    /**
     * Get unacknowledged alerts count
     */
    public function getUnacknowledgedCounts(): array
    {
        return SystemAlert::getUnacknowledgedCounts();
    }

    /**
     * Get recent unacknowledged alerts
     */
    public function getRecentUnacknowledged(int $limit = 10): array
    {
        return SystemAlert::getRecentUnacknowledged($limit);
    }

    /**
     * Send daily summary report
     */
    public function sendDailySummary(): ?SystemAlert
    {
        $status = $this->monitorService->getStatusSummary();

        $counts = SystemAlert::getUnacknowledgedCounts();
        $yesterdayAlerts = SystemAlert::betweenDates(
            now()->subDay()->format('Y-m-d'),
            now()->format('Y-m-d')
        )->count();

        $lastCheck = $status['last_check']?->format('Y-m-d H:i:s') ?? 'Never';

        $message = <<<EOT
Daily System Health Summary

Overall Status: {$status['overall_status']}

Health Checks:
- OK: {$status['summary']['ok']}
- Warning: {$status['summary']['warning']}
- Critical: {$status['summary']['critical']}
- Unknown: {$status['summary']['unknown']}

Alerts (Last 24h):
- Total alerts: {$yesterdayAlerts}
- Unacknowledged: {$counts['total']}
  - Critical: {$counts['critical']}
  - Warning: {$counts['warning']}
  - Info: {$counts['info']}

Last Check: {$lastCheck}
EOT;

        return $this->info($message, [
            'source' => 'daily_summary',
            'metadata' => [
                'health_status' => $status['overall_status'],
                'check_summary' => $status['summary'],
                'alert_counts' => $counts,
            ],
        ]);
    }

    /**
     * Cleanup old acknowledged alerts
     */
    public function cleanupOldAlerts(int $days = 30): int
    {
        $cutoff = now()->subDays($days);

        $deleted = SystemAlert::acknowledged()
            ->where('created_at', '<', $cutoff)
            ->delete();

        Log::info("Cleaned up {$deleted} old alerts (older than {$days} days)");

        return $deleted;
    }

    /**
     * Get alerts for dashboard widget
     */
    public function getDashboardWidgetData(): array
    {
        $counts = SystemAlert::getUnacknowledgedCounts();
        $recent = SystemAlert::unacknowledged()
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($alert) {
                return [
                    'id' => $alert->id,
                    'level' => $alert->level,
                    'message' => $alert->message,
                    'source' => $alert->source,
                    'created_at' => $alert->created_at->diffForHumans(),
                ];
            })
            ->toArray();

        return [
            'counts' => $counts,
            'recent' => $recent,
            'has_critical' => $counts['critical'] > 0,
            'has_warnings' => $counts['warning'] > 0,
        ];
    }
}
