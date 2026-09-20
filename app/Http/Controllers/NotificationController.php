<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\V1\Traits\ApiResponse;
use App\Models\User;
use App\Services\System\NotificationBadgeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/**
 * In-app notification actions for the header bell.
 *
 * Any authenticated user manages only their own notifications; the ownership
 * check on the single-read route prevents guessing another user's
 * notification id and acknowledging it on their behalf.
 */
class NotificationController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected NotificationBadgeService $badgeService,
    ) {}

    /**
     * Mark every unread notification of the current user as read.
     */
    public function markAllRead(): RedirectResponse
    {
        auth()->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('success', 'All notifications marked as read.');
    }

    /**
     * Mark a single notification as read (own notifications only).
     *
     * An optional `redirect` form field navigates to the notification's
     * target after marking it read — relative URLs only, so a tampered
     * value can never bounce the user off-site.
     */
    public function markRead(Request $request, DatabaseNotification $notification): RedirectResponse
    {
        $this->assertOwnedByCurrentUser($notification);

        $notification->markAsRead();

        $redirect = $request->input('redirect');
        if (is_string($redirect) && str_starts_with($redirect, '/') && ! str_starts_with($redirect, '//')) {
            return redirect($redirect);
        }

        return back();
    }

    /**
     * Lightweight payload for the bell's live badge polling.
     *
     * dlq_count is only populated for admins (the badge service returns 0 for
     * everyone else) so branch-level staff never receive operational failure
     * counts.
     *
     * Response payload: { success: true, message: string, data: { count: int, dlq_count: int } }
     */
    public function unreadCount(): JsonResponse
    {
        $user = auth()->user();

        return $this->successResponse([
            'count' => $user ? $this->badgeService->unreadCount($user) : 0,
            'dlq_count' => $this->badgeService->dlqCount($user),
        ]);
    }

    protected function assertOwnedByCurrentUser(DatabaseNotification $notification): void
    {
        if ($notification->notifiable_id !== auth()->id()
            || $notification->notifiable_type !== User::class) {
            abort(403, 'You can only manage your own notifications.');
        }
    }
}
