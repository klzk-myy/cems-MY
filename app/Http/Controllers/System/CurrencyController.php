<?php

namespace App\Http\Controllers\System;

use App\Enums\TransactionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCurrencyRequest;
use App\Http\Requests\UpdateCurrencyRequest;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\CurrencyPosition;
use App\Models\Transaction;
use App\Services\Accounting\CurrencyPositionLockService;
use App\Services\AuditService;
use App\Services\Branch\BranchPoolService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * CurrencyController
 *
 * Admin management UI for currencies (plan WS-C1). Currencies are seeded
 * during setup; this controller allows post-setup creation, edits and
 * soft-disabling. Disabling hides the currency from all transaction form
 * selects (they all filter is_active = true) while historical rows keep
 * rendering.
 *
 * Business rules:
 * - code is an immutable ISO alpha-3 primary key; only created, never edited.
 * - disable is blocked while open transactions (PendingApproval /
 *   PendingCancellation) reference the currency or any CurrencyPosition
 *   holds a non-zero quantity.
 */
class CurrencyController extends Controller
{
    public function __construct(
        protected AuditService $auditService,
        protected BranchPoolService $branchPoolService,
        protected CurrencyPositionLockService $positionLockService,
    ) {}

    /**
     * List all currencies (including disabled) with status badges.
     */
    public function index(): View
    {
        $this->requireAdmin();

        $currencies = Currency::orderBy('code')->get();

        return view('system.currencies.index', compact('currencies'));
    }

    /**
     * Show the create-currency form.
     */
    public function create(): View
    {
        $this->requireAdmin();

        return view('system.currencies.create');
    }

    /**
     * Persist a new currency.
     */
    public function store(StoreCurrencyRequest $request): RedirectResponse
    {
        $this->requireAdmin();

        $validated = $request->validated();

        // The unique-among-active validation rule deliberately allows reuse of a
        // disabled currency's code, but `code` is the table's primary key, so a
        // disabled or soft-deleted row with the same code would collide on insert.
        if (Currency::withTrashed()->whereKey($validated['code'])->exists()) {
            return back()
                ->withErrors(['code' => 'A currency with this code already exists (it may be disabled). Re-enable it instead.'])
                ->withInput();
        }

        [$currency, $branches] = DB::transaction(function () use ($validated) {
            $currency = Currency::create([
                ...$validated,
                'is_active' => true,
            ]);

            // Map the new currency into the accounting system: every active
            // branch gets a zero branch pool and a zero currency position so the
            // currency appears in stock/position views immediately instead of
            // waiting for lazy provisioning at first transaction/counter open.
            $branches = Branch::where('is_active', true)->get();
            foreach ($branches as $branch) {
                $this->branchPoolService->getOrCreateForBranch($branch, $currency->code);
                $this->positionLockService->lock((string) $branch->id, $currency->code);
            }

            return [$currency, $branches];
        });

        $this->auditService->log(
            'currency_created',
            (int) auth()->id(),
            'Currency',
            null,
            [],
            [
                'code' => $currency->code,
                'name' => $currency->name,
                'decimal_places' => $currency->decimal_places,
                'provisioned_branches' => $branches->count(),
            ]
        );

        return redirect()->route('system.currencies.index')
            ->with('success', "Currency {$currency->code} created successfully.");
    }

    /**
     * Show the edit form for a currency.
     */
    public function edit(Currency $currency): View
    {
        $this->requireAdmin();

        return view('system.currencies.edit', compact('currency'));
    }

    /**
     * Update the editable attributes of a currency.
     */
    public function update(UpdateCurrencyRequest $request, Currency $currency): RedirectResponse
    {
        $this->requireAdmin();

        $validated = $request->validated();

        $oldValues = [
            'name' => $currency->name,
            'symbol' => $currency->symbol,
            'decimal_places' => $currency->decimal_places,
        ];

        $currency->update($validated);

        $this->auditService->log(
            'currency_updated',
            (int) auth()->id(),
            'Currency',
            null,
            $oldValues,
            [
                'code' => $currency->code,
                'name' => $currency->name,
                'symbol' => $currency->symbol,
                'decimal_places' => $currency->decimal_places,
            ]
        );

        return redirect()->route('system.currencies.index')
            ->with('success', "Currency {$currency->code} updated successfully.");
    }

    /**
     * Soft-disable a currency.
     *
     * Blocked while open transactions reference the currency or a non-zero
     * position exists anywhere. Disabled currencies disappear from every form
     * select (all selects filter is_active = true) but historical records are
     * unaffected.
     */
    public function disable(Currency $currency): RedirectResponse
    {
        $this->requireAdmin();

        if (! $currency->is_active) {
            return redirect()->route('system.currencies.index')
                ->with('error', "Currency {$currency->code} is already disabled.");
        }

        $hasOpenTransactions = Transaction::query()
            ->where('currency_code', $currency->code)
            ->whereIn('status', [TransactionStatus::PendingApproval, TransactionStatus::PendingCancellation])
            ->exists();

        if ($hasOpenTransactions) {
            return redirect()->route('system.currencies.index')
                ->with('error', "Cannot disable {$currency->code}: open transactions still reference this currency.");
        }

        $hasOpenPositions = CurrencyPosition::query()
            ->where('currency_code', $currency->code)
            ->where('quantity', '!=', 0)
            ->exists();

        if ($hasOpenPositions) {
            return redirect()->route('system.currencies.index')
                ->with('error', "Cannot disable {$currency->code}: non-zero currency positions still exist.");
        }

        $currency->update(['is_active' => false]);

        $this->auditService->log(
            'currency_disabled',
            (int) auth()->id(),
            'Currency',
            null,
            ['is_active' => true],
            ['code' => $currency->code, 'is_active' => false]
        );

        return redirect()->route('system.currencies.index')
            ->with('success', "Currency {$currency->code} disabled.");
    }
}
