<?php

namespace App\Providers;

use App\Models\Branch;
use App\Models\Counter;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\SystemLog;
use App\Models\ThresholdAudit;
use App\Models\Transaction;
use App\Models\User;
use App\Policies\BranchPolicy;
use App\Policies\CounterPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\JournalEntryPolicy;
use App\Policies\SystemLogPolicy;
use App\Policies\ThresholdAuditPolicy;
use App\Policies\TransactionPolicy;
use App\Policies\UserPolicy;
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
        Transaction::class => TransactionPolicy::class,
        Customer::class => CustomerPolicy::class,
        Branch::class => BranchPolicy::class,
        Counter::class => CounterPolicy::class,
        User::class => UserPolicy::class,
        JournalEntry::class => JournalEntryPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
    }
}
