<?php

namespace App\Providers;

use App\Events\CustomerRecordUpdated;
use App\Events\CustomerRelationAdded;
use App\Events\CustomerRelationRemoved;
use App\Events\RelatedPartyOwnershipConcern;
use App\Events\ReportGenerated;
use App\Events\SanctionsListUpdated;
use App\Events\TransactionApproved;
use App\Events\TransactionCancelled;
use App\Events\TransactionCreated;
use App\Listeners\ComplianceEventListener;
use App\Listeners\CustomerRelationListener;
use App\Listeners\RelatedPartyOwnershipConcernListener;
use App\Listeners\ReportGeneratedListener;
use App\Listeners\TransactionApprovedListener;
use App\Listeners\TransactionCancelledListener;
use App\Listeners\TransactionCreatedListener;
use App\Listeners\TriggerSanctionsRescreening;
use App\Services\System\CacheMonitoringService;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Queue\Events\JobFailed;
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
        // SendEmailVerificationNotification for Registered is registered by the
        // framework's base EventServiceProvider (configureEmailVerification);
        // listing it here as well would fire it twice.
        TransactionCreated::class => [
            TransactionCreatedListener::class,
        ],
        TransactionApproved::class => [
            TransactionApprovedListener::class,
        ],
        // ComplianceEventListener handles AlertCreated, CaseOpened,
        // RiskScoreUpdated and RiskScoreCalculated via its subscribe() map.
        // It must be registered as an event subscriber ($subscribe), not as a
        // plain listener: it has no handle()/__invoke() method, so mapping it
        // in $listen makes every dispatch of those events fail with
        // "Call to undefined method __invoke()" inside the dispatcher -
        // rolling back case creation within CaseManagementService's
        // transaction.
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
        TransactionCancelled::class => [
            TransactionCancelledListener::class,
        ],
        RelatedPartyOwnershipConcern::class => [
            RelatedPartyOwnershipConcernListener::class,
        ],
        ReportGenerated::class => [
            ReportGeneratedListener::class,
        ],
    ];

    /**
     * The subscriber classes to register.
     *
     * ComplianceEventListener exposes only handleX() methods plus subscribe(),
     * so it must be wired through the $subscribe property (Laravel event
     * subscriber pattern) rather than the $listen map.
     *
     * @var array<int, class-string>
     */
    protected $subscribe = [
        ComplianceEventListener::class,
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        parent::boot();

        // Feed the /performance cache hit-rate counters — recordHit/recordMiss
        // swallow their own errors, so a Redis blip cannot break a cache read.
        Event::listen(CacheHit::class,
            fn () => app(CacheMonitoringService::class)->recordHit());
        Event::listen(CacheMissed::class,
            fn () => app(CacheMonitoringService::class)->recordMiss());

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

        Queue::failing(function (JobFailed $event) {
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

    /**
     * The framework's base EventServiceProvider registers the email
     * verification listener for Registered exactly once. Because this class
     * inherits register(), it would otherwise run configureEmailVerification()
     * as well and add SendEmailVerificationNotification a second time.
     */
    protected function configureEmailVerification(): void
    {
        //
    }
}
