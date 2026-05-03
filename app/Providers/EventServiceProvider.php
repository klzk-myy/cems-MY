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
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

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
        parent::boot();

        // Queue event listeners for monitoring
        Queue::before(function (JobProcessing $event) {
            Log::info('Queue job starting', [
                'job' => $event->job->getName(),
                'id' => $event->job->getJobId(),
                'queue' => $event->job->getQueue(),
            ]);
        });

        Queue::after(function (JobProcessed $event) {
            Log::info('Queue job completed', [
                'job' => $event->job->getName(),
                'id' => $event->job->getJobId(),
                'queue' => $event->job->getQueue(),
            ]);
        });

        Queue::failing(function (JobExceptionOccurred $event) {
            Log::error('Queue job failing', [
                'job' => $event->job->getName(),
                'id' => $event->job->getJobId(),
                'queue' => $event->job->getQueue(),
                'error' => $event->exception->getMessage(),
            ]);
        });
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
