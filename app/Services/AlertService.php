<?php

namespace App\Services;

use App\Models\SystemAlert;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AlertService
{
    public function __construct(
        protected MonitorService $monitorService,
    ) {}

    /**
     * Send an alert
     */
    public function send(string $message, string $level = SystemAlert::LEVEL_INFO, array $options = []): SystemAlert
    {
        // Create alert in database
        $alert = SystemAlert::create([
            'level' => $level,
            'message' => $message,
            'source' => $options['source'] ?? null,
            'metadata' => $options['metadata'] ?? null,
            'created_at' => now(),
        ]);

        // Send email for warning and critical alerts
        if (in_array($level, [SystemAlert::LEVEL_WARNING, SystemAlert::LEVEL_CRITICAL])) {
            $this->sendEmail($alert, $options);
        }

        // Log the alert
        $this->logAlert($alert);

        return $alert;
    }

    /**
     * Send an info alert
     */
    public function info(string $message, array $options = []): SystemAlert
    {
        return $this->send($message, SystemAlert::LEVEL_INFO, $options);
    }

    /**
     * Send a warning alert
     */
    public function warning(string $message, array $options = []): SystemAlert
    {
        return $this->send($message, SystemAlert::LEVEL_WARNING, $options);
    }

    /**
     * Send a critical alert
     */
    public function critical(string $message, array $options = []): SystemAlert
    {
        return $this->send($message, SystemAlert::LEVEL_CRITICAL, $options);
    }

    /**
     * Send alert via email
     */
    protected function sendEmail(SystemAlert $alert, array $options): void
    {
        try {
            $recipients = $options['recipients'] ?? $this->getDefaultRecipients();

            if (empty($recipients)) {
                Log::warning('No recipients configured for alerts');

                return;
            }

            $subject = $this->buildEmailSubject($alert);
            $body = $this->buildEmailBody($alert);

            foreach ($recipients as $recipient) {
                Mail::raw($body, function ($message) use ($recipient, $subject, $alert) {
                    $message->to($recipient)
                        ->subject($subject);

                    // Set priority for critical alerts
                    if ($alert->level === SystemAlert::LEVEL_CRITICAL) {
                        $message->priority(1);
                    }
                });
            }

            // Update metadata with email sent status
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
        $logMessage = "[ALERT: {$alert->level}] {$alert->message}";

        match ($alert->level) {
            SystemAlert::LEVEL_CRITICAL => Log::critical($logMessage, [
                'alert_id' => $alert->id,
                'source' => $alert->source,
            ]),
            SystemAlert::LEVEL_WARNING => Log::warning($logMessage, [
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
            SystemAlert::LEVEL_CRITICAL => '[CRITICAL]',
            SystemAlert::LEVEL_WARNING => '[WARNING]',
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
        $appName = config('app.name', 'CEMS-MY');
        $url = config('app.url', 'http://localhost');
        $source = $alert->source ?? 'N/A';
        $level = $alert->level;
        $time = $alert->created_at->format('Y-m-d H:i:s');
        $message = $alert->message;
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
                    $body .= "  {$key}: ".json_encode($value)."\n";
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
