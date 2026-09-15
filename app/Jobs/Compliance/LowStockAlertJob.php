<?php

namespace App\Jobs\Compliance;

use App\Enums\SystemAlertLevel;
use App\Models\Currency;
use App\Models\CurrencyPosition;
use App\Services\System\SystemAlertService;
use App\Services\ThresholdService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class LowStockAlertJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public array $backoff = [30, 60, 120];

    public function handle(SystemAlertService $alertService, ThresholdService $thresholdService): void
    {
        $threshold = (string) $thresholdService->get('low_stock', 'threshold', 10000);

        if (! is_numeric($threshold)) {
            throw new \InvalidArgumentException('Configured low stock threshold must be numeric.');
        }
        $lowStockCurrencies = [];

        $currencies = Currency::where('is_active', true)->get();

        foreach ($currencies as $currency) {
            $totalPosition = CurrencyPosition::where('currency_code', $currency->code)
                ->sum('foreign_total');

            if (bccomp((string) $totalPosition, $threshold, 4) < 0) {
                $lowStockCurrencies[] = [
                    'currency' => $currency->code,
                    'position' => (string) $totalPosition,
                    'threshold' => $threshold,
                ];
            }
        }

        if (empty($lowStockCurrencies)) {
            Log::info('LowStockAlertJob: No low stock currencies detected');

            return;
        }

        foreach ($lowStockCurrencies as $item) {
            $alertService->send(
                "Low stock alert for {$item['currency']}: position {$item['position']} below threshold {$item['threshold']}",
                SystemAlertLevel::Warning->value,
                [
                    'source' => 'low_stock',
                    'metadata' => ['currency' => $item['currency'], 'position' => $item['position']],
                ]
            );
        }

        Log::info('LowStockAlertJob: Created '.count($lowStockCurrencies).' low stock alerts');
    }

    public function failed(\Throwable $exception): void
    {
        Log::critical('LowStockAlertJob: Failed', ['error' => $exception->getMessage()]);
    }

    public function tags(): array
    {
        return ['inventory', 'low-stock'];
    }
}
