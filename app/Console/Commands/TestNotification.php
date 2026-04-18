<?php

namespace App\Console\Commands;

use App\Models\Compliance\ComplianceCase;
use App\Models\DataBreachAlert;
use App\Models\SanctionEntry;
use App\Models\StrReport;
use App\Models\SystemAlert;
use App\Models\Transaction;
use App\Models\TransactionConfirmation;
use App\Models\User;
use App\Notifications\ComplianceCaseAssignedNotification;
use App\Notifications\DataBreachAlertNotification;
use App\Notifications\LargeTransactionNotification;
use App\Notifications\SanctionsMatchNotification;
use App\Notifications\StrDeadlineApproachingNotification;
use App\Notifications\StrSubmissionFailedNotification;
use App\Notifications\SystemHealthAlertNotification;
use App\Notifications\TransactionFlaggedNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Test notification delivery for CEMS-MY notification system.
 */
class TestNotification extends Command
{
    protected $signature = 'notifications:test
                            {type : Notification type to test (transaction_flagged, str_deadline, str_failed, case_assigned, data_breach, large_transaction, sanctions_match, system_health, all)}
                            {--user= : User ID to send test notification to}
                            {--channel= : Specific channel to test}
                            {--dry-run : Preview notification without sending}';

    protected $description = 'Test notification delivery';

    protected array $notificationTypes = [
        'transaction_flagged' => 'Transaction Flagged',
        'str_deadline' => 'STR Deadline Approaching',
        'str_failed' => 'STR Submission Failed',
        'case_assigned' => 'Compliance Case Assigned',
        'data_breach' => 'Data Breach Alert',
        'large_transaction' => 'Large Transaction',
        'sanctions_match' => 'Sanctions Match',
        'system_health' => 'System Health Alert',
    ];

    public function handle(): int
    {
        $type = $this->argument('type');
        $userId = $this->option('user');
        $channel = $this->option('channel');
        $dryRun = $this->option('dry-run');

        if ($type !== 'all' && ! array_key_exists($type, $this->notificationTypes)) {
            $this->error("Invalid notification type: {$type}");
            $this->info('Available types: '.implode(', ', array_keys($this->notificationTypes)));

            return 1;
        }

        $user = $this->getTargetUser($userId);

        if (! $user) {
            $this->error('No target user found.');

            return 1;
        }

        $this->info("Testing notifications for user: {$user->username} (ID: {$user->id})");

        if ($dryRun) {
            $this->warn('[DRY RUN MODE]');
        }

        $results = $type === 'all'
            ? $this->sendAllNotifications($user, $channel, $dryRun)
            : [$this->sendNotification($type, $user, $channel, $dryRun)];

        $this->displayResults($results);

        return count(array_filter($results, fn ($r) => ! $r['success'])) > 0 ? 1 : 0;
    }

    protected function getTargetUser(?int $userId): ?User
    {
        if ($userId) {
            return User::find($userId);
        }

        return User::where('is_active', true)->first();
    }

    protected function sendAllNotifications(User $user, ?string $channel, bool $dryRun): array
    {
        $results = [];
        foreach (array_keys($this->notificationTypes) as $type) {
            $results[] = $this->sendNotification($type, $user, $channel, $dryRun);
        }

        return $results;
    }

    protected function sendNotification(string $type, User $user, ?string $channel, bool $dryRun): array
    {
        $result = ['type' => $type, 'success' => false, 'message' => ''];

        try {
            $notification = $this->createNotification($type);

            if ($dryRun) {
                $result['success'] = true;
                $result['message'] = 'Preview generated';
                $result['channels'] = $notification->via($user);
            } else {
                $user->notify($notification);
                $result['success'] = true;
                $result['message'] = 'Sent successfully';
                $result['channels'] = $notification->via($user);
            }
        } catch (\Exception $e) {
            $result['message'] = $e->getMessage();
        }

        return $result;
    }

    protected function createNotification(string $type): object
    {
        return match ($type) {
            'transaction_flagged' => $this->createTransactionFlaggedNotification(),
            'str_deadline' => $this->createStrDeadlineNotification(),
            'str_failed' => $this->createStrFailedNotification(),
            'case_assigned' => $this->createCaseAssignedNotification(),
            'data_breach' => $this->createDataBreachNotification(),
            'large_transaction' => $this->createLargeTransactionNotification(),
            'sanctions_match' => $this->createSanctionsMatchNotification(),
            'system_health' => $this->createSystemHealthNotification(),
            default => throw new \InvalidArgumentException("Unknown type: {$type}"),
        };
    }

    protected function createTransactionFlaggedNotification(): TransactionFlaggedNotification
    {
        return new TransactionFlaggedNotification(
            \App\Models\FlaggedTransaction::factory()->make([
                'id' => 999999,
                'flag_type' => \App\Enums\ComplianceFlagType::Velocity,
                'flag_reason' => 'Test: Multiple transactions detected',
                'status' => \App\Enums\FlagStatus::Open,
            ])
        );
    }

    protected function createStrDeadlineNotification(): StrDeadlineApproachingNotification
    {
        return new StrDeadlineApproachingNotification(
            StrReport::factory()->make([
                'id' => 999999,
                'status' => \App\Enums\StrStatus::Draft,
                'filing_deadline' => now()->addDay(),
            ]),
            1
        );
    }

    protected function createStrFailedNotification(): StrSubmissionFailedNotification
    {
        return new StrSubmissionFailedNotification(
            StrReport::factory()->make([
                'id' => 999999,
                'status' => \App\Enums\StrStatus::Failed,
            ]),
            'Connection timeout',
            2
        );
    }

    protected function createCaseAssignedNotification(): ComplianceCaseAssignedNotification
    {
        return new ComplianceCaseAssignedNotification(
            ComplianceCase::factory()->make([
                'id' => 999999,
                'case_number' => 'CASE-2024-00001',
            ])
        );
    }

    protected function createDataBreachNotification(): DataBreachAlertNotification
    {
        return new DataBreachAlertNotification(
            DataBreachAlert::factory()->make(['id' => 999999])
        );
    }

    protected function createLargeTransactionNotification(): LargeTransactionNotification
    {
        return new LargeTransactionNotification(
            Transaction::factory()->make(['id' => 999999, 'amount' => 75000]),
            TransactionConfirmation::factory()->make(['id' => 999999])
        );
    }

    protected function createSanctionsMatchNotification(): SanctionsMatchNotification
    {
        return new SanctionsMatchNotification(
            SanctionEntry::factory()->make([
                'id' => 999999,
                'match_score' => 95,
                'is_whitelisted' => false,
            ]),
            'Exact name match'
        );
    }

    protected function createSystemHealthNotification(): SystemHealthAlertNotification
    {
        return new SystemHealthAlertNotification(
            SystemAlert::factory()->make([
                'id' => 999999,
                'level' => 'warning',
            ])
        );
    }

    protected function displayResults(array $results): void
    {
        $this->newLine();
        $this->info('Test Results:');
        $this->info(str_repeat('=', 70));

        foreach ($results as $result) {
            $label = $this->notificationTypes[$result['type']] ?? $result['type'];
            $icon = $result['success'] ? '✓' : '✗';
            $this->line("{$icon} {$label}: {$result['message']}");
        }

        $successCount = count(array_filter($results, fn ($r) => $r['success']));
        $this->info(str_repeat('=', 70));
        $this->info("Summary: {$successCount}/".count($results).' tests passed');
    }
}
