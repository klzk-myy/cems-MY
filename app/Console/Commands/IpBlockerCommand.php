<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\RateLimitService;
use Illuminate\Console\Command;

/**
 * Command to manage IP blocking for security purposes.
 *
 * Provides functionality to:
 * - List blocked IPs
 * - Block specific IPs manually
 * - Unblock IPs
 * - View IP statistics
 * - Clear all blocks
 */
class IpBlockerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'security:ip
                            {action : Action to perform (list, block, unblock, stats, clear, check)}
                            {--ip= : IP address to block/unblock/check}
                            {--duration= : Block duration in minutes (default: from config)}
                            {--reason= : Reason for blocking}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage IP blocking for security purposes';

    public function __construct(
        private RateLimitService $rateLimitService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $action = $this->argument('action');

        return match ($action) {
            'list' => $this->listBlockedIps(),
            'block' => $this->blockIp(),
            'unblock' => $this->unblockIp(),
            'stats' => $this->showStats(),
            'clear' => $this->clearAllBlocks(),
            'check' => $this->checkIp(),
            default => $this->error("Unknown action: {$action}") ?? self::FAILURE,
        };
    }

    /**
     * List all blocked IPs.
     */
    private function listBlockedIps(): int
    {
        $blocked = $this->rateLimitService->getBlockedIps();

        if (empty($blocked)) {
            $this->info('No IPs are currently blocked.');

            return self::SUCCESS;
        }

        $this->info('Currently Blocked IPs:');
        $this->newLine();

        $rows = [];
        foreach ($blocked as $info) {
            $rows[] = [
                $info['ip'],
                $info['blocked_at'],
                $info['duration_minutes'].' min',
                $info['expires_at'],
                $info['block_count'],
            ];
        }

        $this->table(
            ['IP Address', 'Blocked At', 'Duration', 'Expires At', 'Block Count'],
            $rows
        );

        $this->info('Total blocked IPs: '.count($blocked));

        return self::SUCCESS;
    }

    /**
     * Block a specific IP address.
     */
    private function blockIp(): int
    {
        $ip = $this->option('ip');

        if (! $ip) {
            $ip = $this->ask('Enter IP address to block');
        }

        if (! $this->isValidIpOrCidr($ip)) {
            $this->error("Invalid IP address or CIDR: {$ip}");

            return self::FAILURE;
        }

        if ($this->rateLimitService->isIpBlocked($ip)) {
            $this->warn("IP {$ip} is already blocked.");
            $info = $this->rateLimitService->getIpBlockInfo($ip);
            $this->info("Expires at: {$info['expires_at']}");

            return self::SUCCESS;
        }

        $duration = $this->option('duration');
        if (! $duration) {
            $duration = $this->ask(
                'Block duration in minutes (default: '.config('security.ip_blocking.block_duration_minutes', 60).')',
                config('security.ip_blocking.block_duration_minutes', 60)
            );
        }

        $reason = $this->option('reason') ?? 'Manual block via CLI';

        if (! $this->confirm("Are you sure you want to block {$ip} for {$duration} minutes?", true)) {
            $this->info('Operation cancelled.');

            return self::SUCCESS;
        }

        $this->rateLimitService->blockIp($ip, (int) $duration);

        $this->info("IP {$ip} has been blocked for {$duration} minutes.");
        $this->info("Reason: {$reason}");

        // Log the action
        \Illuminate\Support\Facades\Log::info('IP manually blocked via CLI', [
            'ip' => $ip,
            'duration' => $duration,
            'reason' => $reason,
            'blocked_by' => 'console',
        ]);

        return self::SUCCESS;
    }

    /**
     * Unblock a specific IP address.
     */
    private function unblockIp(): int
    {
        $ip = $this->option('ip');

        if (! $ip) {
            $ip = $this->ask('Enter IP address to unblock');
        }

        if (! $this->isValidIpOrCidr($ip)) {
            $this->error("Invalid IP address or CIDR: {$ip}");

            return self::FAILURE;
        }

        if (! $this->rateLimitService->isIpBlocked($ip)) {
            $this->warn("IP {$ip} is not currently blocked.");

            return self::SUCCESS;
        }

        if ($this->rateLimitService->unblockIp($ip)) {
            $this->info("IP {$ip} has been unblocked.");

            // Log the action
            \Illuminate\Support\Facades\Log::info('IP manually unblocked via CLI', [
                'ip' => $ip,
                'unblocked_by' => 'console',
            ]);

            return self::SUCCESS;
        }

        $this->error("Failed to unblock IP {$ip}.");

        return self::FAILURE;
    }

    /**
     * Show rate limit statistics.
     */
    private function showStats(): int
    {
        $ip = $this->option('ip');

        if ($ip) {
            // Show stats for specific IP
            if (! $this->isValidIpOrCidr($ip)) {
                $this->error("Invalid IP address or CIDR: {$ip}");

                return self::FAILURE;
            }

            $stats = $this->rateLimitService->getRateLimitStats($ip);

            $this->info("Rate Limit Statistics for {$ip}:");
            $this->newLine();
            $this->info('Is Blocked: '.($stats['is_blocked'] ? 'YES' : 'No'));
            $this->info('Failed Attempts: '.$stats['failed_attempts']);
            $this->info('Total Rate Limit Hits: '.$stats['total_hits']);

            if (! empty($stats['recent_hits'])) {
                $this->newLine();
                $this->info('Recent Rate Limit Hits:');
                $rows = [];
                foreach ($stats['recent_hits'] as $hit) {
                    $rows[] = [
                        $hit['timestamp'],
                        $hit['limiter'],
                        $hit['user_id'] ?? 'Anonymous',
                    ];
                }
                $this->table(['Timestamp', 'Limiter', 'User ID'], $rows);
            }
        } else {
            // Show overall stats
            $overall = $this->rateLimitService->getOverallStats();

            $this->info('Overall Rate Limiting Statistics:');
            $this->newLine();
            $this->info("Blocked IPs Count: {$overall['blocked_ips_count']}");

            if (! empty($overall['blocked_ips'])) {
                $this->newLine();
                $this->info('Blocked IPs Details:');
                $rows = [];
                foreach ($overall['blocked_ips'] as $info) {
                    $rows[] = [
                        $info['ip'],
                        $info['blocked_at'],
                        $info['expires_at'],
                    ];
                }
                $this->table(['IP Address', 'Blocked At', 'Expires At'], $rows);
            }
        }

        return self::SUCCESS;
    }

    /**
     * Clear all IP blocks.
     */
    private function clearAllBlocks(): int
    {
        $blocked = $this->rateLimitService->getBlockedIps();

        if (empty($blocked)) {
            $this->info('No IPs are currently blocked.');

            return self::SUCCESS;
        }

        $count = count($blocked);

        if (! $this->confirm("Are you sure you want to unblock all {$count} blocked IPs?", false)) {
            $this->info('Operation cancelled.');

            return self::SUCCESS;
        }

        foreach ($blocked as $info) {
            $this->rateLimitService->unblockIp($info['ip']);
        }

        $this->info("{$count} IP(s) have been unblocked.");

        // Log the action
        \Illuminate\Support\Facades\Log::info('All IPs unblocked via CLI', [
            'count' => $count,
            'unblocked_by' => 'console',
        ]);

        return self::SUCCESS;
    }

    /**
     * Check if an IP is blocked.
     */
    private function checkIp(): int
    {
        $ip = $this->option('ip');

        if (! $ip) {
            $ip = $this->ask('Enter IP address to check');
        }

        if (! $this->isValidIpOrCidr($ip)) {
            $this->error("Invalid IP address or CIDR: {$ip}");

            return self::FAILURE;
        }

        if ($this->rateLimitService->isIpBlocked($ip)) {
            $info = $this->rateLimitService->getIpBlockInfo($ip);
            $this->error("IP {$ip} is BLOCKED");
            $this->info("Blocked at: {$info['blocked_at']}");
            $this->info("Expires at: {$info['expires_at']}");
            $this->info("Duration: {$info['duration_minutes']} minutes");
            $this->info("Block count: {$info['block_count']}");

            return self::SUCCESS;
        }

        $this->info("IP {$ip} is NOT blocked.");

        $failedAttempts = $this->rateLimitService->getFailedAttempts($ip);
        if ($failedAttempts > 0) {
            $this->warn("Failed login attempts: {$failedAttempts}");
            $threshold = config('security.ip_blocking.failed_attempts_threshold', 10);
            $this->info("Block threshold: {$threshold}");
        }

        return self::SUCCESS;
    }

    /**
     * Validate IP address or CIDR notation.
     */
    private function isValidIpOrCidr(?string $value): bool
    {
        if (empty($value)) {
            return false;
        }

        // Check if CIDR notation
        if (str_contains($value, '/')) {
            $parts = explode('/', $value);
            if (count($parts) !== 2) {
                return false;
            }
            [$ip, $mask] = $parts;
            if (! filter_var($ip, FILTER_VALIDATE_IP)) {
                return false;
            }
            $maskInt = (int) $mask;
            if ($maskInt < 0 || $maskInt > 32) {
                return false;
            }

            return true;
        }

        return filter_var($value, FILTER_VALIDATE_IP) !== false;
    }
}
