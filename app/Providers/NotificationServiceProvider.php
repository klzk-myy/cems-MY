<?php

namespace App\Providers;

use Illuminate\Support\Facades\Notification;
use Illuminate\Support\ServiceProvider;

/**
 * Notification Service Provider
 *
 * Registers notification services and channels for CEMS-MY.
 */
class NotificationServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Merge notification configuration
        $this->mergeConfigFrom(
            __DIR__.'/../../config/notifications.php',
            'notifications'
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register custom notification channels if needed
        // $this->app->make(ChannelManager::class)->extend('sms', function ($app) {
        //     return new SmsChannel($app->make(TwilioService::class));
        // });

        // Register webhook channel if enabled
        if (config('notifications.webhook.enabled')) {
            // $this->app->make(ChannelManager::class)->extend('webhook', function ($app) {
            //     return new WebhookChannel($app->make(WebhookService::class));
            // });
        }
    }
}
