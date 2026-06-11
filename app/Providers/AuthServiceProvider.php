<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use App\Models\SystemLog;
use App\Models\ThresholdAudit;
use App\Policies\SystemLogPolicy;
use App\Policies\ThresholdAuditPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        SystemLog::class => SystemLogPolicy::class,
        ThresholdAudit::class => ThresholdAuditPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        //
    }
}
