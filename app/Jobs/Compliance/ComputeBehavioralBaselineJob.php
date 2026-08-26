<?php

namespace App\Jobs\Compliance;

use App\Enums\TransactionStatus;
use App\Models\Compliance\CustomerBehavioralBaseline;
use App\Models\Customer;
use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Recomputes a customer's trailing-90-day behavioral baseline (average
 * transaction size and frequency) so the Transaction_Deviation risk factor
 * has real data to compare against.
 */
class ComputeBehavioralBaselineJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public readonly int $customerId) {}

    public function handle(): void
    {
        $customer = Customer::find($this->customerId);

        if (! $customer) {
            return;
        }

        $windowStart = now()->subDays(90);

        /** @var object{txn_count:int, avg_size:?string}|null $aggregate */
        $aggregate = Transaction::where('customer_id', $customer->id)
            ->where('status', TransactionStatus::Completed->value)
            ->where('created_at', '>=', $windowStart)
            ->selectRaw('COUNT(*) as txn_count')
            ->selectRaw('COALESCE(AVG(amount_local), 0) as avg_size')
            ->first();

        // Frequency: transactions per week over the trailing window.
        $frequency = $aggregate && $aggregate->txn_count > 0
            ? round($aggregate->txn_count / 13, 2)
            : 0;

        $currencies = Transaction::where('customer_id', $customer->id)
            ->where('status', TransactionStatus::Completed->value)
            ->where('created_at', '>=', $windowStart)
            ->distinct()->pluck('currency_code')->values()->all();

        DB::transaction(function () use ($customer, $aggregate, $frequency, $currencies) {
            $baseline = CustomerBehavioralBaseline::where('customer_id', $customer->id)
                ->orderByDesc('baseline_version')
                ->first();

            $nextVersion = ($baseline !== null ? $baseline->baseline_version : 0) + 1;

            CustomerBehavioralBaseline::create([
                'customer_id' => $customer->id,
                'currency_codes' => $currencies ?: null,
                'avg_transaction_size_myr' => (string) ($aggregate->avg_size ?? '0'),
                'avg_transaction_frequency' => (string) $frequency,
                'last_calculated_at' => now(),
                'baseline_version' => $nextVersion,
            ]);
        });
    }
}
