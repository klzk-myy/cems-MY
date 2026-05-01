<?php

namespace App\Providers;

use App\Models\Customer;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerMorphMap();
        $this->registerCarbonMacros();
        $this->registerBladeDirectives();
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
}
