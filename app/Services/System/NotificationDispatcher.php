<?php

namespace App\Services\System;

use App\Jobs\SendNotificationJob;
use App\Models\User;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class NotificationDispatcher
{
    public static function dispatchSafe(object|string|array $recipient, object $notification, array $channels = ['mail']): void
    {
        try {
            // Plain email addresses (e.g. config('monitoring.alert_recipients'))
            // are not notifiables; wrap them as on-demand notifications routed
            // over the first channel instead of failing parameter binding.
            // Inside the try so any routing error keeps dispatchSafe() safe.
            if (is_string($recipient)) {
                $recipient = (new AnonymousNotifiable)->route($channels[0] ?? 'mail', $recipient);
            }

            // Per-user opt-outs: a User recipient may have disabled this
            // notification type in their preferences.
            if ($recipient instanceof User && ! static::shouldDeliver($recipient, $notification)) {
                return;
            }

            // Fail closed for anything that is not a notifiable entity or an
            // iterable of them - the queued job could never route such a value.
            if (
                ! $recipient instanceof User
                && ! $recipient instanceof AnonymousNotifiable
                && ! $recipient instanceof \Traversable
            ) {
                Log::error('Failed to dispatch notification', [
                    'recipient' => static::formatRecipient($recipient),
                    'notification' => get_class($notification),
                    'error' => 'Recipient must be a notifiable entity or an iterable of them',
                ]);

                return;
            }

            SendNotificationJob::dispatch($recipient, $notification, $channels);
        } catch (\Throwable $e) {
            // \Throwable so serialization \Error from unserializable recipients
            // or notifications cannot escape into caller transactions.
            Log::error('Failed to dispatch notification', [
                'recipient' => static::formatRecipient($recipient),
                'notification' => get_class($notification),
                'error' => $e->getMessage(),
            ]);
        }
    }

    public static function dispatchToAll(Collection $recipients, object $notification, array $channels = ['mail']): void
    {
        foreach ($recipients as $recipient) {
            static::dispatchSafe($recipient, $notification, $channels);
        }
    }

    /**
     * User notification preferences: notification_preferences JSON maps
     * short type keys to booleans; null preferences means deliver everything.
     * Type key = trailing word(s) of the notification class, e.g.
     * LargeTransactionNotification => 'large_transaction'.
     */
    public static function shouldDeliver(User $user, object $notification): bool
    {
        $prefs = $user->notification_preferences;

        if (empty($prefs)) {
            return true;
        }

        $short = class_basename($notification);

        if (str_ends_with($short, 'Notification')) {
            $short = substr($short, 0, -strlen('Notification'));
        }

        $key = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $short));

        return array_key_exists($key, $prefs) ? (bool) $prefs[$key] : true;
    }

    protected static function formatRecipient(object|array|string $recipient): string
    {
        if (is_string($recipient)) {
            return $recipient;
        }

        if (is_array($recipient)) {
            return (string) ($recipient['email'] ?? $recipient['name'] ?? json_encode($recipient));
        }

        if ($recipient instanceof AnonymousNotifiable) {
            return json_encode($recipient->routes) ?: '{}';
        }

        if (property_exists($recipient, 'email')) {
            return (string) $recipient->email;
        }

        if ($recipient instanceof \Stringable) {
            return (string) $recipient;
        }

        return json_encode($recipient) ?: '';
    }
}
