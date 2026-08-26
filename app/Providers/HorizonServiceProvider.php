<?php

namespace App\Providers;

use App\Enums\UserRole;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Only route failure mail when an address is actually configured;
        // passing '' would route mail to a blank recipient.
        $horizonMailTo = env('HORIZON_MAIL_NOTIFICATIONS_TO');
        if (is_string($horizonMailTo) && $horizonMailTo !== '') {
            Horizon::routeMailNotificationsTo($horizonMailTo);
        }
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user = null) {
            return optional($user)->role->value === UserRole::Admin->value;
        });
    }
}
