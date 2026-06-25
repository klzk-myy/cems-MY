<?php

namespace App\Console\Commands\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;

trait HasNotificationTesting
{
    protected function getTargetUsers(?int $userId = null): Collection
    {
        if ($userId) {
            return User::where('id', $userId)->where('is_active', true)->get();
        }

        return User::where('is_active', true)->get();
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
