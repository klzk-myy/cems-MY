<?php

namespace App\Livewire\Accounting\Revaluation;

use App\Livewire\BaseComponent;
use App\Models\CurrencyPosition;
use App\Services\AccountingService;
use App\Services\AuditService;
use App\Services\MathService;
use App\Services\RateApiService;
use App\Services\RevaluationService;
use Illuminate\View\View;

class Index extends BaseComponent
{
    public array $positions = [];

    public bool $isRunning = false;

    public ?string $runMessage = null;

    public function mount(): void
    {
        $this->loadPositions();
    }

    protected function loadPositions(): void
    {
        $currencyPositions = CurrencyPosition::with(['currency'])
            ->where('balance', '!=', 0)
            ->get();

        $this->positions = $currencyPositions->map(function ($position) {
            $oldRate = $position->last_valuation_rate ?? $position->avg_cost_rate;
            $currentRate = $position->current_rate ?? $oldRate;

            $unrealizedPnl = $position->unrealized_pnl ?? '0';
            $needsRevaluation = $position->last_valuation_rate === null
                || bccomp($position->last_valuation_rate, $currentRate, 6) !== 0;

            return [
                'id' => $position->id,
                'currency_code' => $position->currency_code,
                'currency_name' => $position->currency?->name ?? $position->currency_code,
                'balance' => $position->balance,
                'current_rate' => $currentRate,
                'previous_rate' => $oldRate,
                'unrealized_pnl' => $unrealizedPnl,
                'needs_revaluation' => $needsRevaluation,
                'last_valuation_at' => $position->last_valuation_at?->toIso8601String(),
            ];
        })->toArray();
    }

    public function runRevaluation(): void
    {
        $this->isRunning = true;
        $this->runMessage = null;

        try {
            $revaluationService = new RevaluationService(
                app(MathService::class),
                app(RateApiService::class),
                app(AccountingService::class),
                app(AuditService::class),
            );

            $result = $revaluationService->runRevaluation(auth()->id());

            $this->runMessage = sprintf(
                'Revaluation completed: %d positions revalued on %s',
                $result['positions_revalued'],
                $result['date']
            );

            $this->loadPositions();

            $this->dispatchBrowserEvent('toast', [
                'type' => 'success',
                'message' => $this->runMessage,
            ]);
        } catch (\Exception $e) {
            $this->runMessage = 'Revaluation failed: '.$e->getMessage();

            $this->dispatchBrowserEvent('toast', [
                'type' => 'error',
                'message' => $this->runMessage,
            ]);
        } finally {
            $this->isRunning = false;
        }
    }

    public function render(): View
    {
        return view('livewire.accounting.revaluation.index');
    }
}
