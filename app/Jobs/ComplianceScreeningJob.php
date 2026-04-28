<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Services\CustomerScreeningService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ComplianceScreeningJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 600;

    public function __construct(public int $customerId) {}

    public function handle(CustomerScreeningService $service): void
    {
        $start = microtime(true);

        $customer = Customer::find($this->customerId);
        if ($customer) {
            try {
                $service->screenCustomer($customer, 'Compliance screening job');
            } catch (\Exception $e) {
                Log::error('Compliance screening job failed', [
                    'customer_id' => $this->customerId,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }
        }

        $durationMs = (microtime(true) - $start) * 1000;

        Log::info('Compliance screening job completed', [
            'customer_id' => $this->customerId,
            'duration_ms' => $durationMs,
        ]);

        // For performance monitoring, always log warning (will be filtered by threshold in production)
        Log::warning('Slow compliance screening job', [
            'customer_id' => $this->customerId,
            'duration_ms' => $durationMs,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::critical('Compliance screening job failed permanently', [
            'customer_id' => $this->customerId,
            'error' => $exception->getMessage(),
        ]);
    }

    public function tags(): array
    {
        return ['compliance', 'screening'];
    }
}
