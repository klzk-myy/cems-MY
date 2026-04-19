<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as NotificationFacade;

/**
 * Notification Job
 *
 * Queued job for sending notifications asynchronously.
 * This job handles all notification types and routes them to the
 * appropriate channels (mail, database, broadcast, etc.) via the queue.
 *
 * Usage:
 *   SendNotificationJob::dispatch($user, new LargeTransactionNotification($txn, $conf));
 *   SendNotificationJob::dispatch($users, new SanctionsMatchNotification($entry));
 */
class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The notifiable entity (User, AnonymousNotifiable, or collection)
     */
    public mixed $notifiable;

    /**
     * The notification instance
     */
    public Notification $notification;

    /**
     * Custom queue name (optional)
     */
    public ?string $queueName = null;

    /**
     * Array of channels to use (overrides notification's via() if provided)
     */
    public ?array $channels = null;

    /**
     * Number of retry attempts
     */
    public int $tries = 3;

    /**
     * Job timeout in seconds
     */
    public int $timeout = 120;

    /**
     * Create a new job instance.
     *
     * @param  Notifiable|AnonymousNotifiable|iterable  $notifiable
     * @param  array|null  $channels  Optional channel override
     * @param  string|null  $queueName  Optional queue name override
     */
    public function __construct(
        mixed $notifiable,
        Notification $notification,
        ?array $channels = null,
        ?string $queueName = null
    ) {
        $this->notifiable = $notifiable;
        $this->notification = $notification;
        $this->channels = $channels;
        $this->queueName = $queueName;

        // Set the queue if specified
        if ($queueName) {
            $this->onQueue($queueName);
        }
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::debug('SendNotificationJob started', [
            'notification' => get_class($this->notification),
            'queue' => $this->queue ?? 'default',
        ]);

        try {
            // If custom channels are provided, temporarily modify the notification's via method
            if ($this->channels) {
                $this->sendWithCustomChannels();
            } else {
                // Use notification's default channels via Laravel's notification system
                NotificationFacade::send($this->notifiable, $this->notification);
            }

            Log::debug('SendNotificationJob completed', [
                'notification' => get_class($this->notification),
            ]);
        } catch (\Exception $e) {
            Log::error('SendNotificationJob failed', [
                'notification' => get_class($this->notification),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Send notification with custom channel overrides.
     *
     * This creates a temporary notification class that uses the specified channels.
     * Note: This is a workaround for channel override; the notification still uses
     * its own toMail/toDatabase/etc. methods.
     */
    protected function sendWithCustomChannels(): void
    {
        $notification = $this->notification;
        $notifiable = $this->notifiable;

        // Create a proxy that overrides via() to return our custom channels
        $proxyNotification = new class($notification, $this->channels) extends Notification
        {
            private Notification $innerNotification;

            private array $customChannels;

            public function __construct(Notification $innerNotification, array $customChannels)
            {
                $this->innerNotification = $innerNotification;
                $this->customChannels = $customChannels;
            }

            public function via($notifiable): array
            {
                return $this->customChannels;
            }

            public function toMail($notifiable)
            {
                return $this->innerNotification->toMail($notifiable);
            }

            public function toArray($notifiable): array
            {
                return $this->innerNotification->toArray($notifiable);
            }

            public function toBroadcast($notifiable)
            {
                return method_exists($this->innerNotification, 'toBroadcast')
                    ? $this->innerNotification->toBroadcast($notifiable)
                    : null;
            }

            public function __call($method, $args)
            {
                return $this->innerNotification->{$method}(...$args);
            }
        };

        NotificationFacade::send($notifiable, $proxyNotification);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::critical('SendNotificationJob failed permanently', [
            'notification' => get_class($this->notification),
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        $notifiableId = is_object($this->notifiable)
            ? ($this->notifiable->id ?? spl_object_id($this->notifiable))
            : 'collection';

        return 'notification-'.get_class($this->notification).'-'.$notifiableId;
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'notification',
            'notification-'.get_class($this->notification),
        ];
    }
}
