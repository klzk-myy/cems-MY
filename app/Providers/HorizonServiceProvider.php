<?php

namespace App\Providers;

use App\Enums\Permission;
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
        // Matrix-driven: admin holds ManageSystem by default and may grant
        // Horizon access to another role via the role-permission UI.
        Gate::define('viewHorizon', function ($user = null) {
            return $user?->role->canPerform(Permission::ManageSystem) ?? false;
        });
    }
}
