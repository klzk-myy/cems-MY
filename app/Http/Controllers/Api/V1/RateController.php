<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Traits\ApiResponse;
use App\Http\Controllers\Concerns\EnsuresManagerOrAdmin;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Rate\CheckRateSetRequest;
use App\Http\Requests\Api\V1\Rate\CopyPreviousRateRequest;
use App\Http\Requests\Api\V1\Rate\ValidateRateRequest;
use App\Http\Requests\Api\V1\RateHistoryRequest;
use App\Http\Requests\FetchRateRequest;
use App\Http\Requests\OverrideRateRequest;
use App\Models\ExchangeRateHistory;
use App\Services\Transaction\RateManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * RateController API v1
 *
 * Handles exchange rate management operations via API.
 * Manager/Admin only for rate overrides.
 */
class RateController extends Controller
{
    use ApiResponse;
    use EnsuresManagerOrAdmin;

    public function __construct(
        protected RateManagementService $rateService
    ) {}

    /**
     * Get all current rates.
     */
    public function index(): JsonResponse
    {
        $rates = $this->rateService->getCurrentRates();

        return $this->successResponse($rates);
    }

    /**
     * Get rates summary with spread calculation.
     */
    public function summary(): JsonResponse
    {
        $summary = $this->rateService->getRatesSummary();

        return $this->successResponse($summary);
    }

    /**
     * Fetch latest rates from API and store in exchange_rates table.
     * Accessible to Manager and Admin.
     */
    public function fetchFromApi(FetchRateRequest $request): JsonResponse
    {
        $user = Auth::user();

        if ($response = $this->ensureManagerOrAdminResponse(
            fn () => $this->errorResponse('Only managers and admins can fetch rates from API', [], 403)
        )) {
            return $response;
        }

        $result = $this->rateService->fetchAndStoreRates($user);

        if (! $result['success']) {
            return $this->errorResponse($result['message'], [], 500);
        }

        return $this->successResponse($result['rates'] ?? null, $result['message']);
    }

    /**
     * Get a specific currency rate.
     */
    public function show(string $currencyCode): JsonResponse
    {
        $rate = $this->rateService->getRateForCurrency($currencyCode);

        if (! $rate) {
            return $this->errorResponse("No rate found for {$currencyCode}", [], 404);
        }

        return $this->successResponse($rate);
    }

    /**
     * Override/Manually set rates for a currency.
     * Manager/Admin only.
     */
    public function apiOverride(OverrideRateRequest $request, string $currencyCode): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->rateService->overrideRate(
            $currencyCode,
            $validated['rate_buy'],
            $validated['rate_sell'],
            Auth::user()
        );

        return $this->successResponse($result, $result->message);
    }

    /**
     * Copy previous day's rates as today's rates.
     * Manager/Admin only.
     */
    public function copyPrevious(CopyPreviousRateRequest $request): JsonResponse
    {
        $user = Auth::user();

        if ($response = $this->ensureManagerOrAdminResponse(
            fn () => $this->errorResponse('Only managers and admins can copy previous rates', [], 403)
        )) {
            return $response;
        }

        $validated = $request->validated();

        $targetDate = $validated['date'] ?? now()->subDay()->toDateString();

        $result = $this->rateService->copyPreviousRates($targetDate);

        if (! $result['success']) {
            return $this->errorResponse($result['message'], [], 404);
        }

        return $this->successResponse($result['rates'] ?? null, $result['message']);
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

        return $this->successResponse($dates);
    }

    /**
     * Get rate history/trend for a currency.
     */
    public function history(RateHistoryRequest $request, string $currencyCode): JsonResponse
    {
        $days = $request->get('days', 30);

        $histories = ExchangeRateHistory::forCurrency($currencyCode)
            ->forDateRange(
                now()->subDays($days)->toDateString(),
                now()->toDateString()
            )
            ->orderBy('effective_date', 'desc')
            ->get();

        return $this->successResponse($histories);
    }

    /**
     * Check if all required rates are set.
     */
    public function checkSet(CheckRateSetRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->rateService->areAllRatesSet($validated['currencies']);

        return $this->successResponse(null, 'Rate check completed.', 200, [
            'all_set' => $result['all_set'],
            'missing' => $result['missing'],
        ]);
    }

    /**
     * Validate a submitted rate against current market rate.
     */
    public function validateRate(ValidateRateRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->rateService->validateTransactionRate(
            $validated['rate'],
            $validated['currency_code'],
            $validated['type']
        );

        return $this->successResponse(null, 'Rate validation completed.', 200, [
            'valid' => $result['valid'],
            'reason' => $result['reason'],
            'deviation_percent' => $result['deviation_percent'],
            'max_allowed' => $result['max_allowed'],
            'market_rate' => $result['market_rate'] ?? null,
            'submitted_rate' => $result['submitted_rate'] ?? null,
        ]);
    }
}
