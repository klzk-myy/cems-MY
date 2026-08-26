<?php

namespace App\Http\Controllers;

use App\Exceptions\Domain\InvalidRateException;
use App\Http\Controllers\Concerns\EnsuresManagerOrAdmin;
use App\Http\Requests\OverrideRateRequest;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\ExchangeRateHistory;
use App\Models\User;
use App\Services\Transaction\RateManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RateController extends Controller
{
    use EnsuresManagerOrAdmin;

    public function __construct(
        protected RateManagementService $rateService
    ) {}

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

        $currencyCodes = collect($rates)->pluck('currency_code')->unique();
        $currencies = Currency::whereIn('code', $currencyCodes)
            ->pluck('name', 'code');

        return view('rates.index', [
            'rates' => $rates,
            'availableDates' => $availableDates,
            'currentBranch' => $branch,
            'canSelectBranch' => $user->role->isAdmin(),
            'branches' => $user->role->isAdmin()
                ? Branch::where('is_active', true)->orderBy('name')->get()
                : collect(),
            'currencies' => $currencies,
        ]);
    }

    public function override(OverrideRateRequest $request): RedirectResponse
    {
        $user = Auth::user();

        if (! $user->isManager()) {
            abort(403, 'Only managers and admins can override rates');
        }

        $validated = $request->validated();
        $currencyCode = $request->input('currency_code');

        if (empty($currencyCode)) {
            return back()->with('error', 'Currency code is required.')->withInput();
        }

        $branchId = $this->resolveBranchId($user, $request);

        try {
            $result = $this->rateService->overrideRate(
                $currencyCode,
                $validated['rate_buy'],
                $validated['rate_sell'],
                $user,
                $validated['reason'] ?? null,
                $branchId,
                $validated['effective_date'] ?? null
            );
        } catch (InvalidRateException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        if (! $result->success) {
            return back()->with('error', $result->message)->withInput();
        }

        return back()->with('success', $result->message);
    }

    public function copyPrevious(Request $request): RedirectResponse
    {
        $user = Auth::user();

        if (! $user->isManager()) {
            abort(403, 'Only managers and admins can copy rates');
        }

        $validated = $request->validate([
            'date' => 'nullable|date',
            'branch_id' => 'nullable|integer|exists:branches,id',
        ]);

        $targetDate = $validated['date'] ?? now()->subDay()->toDateString();
        $branchId = $this->resolveBranchId($user, $request);

        $result = $this->rateService->copyPreviousRates($targetDate, $branchId);

        if (! $result['success']) {
            return back()->with('error', $result['message']);
        }

        return back()->with('success', $result['message']);
    }

    protected function resolveBranchId(User $user, Request $request): ?int
    {
        if ($user->role->isAdmin() && $request->has('branch_id')) {
            return (int) $request->get('branch_id');
        }

        if ($user->role->isManager()) {
            return $user->branch_id;
        }

        return null;
    }

    /**
     * Required by EnsuresManagerOrAdmin::requireManagerOrAdminResponse().
     * Mirrors the ApiResponse::errorResponse() shape used by API controllers.
     */
    private function errorResponse(string $message, array $errors = [], int $code = 400, array $meta = []): JsonResponse
    {
        return response()->json(array_merge([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $meta), $code);
    }
}
