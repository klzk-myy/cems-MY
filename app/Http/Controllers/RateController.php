<?php

namespace App\Http\Controllers;

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

    /**
     * Display rate management page with current rates table.
     */
    public function index(): View
    {
        $rates = $this->rateService->getRatesSummary();
        $availableDates = ExchangeRateHistory::select('effective_date')
            ->distinct()
            ->orderBy('effective_date', 'desc')
            ->limit(30)
            ->get()
            ->pluck('effective_date')
            ->map(fn ($date) => $date->format('Y-m-d'))
            ->toArray();

        return view('rates.index', [
            'rates' => $rates,
            'availableDates' => $availableDates,
        ]);
    }

    /**
     * Fetch rates from external API.
     */
    public function fetchFromApi(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (! $user->role->isManager() && ! $user->role->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only managers and admins can fetch rates from API',
            ], 403);
        }

        $result = $this->rateService->fetchAndStoreRates($user);

        return response()->json($result);
    }

    /**
     * Copy previous day's rates.
     */
    public function copyPrevious(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (! $user->role->isManager() && ! $user->role->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only managers and admins can copy previous rates',
            ], 403);
        }

        $validated = $request->validate([
            'date' => 'nullable|date|before_or_equal:today',
        ]);

        $targetDate = $validated['date'] ?? now()->subDay()->toDateString();

        $historicalRates = ExchangeRateHistory::where('effective_date', $targetDate)->get();

        if ($historicalRates->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => "No rates found for date {$targetDate}",
            ], 404);
        }

        $copied = [];
        foreach ($historicalRates as $histRate) {
            $exchangeRate = ExchangeRate::where('currency_code', $histRate->currency_code)->first();

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

    /**
     * Override rate for a specific currency.
     */
    public function override(Request $request, string $currencyCode): JsonResponse
    {
        $user = Auth::user();

        if (! $user->role->isManager() && ! $user->role->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only managers and admins can override rates',
            ], 403);
        }

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
            $validated['reason'] ?? null
        );

        return response()->json($result);
    }

    /**
     * Get rate history for a currency.
     */
    public function history(Request $request, string $currencyCode): JsonResponse
    {
        $days = $request->get('days', 30);

        $histories = ExchangeRateHistory::forCurrency($currencyCode)
            ->forDateRange(
                now()->subDays($days)->toDateString(),
                now()->toDateString()
            )
            ->orderBy('effective_date', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $histories,
        ]);
    }
}
