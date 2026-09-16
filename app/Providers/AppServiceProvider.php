<?php

namespace App\Providers;

use App\Models\Customer;
use App\Models\FlaggedTransaction;
use App\Models\Transaction;
use App\Services\Contracts\MathServiceInterface;
use App\Services\Contracts\RateManagementServiceInterface;
use App\Services\Contracts\ThresholdServiceInterface;
use App\Services\Contracts\TransactionApprovalServiceInterface;
use App\Services\Contracts\TransactionCreationServiceInterface;
use App\Services\Contracts\TransactionHoldServiceInterface;
use App\Services\Contracts\TransactionIdempotencyServiceInterface;
use App\Services\Contracts\TransactionServiceInterface;
use App\Services\Contracts\TransactionStatusServiceInterface;
use App\Services\Contracts\TransactionValidationInterface;
use App\Services\System\CacheInvalidationService;
use App\Services\System\MathService;
use App\Services\ThresholdService;
use App\Services\Transaction\RateManagementService;
use App\Services\Transaction\TransactionApprovalService;
use App\Services\Transaction\TransactionCreationService;
use App\Services\Transaction\TransactionHoldService;
use App\Services\Transaction\TransactionIdempotencyService;
use App\Services\Transaction\TransactionService;
use App\Services\Transaction\TransactionStatusService;
use App\Services\Transaction\TransactionValidationService;
use App\View\Composers\NotificationComposer;
use App\View\Composers\UserComposer;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            TransactionServiceInterface::class,
            TransactionService::class
        );

        $this->app->bind(
            TransactionHoldServiceInterface::class,
            TransactionHoldService::class
        );

        $this->app->bind(
            TransactionIdempotencyServiceInterface::class,
            TransactionIdempotencyService::class
        );

        $this->app->bind(
            TransactionStatusServiceInterface::class,
            TransactionStatusService::class
        );

        $this->app->bind(
            TransactionValidationInterface::class,
            TransactionValidationService::class
        );

        $this->app->bind(
            TransactionCreationServiceInterface::class,
            TransactionCreationService::class
        );

        $this->app->bind(
            TransactionApprovalServiceInterface::class,
            TransactionApprovalService::class
        );

        $this->app->bind(
            MathServiceInterface::class,
            MathService::class
        );

        $this->app->bind(
            RateManagementServiceInterface::class,
            RateManagementService::class
        );

        // Request/job-scoped: the persisted-override snapshot is loaded once
        // per lifecycle, so all consumers share one threshold_audits query.
        $this->app->scoped(
            ThresholdServiceInterface::class,
            ThresholdService::class
        );

        $this->app->scoped(ThresholdService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Note: Redis password override for tests is handled in tests/CreatesApplication.php
        // after app bootstrap. The runningUnitTests() check is unavailable during
        // AppServiceProvider::boot() because 'unitTesting' is set after provider boot.

        // Catch N+1 queries: violations throw in dev/test but only log in
        // production, so a missed eager load can never 500 a live request.
        Model::preventLazyLoading();
        Model::handleLazyLoadingViolationUsing(function (Model $model, string $relation) {
            // Keep the stock handler's exemption: freshly created and unsaved
            // models may still resolve relations lazily (the callback
            // bypasses this check when registered, so re-apply it here).
            if (! $model->exists || $model->wasRecentlyCreated) {
                return;
            }

            if (app()->isProduction()) {
                Log::warning('Lazy loading violation', [
                    'model' => $model::class,
                    'relation' => $relation,
                    'url' => app()->runningInConsole() ? 'console' : request()->fullUrl(),
                ]);

                return;
            }

            throw new LazyLoadingViolationException($model, $relation);
        });

        $this->registerCacheInvalidationSafetyNet();

        $this->registerMorphMap();
        $this->registerCarbonMacros();
        $this->registerBladeDirectives();
        $this->registerViewComposers();

        // Name framework-provided routes after all service providers have booted.
        $this->app->booted(function () {
            $this->nameFrameworkRoutes();
        });
    }

    /**
     * Assign names to framework-provided routes that are registered without a name.
     * This keeps the route table consistent with the application's naming convention.
     */
    protected function nameFrameworkRoutes(): void
    {
        $routeCollection = Route::getRoutes();

        foreach ($routeCollection->getRoutes() as $route) {
            if ($route->getName() === null && $route->uri() === 'broadcasting/auth') {
                $route->name('broadcasting.auth');
            }
        }

        $routeCollection->refreshNameLookups();
    }

    /**
     * Register the polymorphic relationship morph map.
     * This allows using short names like 'Customer' and 'Transaction'
     * in polymorphic relationship type columns.
     */
    protected function registerMorphMap(): void
    {
        Relation::morphMap([
            'Customer' => Customer::class,
            'Transaction' => Transaction::class,
        ]);
    }

    /**
     * Register Carbon macros for working days calculations.
     * BNM compliance requires STR filing within 3 working days.
     */
    protected function registerCarbonMacros(): void
    {
        Carbon::macro('addWorkingDays', function (int $days) {
            $current = $this->copy();
            $added = 0;

            while ($added < $days) {
                $current->addDay();
                if (! $current->isWeekend()) {
                    $added++;
                }
            }

            return $current;
        });

        Carbon::macro('workingDaysUntil', function ($date) {
            $end = $date instanceof Carbon ? $date->copy() : Carbon::parse($date);
            $current = $this->copy();
            $workingDays = 0;

            while ($current->lessThan($end)) {
                $current->addDay();
                if (! $current->isWeekend()) {
                    $workingDays++;
                }
            }

            return $workingDays;
        });
    }

    /**
     * Register Blade directives for common operations.
     */
    protected function registerBladeDirectives(): void
    {
        Blade::directive('statusLabel', function ($statusExpr, $default = "''") {
            return "<?php echo \App\Helpers\LabelHelper::getStatusLabel({$statusExpr}, {$default}); ?>";
        });

        Blade::directive('typeLabel', function ($typeExpr, $default = "''") {
            return "<?php echo \App\Helpers\LabelHelper::getTypeLabel({$typeExpr}, {$default}); ?>";
        });

        Blade::if('role', function ($role) {
            if (! auth()->check()) {
                return false;
            }

            $userRole = auth()->user()->role;

            return match ($role) {
                'admin' => $userRole->isAdmin(),
                'manager' => $userRole->isManager(),
                'compliance_officer' => $userRole->isComplianceOfficer(),
                'teller' => $userRole->isTeller(),
                default => false,
            };
        });
    }

    /**
     * Register view composers for shared data.
     */
    protected function registerViewComposers(): void
    {
        View::composer('*', UserComposer::class);
        View::composer('components.app-layout', NotificationComposer::class);
    }

    /**
     * Flush the dashboard cache tag on writes to the primary aggregates.
     *
     * Defense-in-depth: explicit invalidation lives at the service layer, but
     * any write path that bypasses it (jobs, importers, future code) still
     * flushes the tag. The entry TTL already bounds staleness, so this only
     * shortens it for missed call sites. Mass updates bypass model events,
     * which is inherent and acceptable for a safety net.
     */
    protected function registerCacheInvalidationSafetyNet(): void
    {
        $invalidator = $this->app->make(CacheInvalidationService::class);

        // On stores without tag support, invalidate() falls back to a full
        // cache flush — far too expensive to run on every model save.
        if (! $invalidator->supportsTags()) {
            return;
        }

        // Flushes are deferred to afterCommit: firing on save() would let a
        // concurrent reader repopulate the tag with uncommitted state, and
        // the writes above often run inside DB::transaction().
        $flush = fn (string ...$tags) => DB::afterCommit(
            function () use ($invalidator, $tags) {
                foreach ($tags as $tag) {
                    $invalidator->invalidate($tag);
                }
            }
        );

        Transaction::saved(fn () => $flush('dashboard'));
        Transaction::deleted(fn () => $flush('dashboard'));
        Customer::saved(fn () => $flush('dashboard', 'customers'));
        Customer::deleted(fn () => $flush('dashboard', 'customers'));
        FlaggedTransaction::saved(fn () => $flush('dashboard'));
        FlaggedTransaction::deleted(fn () => $flush('dashboard'));
    }
}
