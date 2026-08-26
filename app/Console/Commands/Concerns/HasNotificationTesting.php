<?php

namespace App\Console\Commands\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;

trait HasNotificationTesting
{
    /**
     * @return Collection<int, User>
     */
    protected function getTargetUsers(?int $userId = null): Collection
    {
        if ($userId) {
            // Explicit --user targeting is a manual override (e.g. testing a
            // single user's digest) and bypasses the opt-in filter.
            return User::where('id', $userId)->where('is_active', true)->get();
        }

        // Bulk sends honour the digest_enabled preference stored in the
        // users.notification_preferences JSON; missing key defaults to opted-in.
        return User::where('is_active', true)
            ->get()
            ->filter(function (User $user) {
                $prefs = $user->notification_preferences;

                return ! is_array($prefs) || ($prefs['digest_enabled'] ?? true) === true;
            })
            ->values();
    }

    protected function sendTestNotification(User $user, $notification): void
    {
        Notification::send($user, new $notification);
    }

    protected function formatNotificationResult(string $type, int $count): string
    {
        return "{$type} notification sent to {$count} user(s)";
    }
}
