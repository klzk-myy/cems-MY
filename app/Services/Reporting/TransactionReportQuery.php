<?php

namespace App\Services\Reporting;

use App\Enums\TransactionType;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Collection;

class TransactionReportQuery
{
    public function baseQuery(?int $branchId = null): Builder
    {
        return Transaction::query()->notCancelled()->forBranch($branchId);
    }

    public function completed(?int $branchId = null): Builder
    {
        return $this->baseQuery($branchId)->completed();
    }

    public function forDateRange(string $from, string $to, ?int $branchId = null): Builder
    {
        return $this->baseQuery($branchId)->forDateRange($from, $to);
    }

    /**
     * Aggregate buy/sell volumes and counts, optionally grouped by a column.
     *
     * @param  array<int, string>|null  $select
     * @return Collection<int, \stdClass>
     */
    public function buySellSummary(
        Builder $query,
        string|Expression|null $groupBy = null,
        string $volumeColumn = 'amount_local',
        ?string $amountColumn = null
    ): Collection {
        $buyType = TransactionType::Buy->value;
        $sellType = TransactionType::Sell->value;

        if ($groupBy) {
            $query->groupBy($groupBy);
        }

        $query->selectRaw("SUM(CASE WHEN type = ? THEN {$volumeColumn} ELSE 0 END) as buy_volume", [$buyType])
            ->selectRaw('COUNT(CASE WHEN type = ? THEN 1 END) as buy_count', [$buyType])
            ->selectRaw("SUM(CASE WHEN type = ? THEN {$volumeColumn} ELSE 0 END) as sell_volume", [$sellType])
            ->selectRaw('COUNT(CASE WHEN type = ? THEN 1 END) as sell_count', [$sellType]);

        if ($amountColumn) {
            $query->selectRaw("SUM(CASE WHEN type = ? THEN {$amountColumn} ELSE 0 END) as buy_amount", [$buyType])
                ->selectRaw("SUM(CASE WHEN type = ? THEN {$amountColumn} ELSE 0 END) as sell_amount", [$sellType]);
        }

        return $query->get();
    }

    /**
     * Split a pre-fetched transaction collection into buy/sell volumes and counts.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, Transaction>|Collection<int, Transaction>  $transactions
     * @return array{buy_count: int, buy_volume: string, sell_count: int, sell_volume: string}
     */
    public function buySellVolumes(Collection $transactions, string $volumeColumn = 'amount_local'): array
    {
        $buy = $transactions->where('type', TransactionType::Buy->value);
        $sell = $transactions->where('type', TransactionType::Sell->value);

        return [
            'buy_count' => $buy->count(),
            'buy_volume' => (string) $buy->sum($volumeColumn),
            'sell_count' => $sell->count(),
            'sell_volume' => (string) $sell->sum($volumeColumn),
        ];
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
