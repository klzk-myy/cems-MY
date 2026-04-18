<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Services\UnifiedSanctionScreeningService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
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

    public function handle(UnifiedSanctionScreeningService $service): void
    {
        $customers = Customer::where('risk_score', '>=', 70)
            ->orWhere('sanction_hit', true)
            ->pluck('id');

        Log::info('RescreenHighRiskCustomersJob: Starting high-risk rescreening', [
            'customer_count' => $customers->count(),
        ]);

        foreach ($customers as $customerId) {
            $customer = Customer::find($customerId);
            if ($customer) {
                try {
                    $service->screenCustomer($customer, 'Scheduled high-risk rescreening');
                } catch (\Exception $e) {
                    Log::error('RescreenHighRiskCustomersJob: Failed to rescreen customer', [
                        'customer_id' => $customerId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        Log::info('RescreenHighRiskCustomersJob: Completed high-risk rescreening', [
            'customer_count' => $customers->count(),
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
