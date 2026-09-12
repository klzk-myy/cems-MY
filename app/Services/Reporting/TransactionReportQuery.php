<?php

namespace App\Services\Reporting;

use App\Enums\TransactionType;
use App\Exceptions\Domain\ReportValidationException;
use App\Models\Transaction;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TransactionReportQuery
{
    /**
     * @return Builder<Transaction>
     */
    public function baseQuery(?int $branchId = null): Builder
    {
        return Transaction::query()->notCancelled()->forBranch($branchId);
    }

    /**
     * @return Builder<Transaction>
     */
    public function completed(?int $branchId = null): Builder
    {
        return $this->baseQuery($branchId)->completed();
    }

    /**
     * @return Builder<Transaction>
     */
    public function forDateRange(string $from, string $to, ?int $branchId = null): Builder
    {
        return $this->baseQuery($branchId)->forDateRange($from, $to);
    }

    /**
     * Aggregate buy/sell volumes and counts, optionally grouped by a column.
     *
     * @param  Builder<Transaction>  $query
     * @param  array<int, string>|null  $select
     * @return Collection<int, \stdClass>
     */
    public function buySellSummary(
        Builder $query,
        string|Expression|null $groupBy = null,
        string $volumeColumn = 'amount_local',
        ?string $amountColumn = null
    ): Collection {
        // The column names are interpolated into raw SQL fragments; only allow
        // known transaction columns so a caller can never inject SQL through
        // this parameter.
        foreach (array_filter([$volumeColumn, $amountColumn]) as $column) {
            if (! in_array($column, ['amount_local', 'amount_foreign'], true)) {
                throw new ReportValidationException("Unsupported report column '{$column}'.");
            }
        }

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

        return $query->toBase()->get();
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
            'buy_volume' => $this->sumColumn($buy, $volumeColumn),
            'sell_count' => $sell->count(),
            'sell_volume' => $this->sumColumn($sell, $volumeColumn),
        ];
    }

    /**
     * Sum a column of DECIMAL strings with bcmath so large monetary totals in
     * reports never lose precision to a float cast (Collection::sum() would
     * cast '0.0001' + '0.0002' to 0.00030000000000000003).
     */
    private function sumColumn(Collection $rows, string $column): string
    {
        $total = '0';

        foreach ($rows as $row) {
            $value = $row->{$column} ?? '0';
            if (is_string($value) && $value !== '' && is_numeric($value)) {
                $total = bcadd($total, $value, 4);
            }
        }

        return $total;
    }

    public function sumByType(?int $branchId = null): array
    {
        $query = $this->completed($branchId);

        $buySum = (clone $query)->buy()->sum('amount_foreign');
        $sellSum = (clone $query)->sell()->sum('amount_foreign');

        return [
            'buy' => $buySum ?? '0',
            'sell' => $sellSum ?? '0',
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
