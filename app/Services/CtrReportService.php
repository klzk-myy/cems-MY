<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Transaction;
use Illuminate\Support\Collection;

class CtrReportService
{
    protected float $ctrThreshold;

    protected float $ctrWarningThreshold;

    public function __construct(
        protected ThresholdService $thresholdService,
    ) {
        $this->ctrThreshold = (float) $this->thresholdService->getCtrThreshold();
        $this->ctrWarningThreshold = (float) config('compliance.ctr_warning_threshold', 20000);
    }

    public function checkThreshold(Transaction $transaction): array
    {
        $amount = (float) $transaction->amount_local;

        if ($amount >= $this->ctrThreshold) {
            return [
                'status' => 'exceeded',
                'threshold' => $this->ctrThreshold,
                'amount' => $amount,
                'message' => "CTR threshold of RM {$this->ctrThreshold} exceeded",
            ];
        }

        if ($amount >= $this->ctrWarningThreshold) {
            return [
                'status' => 'approaching',
                'threshold' => $this->ctrThreshold,
                'amount' => $amount,
                'message' => 'Amount is approaching CTR threshold',
            ];
        }

        return [
            'status' => 'not_exceeded',
            'threshold' => $this->ctrThreshold,
            'amount' => $amount,
            'message' => null,
        ];
    }

    public function getDailyTotal(Customer $customer, string $date): string
    {
        $total = Transaction::where('customer_id', $customer->id)
            ->whereDate('created_at', $date)
            ->whereIn('status', ['Completed', 'approved'])
            ->where('type', 'Buy')
            ->sum('amount_local');

        return number_format((float) $total, 2, '.', '');
    }

    public function getDailyCtrAggregates(string $date): Collection
    {
        $transactions = Transaction::whereDate('created_at', $date)
            ->whereIn('status', ['Completed', 'approved'])
            ->where('type', 'Buy')
            ->get();

        $grouped = $transactions->groupBy('customer_id')->map(function ($items, $customerId) {
            $total = $items->sum('amount_local');
            if ($total < $this->ctrThreshold) {
                return null;
            }
            $customer = $items->first()->customer;

            return [
                'customer_id' => $customerId,
                'full_name' => $customer?->full_name ?? 'Unknown',
                'total_amount' => $total,
                'transaction_count' => $items->count(),
            ];
        })->filter()->values();

        return $grouped;
    }

    public function generateCtrReport(string $date): array
    {
        $aggregates = $this->getDailyCtrAggregates($date);

        return [
            'report_date' => $date,
            'threshold' => $this->ctrThreshold,
            'warning_threshold' => $this->ctrWarningThreshold,
            'total_customers_above_threshold' => $aggregates->count(),
            'total_amount' => $aggregates->sum('total_amount'),
            'customers' => $aggregates->map(fn ($item) => [
                'customer_id' => $item['customer_id'],
                'customer_name' => $item['full_name'],
                'total_amount' => $item['total_amount'],
                'transaction_count' => $item['transaction_count'],
            ])->toArray(),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    public function isTransactionAboveThreshold(Transaction $transaction): bool
    {
        return (float) $transaction->amount_local >= $this->ctrThreshold;
    }

    public function getCtrThreshold(): float
    {
        return $this->ctrThreshold;
    }

    public function getCtrWarningThreshold(): float
    {
        return $this->ctrWarningThreshold;
    }
}
