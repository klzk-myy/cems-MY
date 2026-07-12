<?php

namespace App\Providers;

use App\Models\Customer;
use App\Models\Transaction;
use App\Services\Contracts\TransactionApprovalServiceInterface;
use App\Services\Contracts\TransactionCreationServiceInterface;
use App\Services\Contracts\TransactionHoldServiceInterface;
use App\Services\Contracts\TransactionIdempotencyServiceInterface;
use App\Services\Contracts\TransactionServiceInterface;
use App\Services\Contracts\TransactionStatusServiceInterface;
use App\Services\Contracts\TransactionValidationInterface;
use App\Services\Transaction\TransactionApprovalService;
use App\Services\Transaction\TransactionCreationService;
use App\Services\Transaction\TransactionHoldService;
use App\Services\Transaction\TransactionIdempotencyService;
use App\Services\Transaction\TransactionService;
use App\Services\Transaction\TransactionStatusService;
use App\Services\Transaction\TransactionValidationService;
use App\View\Composers\NavigationComposer;
use App\View\Composers\UserComposer;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Blade;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Note: Redis password override for tests is handled in tests/CreatesApplication.php
        // after app bootstrap. The runningUnitTests() check is unavailable during
        // AppServiceProvider::boot() because 'unitTesting' is set after provider boot.

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

        foreach ($routeCollection as $route) {
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
        View::composer('*', NavigationComposer::class);
        View::composer('*', UserComposer::class);
    }
}
