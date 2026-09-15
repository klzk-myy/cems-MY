<?php

namespace App\Services\System;

use App\Enums\Permission;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;

/**
 * Feeds the header notification bell.
 *
 * Centralizes the queries shared by the NotificationComposer (page render)
 * and NotificationController::unreadCount (live polling) so the bell shows the
 * same data everywhere. The DLQ count is admin-only and cached under the
 * dashboard/transactions tags for 60s, matching the dashboard widget, so a
 * DLQ retry/purge (which invalidates those tags) refreshes it immediately.
 */
class NotificationBadgeService
{
    public function __construct(
        protected CacheOptimizationService $cacheOptimizationService,
    ) {}

    /**
     * Count of unread in-app notifications for the user.
     */
    public function unreadCount(User $user): int
    {
        return $user->unreadNotifications()->count();
    }

    /**
     * Summarized unread notifications for the bell dropdown.
     *
     * @return array<int, array{id: string, title: string, message: string, url: string|null, time: string}>
     */
    public function unreadList(User $user, int $limit = 8): array
    {
        return $user->unreadNotifications()
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (DatabaseNotification $notification) => $this->summarize($notification))
            ->values()
            ->all();
    }

    /**
     * DLQ count for the header badge. Non-admins always get 0 so operational
     * failure counts are never leaked to branch-level staff.
     */
    public function dlqCount(?User $user): int
    {
        if (! $user?->role->canPerform(Permission::ManageDlq)) {
            return 0;
        }

        return (int) $this->cacheOptimizationService->remember(
            'header.dlq_count',
            60,
            ['dashboard', 'transactions'],
            fn () => Transaction::where('is_dlq', true)->count()
        );
    }

    /**
     * Map a stored notification to a display shape for the dropdown.
     *
     * Notification data arrays differ per type; the summarizer picks the
     * fields every type shares (type/url/message) and falls back to a readable
     * headline of the type key so unknown notifications still render.
     *
     * @return array{id: string, title: string, message: string, url: string|null, time: string}
     */
    protected function summarize(DatabaseNotification $notification): array
    {
        $data = $notification->data;
        $type = $data['type'] ?? 'notification';

        $title = match ($type) {
            'transaction_dlq' => 'Dead letter queue alert',
            'transaction_flagged' => 'Transaction flagged',
            'system_health_alert' => $data['level_label'] ?? 'System health alert',
            default => Str::headline($type),
        };

        $message = $data['message'] ?? $data['flag_reason'] ?? '';

        if ($type === 'transaction_dlq' && $message === '') {
            $message = ($data['count'] ?? 0).' transaction(s) awaiting manual review';
        }

        $url = $this->normalizeUrl($data['url'] ?? null);

        return [
            'id' => $notification->id,
            'title' => $title,
            'message' => $message,
            'url' => $url,
            'time' => $notification->created_at?->diffForHumans() ?? '',
        ];
    }

    /**
     * Reduce a stored notification URL to a safe same-site path.
     *
     * Notifications store route()-generated absolute URLs, which bake the
     * APP_URL at send time into the row — a stale value (or tampered data)
     * must never become an off-site href. Relative URLs pass through;
     * absolute http(s) URLs keep only path + query; anything else is dropped.
     */
    protected function normalizeUrl(mixed $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        if (str_starts_with($url, '/')) {
            return str_starts_with($url, '//') ? null : $url;
        }

        if (preg_match('#^https?://#i', $url) === 1) {
            $path = parse_url($url, PHP_URL_PATH) ?: '/';
            $query = parse_url($url, PHP_URL_QUERY);

            return $query === null ? $path : $path.'?'.$query;
        }

        return null;
    }
}
