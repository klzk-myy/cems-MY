<?php

namespace App\Services\Dashboard;

use App\Enums\FlagStatus;
use App\Enums\Permission;
use App\Models\Customer;
use App\Models\FlaggedTransaction;
use App\Models\Transaction;
use App\Support\ActorContext;

class DashboardService
{
    public function buildStats(?int $branchId = null): array
    {
        $user = ActorContext::capture()->user;

        return [
            'total_transactions' => Transaction::today()
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->count(),
            'buy_volume' => Transaction::completed()->today()
                ->buy()
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->sum('amount_myr'),
            'sell_volume' => Transaction::completed()->today()
                ->sell()
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->sum('amount_myr'),
            'flagged' => $user && $user->role->canAccessCompliance()
                ? FlaggedTransaction::where('status', FlagStatus::Open->value)->count()
                : 0,
            'active_customers' => Customer::when($branchId, fn ($q) => $q->forBranch($branchId))->count(),
            'dlq_count' => $user && $user->role->canPerform(Permission::ManageDlq)
                ? Transaction::where('is_dlq', true)->count()
                : 0,
        ];
    }
}
