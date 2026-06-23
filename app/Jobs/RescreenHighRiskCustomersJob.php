<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Services\CustomerScreeningService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RescreenHighRiskCustomersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 600;

    public array $backoff = [30, 60, 120];

    public function handle(CustomerScreeningService $service): void
    {
        $query = Customer::where(function (Builder $q) {
            $q->where('risk_score', '>=', 70)
                ->orWhere('sanction_hit', true);
        })->select('id');

        $totalCount = 0;

        $query->chunkById(100, function ($customers) use ($service, &$totalCount) {
            foreach ($customers as $customer) {
                try {
                    $service->screenCustomer($customer);
                    $totalCount++;
                } catch (\Exception $e) {
                    Log::error('RescreenHighRiskCustomersJob: Failed to rescreen customer', [
                        'customer_id' => $customer->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });

        Log::info('RescreenHighRiskCustomersJob: Completed high-risk rescreening', [
            'customer_count' => $totalCount,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::critical('RescreenHighRiskCustomersJob: High-risk rescreening failed permanently', [
            'error' => $exception->getMessage(),
        ]);
    }

    public function tags(): array
    {
        return [
            'sanctions',
            'sanctions-rescreen',
            'high-risk',
        ];
    }
}
