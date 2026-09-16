<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Exceptions\Domain\InvalidRateException;
use App\Http\Requests\OverrideRateRequest;
use App\Http\Requests\UpdateRateUnitRequest;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\ExchangeRateHistory;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Transaction\RateManagementService;
use App\ValueObjects\QuoteConvention;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RateController extends Controller
{
    public function __construct(
        protected RateManagementService $rateService,
        protected AuditService $auditService
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

        // Display each card in the currency's configured quote convention, so
        // a card written under an older unit/direction still shows in today's
        // terms.
        foreach ($rates as &$rate) {
            $rate['display_unit'] = $rate['currency_rate_unit'];
            $rate['display_inverse'] = $rate['currency_rate_inverse'];
            $stored = new QuoteConvention((int) $rate['rate_unit'], (bool) $rate['rate_inverse']);
            $display = $rate['currency_convention'] ?? new QuoteConvention;
            $rate['display_buy'] = $stored->reQuoteInto((string) $rate['rate_buy'], $display);
            $rate['display_sell'] = $stored->reQuoteInto((string) $rate['rate_sell'], $display);
        }
        unset($rate);

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

    /**
     * Quote-unit configuration for every active currency: the price multiply
     * (currencies.rate_unit) plus the current card shown in that unit.
     */
    public function units(Request $request): View
    {
        $user = Auth::user();
        $branchId = $this->resolveBranchId($user, $request);

        $cards = ExchangeRate::query()->active()
            ->when($branchId !== null, fn ($q) => $q
                ->where(fn ($inner) => $inner->forBranch($branchId)->orWhereNull('branch_id')))
            ->get()
            ->sortBy(fn (ExchangeRate $rate) => $rate->branch_id === $branchId ? 0 : 1)
            ->unique('currency_code')
            ->keyBy('currency_code');

        $currencies = Currency::where('is_active', true)
            ->orderBy('code')
            ->get()
            ->map(function (Currency $currency) use ($cards) {
                $card = $cards->get($currency->code);
                $targetConvention = $currency->quoteConvention();

                return [
                    'code' => $currency->code,
                    'name' => $currency->name,
                    'symbol' => $currency->symbol,
                    'rate_unit' => (string) $targetConvention->unit,
                    'rate_inverse' => $targetConvention->inverse,
                    'rate_buy' => $card === null ? null : $card->quoteConvention()
                        ->reQuoteInto((string) $card->rate_buy, $targetConvention),
                    'rate_sell' => $card === null ? null : $card->quoteConvention()
                        ->reQuoteInto((string) $card->rate_sell, $targetConvention),
                    'has_card' => $card !== null,
                    'fetched_at' => $card?->fetched_at?->format('Y-m-d H:i'),
                ];
            });

        $branch = $branchId ? Branch::find($branchId) : null;

        return view('rates.units', [
            'currencies' => $currencies,
            'currentBranch' => $branch,
            'canSelectBranch' => $user->role->isAdmin(),
            'branches' => $user->role->isAdmin()
                ? Branch::where('is_active', true)->orderBy('name')->get()
                : collect(),
        ]);
    }

    /**
     * Update a currency's quote unit and (optionally) its unit-quoted card.
     */
    public function updateUnits(UpdateRateUnitRequest $request): RedirectResponse
    {
        $user = Auth::user();

        if (! $user->role->canPerform(Permission::AccessRates)) {
            abort(403, 'You do not have permission to update rate units.');
        }

        $validated = $request->validated();
        $currency = Currency::where('code', $validated['currency_code'])->firstOrFail();
        $branchId = $this->resolveBranchId($user, $request);

        $newUnit = (string) $validated['rate_unit'];
        $newInverse = (bool) $validated['rate_inverse'];
        $oldUnit = (string) $currency->rate_unit;
        $oldInverse = (bool) $currency->rate_inverse;

        // rate_unit / rate_inverse live on the global currencies row — a
        // branch manager's convention change would affect every branch, so
        // only admins may change it; managers can still save branch cards.
        if (! $user->isAdmin() && ($newUnit !== $oldUnit || $newInverse !== $oldInverse)) {
            return back()->with('error', 'Only admins can change a currency\'s quote convention.')->withInput();
        }

        // Convention update + card save are one atomic statement: submitted
        // buy/sell are quoted under the NEW convention, so the currency is
        // updated first and overrideRate resolves it. A failed card save
        // throws to roll back the convention change too.
        try {
            $result = DB::transaction(function () use ($validated, $currency, $user, $branchId, $newUnit, $newInverse, $oldUnit, $oldInverse) {
                $conventionChanged = $newUnit !== $oldUnit || $newInverse !== $oldInverse;

                if ($conventionChanged) {
                    $currency->update([
                        'rate_unit' => (int) $newUnit,
                        'rate_inverse' => $newInverse,
                    ]);
                }

                $cardMessage = null;
                if (isset($validated['rate_buy'], $validated['rate_sell'])) {
                    $result = $this->rateService->overrideRate(
                        $currency->code,
                        $validated['rate_buy'],
                        $validated['rate_sell'],
                        $user,
                        $validated['reason'] ?? null,
                        $branchId
                    );

                    if (! $result->success) {
                        throw new InvalidRateException($result->message);
                    }

                    $cardMessage = "card saved — {$result->message}";
                }

                if ($conventionChanged) {
                    $this->auditService->log(
                        'rate_unit_changed',
                        $user->id,
                        'Currency',
                        null,
                        [
                            'code' => $currency->code,
                            'rate_unit' => $oldUnit,
                            'rate_inverse' => $oldInverse,
                        ],
                        [
                            'code' => $currency->code,
                            'rate_unit' => $newUnit,
                            'rate_inverse' => $newInverse,
                            'branch_id' => $branchId,
                        ]
                    );
                }

                return $cardMessage;
            });
        } catch (InvalidRateException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        $conventionChanged = $newUnit !== $oldUnit || $newInverse !== $oldInverse;
        $conventionMessage = $conventionChanged
            ? "quote convention updated to {$newUnit}".($newInverse ? ' (inverse)' : '')
            : null;
        $parts = array_filter([$result, $conventionMessage]);

        return back()->with('success', $parts
            ? "{$currency->code}: ".implode('; ', $parts)
            : "{$currency->code}: no changes to save.");
    }

    public function override(OverrideRateRequest $request): RedirectResponse
    {
        $user = Auth::user();

        if (! $user->role->canPerform(Permission::AccessRates)) {
            abort(403, 'You do not have permission to override rates.');
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

        if (! $user->role->canPerform(Permission::AccessRates)) {
            abort(403, 'You do not have permission to copy rates.');
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

        // Branch scope: non-admin roles always operate on their own branch's
        // rates; an unassigned user falls back to company-wide (null).
        return $user->branch_id;
    }
}
