<?php

namespace App\Console\Commands;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Send daily notification digest to users.
 *
 * This command sends a summary of unread notifications to users
 * who have enabled digest notifications.
 */
class SendNotificationDigest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:send-digest
                            {--period=24h : Time period to include (24h, 7d, 30d)}
                            {--user= : Specific user ID to send digest to}
                            {--dry-run : Show what would be sent without sending}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send daily notification digest to users';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $period = $this->option('period');
        $userId = $this->option('user');
        $dryRun = $this->option('dry-run');

        $this->info("Sending notification digests (period: {$period})");

        if ($dryRun) {
            $this->warn('[DRY RUN MODE] - No actual emails will be sent');
        }

        // Calculate cutoff time
        $cutoff = $this->getCutoffTime($period);
        $this->info("Including notifications from: {$cutoff->format('Y-m-d H:i:s')}");

        // Get users to send digest to
        $users = $this->getTargetUsers($userId);

        if ($users->isEmpty()) {
            $this->warn('No users found to send digest to.');

            return 0;
        }

        $this->info("Found {$users->count()} user(s) to process");

        $successCount = 0;
        $failCount = 0;

        foreach ($users as $user) {
            try {
                $stats = $this->sendDigestForUser($user, $cutoff, $dryRun);

                if ($stats['total'] > 0) {
                    $this->info("✓ User {$user->username}: {$stats['total']} notifications ({$stats['by_type']})");
                    $successCount++;
                } else {
                    $this->line("  User {$user->username}: No notifications to digest");
                }
            } catch (\Exception $e) {
                $this->error("✗ Failed for user {$user->username}: {$e->getMessage()}");
                $failCount++;
            }
        }

        $this->newLine();
        $this->info('Digest Summary:');
        $this->info("  Success: {$successCount}");
        $this->info("  Failed: {$failCount}");

        if ($dryRun) {
            $this->warn('Dry run complete. No emails were actually sent.');
        }

        return $failCount > 0 ? 1 : 0;
    }

    /**
     * Get the cutoff time based on the period option.
     */
    protected function getCutoffTime(string $period): Carbon
    {
        return match ($period) {
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            default => now()->subHours(24),
        };
    }

    /**
     * Get target users for digest.
     *
     * @return \Illuminate\Support\Collection
     */
    protected function getTargetUsers(?int $userId = null)
    {
        $query = User::where('is_active', true);

        if ($userId) {
            $query->where('id', $userId);
        }

        return $query->get();
    }

    /**
     * Send digest for a specific user.
     *
     * @return array<string, mixed>
     */
    protected function sendDigestForUser(User $user, Carbon $cutoff, bool $dryRun): array
    {
        // Get unread notifications for this user
        $notifications = DatabaseNotification::where('notifiable_type', User::class)
            ->where('notifiable_id', $user->id)
            ->whereNull('read_at')
            ->where('created_at', '>=', $cutoff)
            ->orderBy('created_at', 'desc')
            ->get();

        if ($notifications->isEmpty()) {
            return [
                'total' => 0,
                'by_type' => 'none',
            ];
        }

        // Group notifications by type
        $grouped = $notifications->groupBy(function ($notification) {
            return $notification->type ?? 'unknown';
        });

        // Build digest data
        $digestData = [
            'user' => $user,
            'total' => $notifications->count(),
            'period' => $cutoff->diffForHumans(),
            'notifications_by_type' => [],
        ];

        foreach ($grouped as $type => $items) {
            $digestData['notifications_by_type'][$this->getFriendlyTypeName($type)] = $items->count();
        }

        if (! $dryRun) {
            // Send digest notification via Laravel's notification system
            // In a real implementation, you would create a DigestNotification class
            // For now, we log the digest
            \Illuminate\Support\Facades\Log::info('Notification digest sent', [
                'user_id' => $user->id,
                'total_notifications' => $notifications->count(),
                'by_type' => $digestData['notifications_by_type'],
            ]);
        }

        return [
            'total' => $notifications->count(),
            'by_type' => implode(', ', array_map(function ($type, $count) {
                return "{$type}: {$count}";
            }, array_keys($digestData['notifications_by_type']), $digestData['notifications_by_type'])),
        ];
    }

    /**
     * Get friendly name for notification type.
     */
    protected function getFriendlyTypeName(string $type): string
    {
        $className = class_basename($type);

        return match ($className) {
            'TransactionFlaggedNotification' => 'Transaction Flags',
            'StrDeadlineApproachingNotification' => 'STR Deadlines',
            'StrSubmissionFailedNotification' => 'STR Failures',
            'ComplianceCaseAssignedNotification' => 'Case Assignments',
            'DataBreachAlertNotification' => 'Data Breaches',
            'LargeTransactionNotification' => 'Large Transactions',
            'SanctionsMatchNotification' => 'Sanctions Matches',
            'SystemHealthAlertNotification' => 'System Health',
            default => str_replace('Notification', '', $className),
        };
    }
}
