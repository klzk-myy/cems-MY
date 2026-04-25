<?php

namespace App\Livewire\Stock;

use App\Livewire\BaseComponent;
use App\Models\Currency;
use App\Models\TillBalance;
use App\Services\CurrencyPositionService;
use App\Services\MathService;
use Illuminate\Support\Facades\Auth;

class Index extends BaseComponent
{
    public array $positions = [];

    public array $stats = [];

    public string $myrCashInHand = '0';

    public array $openTills = [];

    public array $closedTills = [];

    public array $todayBalances = [];

    public function mount()
    {
        $this->loadData();
    }

    public function loadData(): void
    {
        $user = Auth::user();
        $mathService = new MathService;
        $positionService = new CurrencyPositionService($mathService);

        // Get visible positions for user
        $positions = $positionService->getVisiblePositionsForUser($user);

        // Transform positions for display
        $this->positions = $positions->map(function ($position) use ($mathService) {
            // Calculate market value (balance * last valuation rate)
            $marketValue = $mathService->multiply(
                $position->balance,
                $position->last_valuation_rate ?? '0'
            );

            return [
                'id' => $position->id,
                'currency_code' => $position->currency_code,
                'currency_name' => $position->currency->name ?? $position->currency_code,
                'quantity' => $position->balance,
                'avg_cost' => $position->avg_cost_rate ?? '0',
                'market_value' => $marketValue,
                'unrealized_pl' => $position->unrealized_pnl ?? '0',
                'last_valuation_rate' => $position->last_valuation_rate ?? '0',
                'till_id' => $position->till_id,
            ];
        })->toArray();

        // Get MYR cash in hand
        $this->myrCashInHand = $this->calculateMyrCashInHand($user);

        // Get till information
        $this->openTills = TillBalance::whereDate('date', now()->toDateString())
            ->whereNull('closed_at')
            ->distinct()
            ->pluck('till_id')
            ->toArray();

        $this->closedTills = TillBalance::whereDate('date', now()->toDateString())
            ->whereNotNull('closed_at')
            ->distinct()
            ->pluck('till_id')
            ->toArray();

        // Get today's till balances
        $this->todayBalances = TillBalance::with(['currency', 'opener', 'closer'])
            ->whereDate('date', now()->toDateString())
            ->get()
            ->map(function ($balance) {
                return [
                    'id' => $balance->id,
                    'till_id' => $balance->till_id,
                    'currency_code' => $balance->currency_code,
                    'currency' => $balance->currency ? [
                        'code' => $balance->currency->code,
                        'name' => $balance->currency->name,
                    ] : null,
                    'opening_balance' => $balance->opening_balance,
                    'closing_balance' => $balance->closing_balance,
                    'variance' => $balance->variance,
                    'opener' => $balance->opener ? [
                        'name' => $balance->opener->name,
                    ] : null,
                    'closer' => $balance->closer ? [
                        'name' => $balance->closer->name,
                    ] : null,
                    'closed_at' => $balance->closed_at,
                ];
            })
            ->toArray();

        // Calculate total variance
        $totalVariance = '0';
        foreach ($this->todayBalances as $balance) {
            $totalVariance = $mathService->add($totalVariance, (string) ($balance['variance'] ?? '0'));
        }

        // Calculate total P&L
        $totalPnl = $positionService->getTotalPnl();

        $this->stats = [
            'total_currencies' => Currency::where('is_active', true)->count(),
            'active_positions' => count($this->positions),
            'open_tills' => count($this->openTills),
            'closed_tills' => count($this->closedTills),
            'total_variance' => $totalVariance,
            'total_pnl' => $totalPnl,
        ];
    }

    protected function calculateMyrCashInHand($user): string
    {
        $mathService = new MathService;

        $query = TillBalance::whereDate('date', now()->toDateString())
            ->where('currency_code', 'MYR');

        if (! $user->role->canManageAllBranches()) {
            $query->where('branch_id', $user->branch_id);
        }

        $balances = $query->get();
        $myrCash = '0';

        foreach ($balances as $balance) {
            $balanceAmount = $balance->closed_at
                ? ($balance->closing_balance ?? '0')
                : ($balance->opening_balance ?? '0');
            $myrCash = $mathService->add($myrCash, (string) $balanceAmount);
        }

        return $myrCash;
    }

    public function render()
    {
        return view('livewire.stock.index');
    }
}
