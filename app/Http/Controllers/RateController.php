<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\ExchangeRate;
use App\Models\ExchangeRateHistory;
use App\Services\RateManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RateController extends Controller
{
    protected RateManagementService $rateService;

    public function __construct(RateManagementService $rateService)
    {
        $this->rateService = $rateService;
    }

    public function index(Request $request): View
    {
        $user = Auth::user();
        $branchId = $this->resolveBranchId($user, $request);

        $rates = $this->rateService->getRatesSummary($branchId);

        $historyQuery = ExchangeRateHistory::query();
        if ($branchId !== null) {
            $historyQuery->where('branch_id', $branchId);
        }
        $availableDates = $historyQuery->select('effective_date')
            ->distinct()
            ->orderBy('effective_date', 'desc')
            ->limit(30)
            ->get()
            ->pluck('effective_date')
            ->map(fn ($date) => $date->format('Y-m-d'))
            ->toArray();

        $branch = $branchId ? Branch::find($branchId) : null;

        return view('rates.index', [
            'rates' => $rates,
            'availableDates' => $availableDates,
            'currentBranch' => $branch,
            'canSelectBranch' => $user->role->isAdmin(),
        ]);
    }

    public function fetchFromApi(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (! $user->role->isManager() && ! $user->role->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only managers and admins can fetch rates from API',
            ], 403);
        }

        $branchId = $this->resolveBranchId($user, $request);

        $result = $this->rateService->fetchAndStoreRates($user, $branchId);

        return response()->json($result);
    }

    public function copyPrevious(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (! $user->role->isManager() && ! $user->role->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only managers and admins can copy previous rates',
            ], 403);
        }

        $branchId = $this->resolveBranchId($user, $request);

        $validated = $request->validate([
            'date' => 'nullable|date|before_or_equal:today',
        ]);

        $targetDate = $validated['date'] ?? now()->subDay()->toDateString();

        $historyQuery = ExchangeRateHistory::where('effective_date', $targetDate);
        if ($branchId !== null) {
            $historyQuery->where('branch_id', $branchId);
        }
        $historicalRates = $historyQuery->get();

        if ($historicalRates->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => "No rates found for date {$targetDate}",
            ], 404);
        }

        $copied = [];
        foreach ($historicalRates as $histRate) {
            $query = ExchangeRate::where('currency_code', $histRate->currency_code);
            if ($branchId !== null) {
                $query->forBranch($branchId);
            }
            $exchangeRate = $query->first();

            if ($exchangeRate) {
                $oldBuy = $exchangeRate->rate_buy;
                $oldSell = $exchangeRate->rate_sell;

                $exchangeRate->update([
                    'rate_buy' => $histRate->rate,
                    'rate_sell' => $histRate->rate,
                    'source' => "copied_from_{$targetDate}",
                    'fetched_at' => now(),
                ]);

                $copied[] = [
                    'currency' => $histRate->currency_code,
                    'old_buy' => $oldBuy,
                    'old_sell' => $oldSell,
                    'new_rate' => $histRate->rate,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Rates copied successfully',
            'copied_from_date' => $targetDate,
            'rates' => $copied,
        ]);
    }

    public function override(Request $request, string $currencyCode): JsonResponse
    {
        $user = Auth::user();

        if (! $user->role->isManager() && ! $user->role->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only managers and admins can override rates',
            ], 403);
        }

        $branchId = $this->resolveBranchId($user, $request);

        $validated = $request->validate([
            'rate_buy' => 'required|numeric|min:0.0001',
            'rate_sell' => 'required|numeric|min:0.0001',
            'reason' => 'nullable|string|max:500',
        ]);

        $result = $this->rateService->overrideRate(
            $currencyCode,
            $validated['rate_buy'],
            $validated['rate_sell'],
            $user,
            $validated['reason'] ?? null,
            $branchId
        );

        return response()->json($result);
    }

    public function history(Request $request, string $currencyCode): JsonResponse
    {
        $user = Auth::user();
        $branchId = $this->resolveBranchId($user, $request);
        $days = $request->get('days', 30);

        $query = ExchangeRateHistory::forCurrency($currencyCode)
            ->forDateRange(
                now()->subDays($days)->toDateString(),
                now()->toDateString()
            );

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        $histories = $query->orderBy('effective_date', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $histories,
        ]);
    }

    protected function resolveBranchId($user, Request $request): ?int
    {
        if ($user->role->isAdmin() && $request->has('branch_id')) {
            return (int) $request->get('branch_id');
        }

        if ($user->role->isManager()) {
            return $user->branch_id;
        }

        return null;
    }
}
