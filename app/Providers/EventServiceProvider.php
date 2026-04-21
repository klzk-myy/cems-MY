<?php

namespace App\Providers;

use App\Events\AlertCreated;
use App\Events\CaseOpened;
use App\Events\CustomerRecordUpdated;
use App\Events\CustomerRelationAdded;
use App\Events\CustomerRelationRemoved;
use App\Events\RiskScoreCalculated;
use App\Events\RiskScoreUpdated;
use App\Events\SanctionsListUpdated;
use App\Events\TransactionApproved;
use App\Events\TransactionCreated;
use App\Listeners\ComplianceEventListener;
use App\Listeners\CustomerRelationListener;
use App\Listeners\TransactionApprovedListener;
use App\Listeners\TransactionCreatedListener;
use App\Listeners\TriggerSanctionsRescreening;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        TransactionCreated::class => [
            TransactionCreatedListener::class,
        ],
        TransactionApproved::class => [
            TransactionApprovedListener::class,
        ],
        AlertCreated::class => [
            ComplianceEventListener::class,
        ],
        CaseOpened::class => [
            ComplianceEventListener::class,
        ],
        RiskScoreUpdated::class => [
            ComplianceEventListener::class,
        ],
        RiskScoreCalculated::class => [
            ComplianceEventListener::class,
        ],
        CustomerRelationAdded::class => [
            [CustomerRelationListener::class, 'handleAdded'],
        ],
        CustomerRelationRemoved::class => [
            [CustomerRelationListener::class, 'handleRemoved'],
        ],
        CustomerRecordUpdated::class => [
            [TriggerSanctionsRescreening::class, 'handleCustomerUpdate'],
        ],
        SanctionsListUpdated::class => [
            [TriggerSanctionsRescreening::class, 'handleSanctionsUpdate'],
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
