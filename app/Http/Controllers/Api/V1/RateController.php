<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\FetchRateRequest;
use App\Http\Requests\OverrideRateRequest;
use App\Services\RateManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * RateController API v1
 *
 * Handles exchange rate management operations via API.
 * Manager/Admin only for rate overrides.
 */
class RateController extends Controller
{
    protected RateManagementService $rateService;

    public function __construct(RateManagementService $rateService)
    {
        $this->rateService = $rateService;
    }

    /**
     * Get all current rates.
     */
    public function index(): JsonResponse
    {
        $rates = $this->rateService->getCurrentRates();

        return response()->json([
            'success' => true,
            'data' => $rates,
        ]);
    }

    /**
     * Get rates summary with spread calculation.
     */
    public function summary(): JsonResponse
    {
        $summary = $this->rateService->getRatesSummary();

        return response()->json([
            'success' => true,
            'data' => $summary,
        ]);
    }

    /**
     * Fetch latest rates from API and store in exchange_rates table.
     * Accessible to Manager and Admin.
     */
    public function fetchFromApi(FetchRateRequest $request): JsonResponse
    {
        $user = Auth::user();

        if (! $user->role->isManager() && ! $user->role->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only managers and admins can fetch rates from API',
            ], 403);
        }

        $result = $this->rateService->fetchAndStoreRates($user);

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'rates' => $result['rates'],
        ]);
    }

    /**
     * Get a specific currency rate.
     */
    public function show(string $currencyCode): JsonResponse
    {
        $rate = $this->rateService->getRateForCurrency($currencyCode);

        if (! $rate) {
            return response()->json([
                'success' => false,
                'message' => "No rate found for {$currencyCode}",
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $rate,
        ]);
    }

    /**
     * Override/Manually set rates for a currency.
     * Manager/Admin only.
     */
    public function override(OverrideRateRequest $request, string $currencyCode): JsonResponse
    {
        $user = Auth::user();

        if (! $user->role->isManager() && ! $user->role->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only managers and admins can override rates',
            ], 403);
        }

        $validated = $request->validated();

        $result = $this->rateService->overrideRate(
            $currencyCode,
            $validated['rate_buy'],
            $validated['rate_sell'],
            $user,
            $validated['reason'] ?? null
        );

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'old_buy_rate' => $result['old_buy_rate'],
            'old_sell_rate' => $result['old_sell_rate'],
            'new_buy_rate' => $result['new_buy_rate'],
            'new_sell_rate' => $result['new_sell_rate'],
        ]);
    }

    /**
     * Copy previous day's rates as today's rates.
     * Manager/Admin only.
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

        $result = $this->rateService->copyPreviousRates($targetDate);

        return response()->json($result, $result['success'] ? 200 : 404);
    }

    /**
     * Get available dates for rate history (for copy previous feature).
     */
    public function availableDates(): JsonResponse
    {
        $dates = ExchangeRateHistory::select('effective_date')
            ->distinct()
            ->orderBy('effective_date', 'desc')
            ->limit(30)
            ->get()
            ->pluck('effective_date')
            ->map(fn ($date) => $date->format('Y-m-d'));

        return response()->json([
            'success' => true,
            'data' => $dates,
        ]);
    }

    /**
     * Get rate history/trend for a currency.
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

    /**
     * Check if all required rates are set.
     */
    public function checkSet(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'currencies' => 'required|array|min:1',
            'currencies.*' => 'string|size:3',
        ]);

        $result = $this->rateService->areAllRatesSet($validated['currencies']);

        return response()->json([
            'success' => true,
            'all_set' => $result['all_set'],
            'missing' => $result['missing'],
        ]);
    }

    /**
     * Validate a submitted rate against current market rate.
     */
    public function validateRate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'rate' => 'required|numeric|min:0.0001',
            'currency_code' => 'required|string|size:3',
            'type' => 'required|in:buy,sell',
        ]);

        $result = $this->rateService->validateTransactionRate(
            $validated['rate'],
            $validated['currency_code'],
            $validated['type']
        );

        return response()->json([
            'success' => true,
            'valid' => $result['valid'],
            'reason' => $result['reason'],
            'deviation_percent' => $result['deviation_percent'],
            'max_allowed' => $result['max_allowed'],
            'market_rate' => $result['market_rate'] ?? null,
            'submitted_rate' => $result['submitted_rate'] ?? null,
        ]);
    }
}
