<?php

namespace App\Services\Reporting;

use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;

class TransactionReportQuery
{
    public function baseQuery(?int $branchId = null): Builder
    {
        return Transaction::query()->forBranch($branchId);
    }

    public function completed(?int $branchId = null): Builder
    {
        return $this->baseQuery($branchId)->completed();
    }

    public function forDateRange(string $from, string $to, ?int $branchId = null): Builder
    {
        return $this->baseQuery($branchId)->forDateRange($from, $to);
    }

    public function sumByType(?int $branchId = null): array
    {
        $query = $this->completed($branchId);

        return [
            'buy' => (float) (clone $query)->buy()->sum('amount_foreign'),
            'sell' => (float) (clone $query)->sell()->sum('amount_foreign'),
        ];
    }

    public function countByStatus(?int $branchId = null): array
    {
        return $this->baseQuery($branchId)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
    }
}
