<?php

namespace App\Providers;

use App\Models\Branch;
use App\Models\Compliance\Alert;
use App\Models\Compliance\ComplianceCase;
use App\Models\Compliance\EnhancedDiligenceRecord;
use App\Models\Compliance\FlaggedTransaction;
use App\Models\Counter;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\StockTransfer;
use App\Models\SystemLog;
use App\Models\ThresholdAudit;
use App\Models\Transaction;
use App\Models\User;
use App\Policies\AlertPolicy;
use App\Policies\BranchPolicy;
use App\Policies\ComplianceCasePolicy;
use App\Policies\CounterPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\EnhancedDiligenceRecordPolicy;
use App\Policies\FlaggedTransactionPolicy;
use App\Policies\JournalEntryPolicy;
use App\Policies\StockTransferPolicy;
use App\Policies\SystemLogPolicy;
use App\Policies\ThresholdAuditPolicy;
use App\Policies\TransactionPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

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
        FlaggedTransaction::class => FlaggedTransactionPolicy::class,
        Alert::class => AlertPolicy::class,
        StockTransfer::class => StockTransferPolicy::class,
        ComplianceCase::class => ComplianceCasePolicy::class,
        EnhancedDiligenceRecord::class => EnhancedDiligenceRecordPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        Gate::define('viewReports', function (User $user): bool {
            return $user->role->canViewReports();
        });
    }
}
