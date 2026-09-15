<?php

namespace App\Services\Dashboard;

use App\Enums\Permission;
use App\Models\Customer;
use App\Models\FlaggedTransaction;
use App\Models\Transaction;

class DashboardService
{
    public function buildStats(?int $branchId = null): array
    {
        $user = auth()->user();

        return [
            'total_transactions' => Transaction::whereDate('created_at', today())
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->count(),
            'buy_volume' => Transaction::completed()->whereDate('created_at', today())
                ->buy()
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->sum('amount_local'),
            'sell_volume' => Transaction::completed()->whereDate('created_at', today())
                ->sell()
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->sum('amount_local'),
            'flagged' => $user && $user->role->canAccessCompliance()
                ? FlaggedTransaction::where('status', 'Open')->count()
                : 0,
            'active_customers' => Customer::when($branchId, fn ($q) => $q->forBranch($branchId))->count(),
            'dlq_count' => $user && $user->role->canPerform(Permission::ManageDlq)
                ? Transaction::where('is_dlq', true)->count()
                : 0,
        ];
    }
}
