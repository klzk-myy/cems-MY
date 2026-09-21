<?php

namespace App\Http\Controllers\System;

use App\Enums\Permission;
use App\Enums\TransactionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCurrencyRequest;
use App\Http\Requests\UpdateCurrencyRequest;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\CurrencyPosition;
use App\Models\Transaction;
use App\Services\Accounting\CurrencyAccountProvisioner;
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
        protected CurrencyAccountProvisioner $accountProvisioner,
    ) {}

    /**
     * List all currencies (including disabled) with status badges.
     */
    public function index(): View
    {
        $this->requirePermission(Permission::ManageCurrencies);

        $currencies = Currency::orderBy('code')->paginate(25);

        return view('system.currencies.index', compact('currencies'));
    }

    /**
     * Show the create-currency form.
     */
    public function create(): View
    {
        $this->requirePermission(Permission::ManageCurrencies);

        return view('system.currencies.create');
    }

    /**
     * Persist a new currency.
     */
    public function store(StoreCurrencyRequest $request): RedirectResponse
    {
        $this->requirePermission(Permission::ManageCurrencies);

        $validated = $request->validated();

        // The unique-among-active validation rule deliberately allows reuse of a
        // disabled currency's code, but `code` is the table's primary key, so a
        // disabled or soft-deleted row with the same code would collide on insert.
        if (Currency::withTrashed()->whereKey($validated['code'])->exists()) {
            return back()
                ->withErrors(['code' => 'A currency with this code already exists (it may be disabled). Re-enable it instead.'])
                ->withInput();
        }

        // auth()->id() is int|string|null; the cast must not turn a missing
        // actor into user id 0, which would violate the updated_by FK.
        $actorId = auth()->id();
        $actorId = $actorId === null ? null : (int) $actorId;

        [$currency, $branches, $glAccounts] = DB::transaction(function () use ($validated, $actorId) {
            $currency = Currency::create([
                ...$validated,
                'is_active' => true,
            ]);

            // Map the new currency into the accounting system: every active
            // trading branch gets a zero branch pool and a zero currency
            // position so the currency appears in stock/position views
            // immediately instead of waiting for lazy provisioning at first
            // transaction/counter open. Head offices are non-trading and hold
            // no foreign-currency stock.
            $branches = Branch::where('is_active', true)
                ->where('type', '!=', Branch::TYPE_HEAD_OFFICE)
                ->get();
            foreach ($branches as $branch) {
                $this->branchPoolService->getOrCreateForBranch($branch, $currency->code);
                $this->positionLockService->lock((string) $branch->id, $currency->code);
            }

            // Dedicated Cash/Inventory chart accounts plus cash.{CCY} and
            // inventory.{CCY} mapping rows so postings route to the
            // currency's own GL accounts instead of the pooled defaults.
            $glAccounts = $this->accountProvisioner->provision($currency, $actorId);

            return [$currency, $branches, $glAccounts];
        });

        $this->auditService->log(
            'currency_created',
            $actorId,
            'Currency',
            null,
            [],
            [
                'code' => $currency->code,
                'name' => $currency->name,
                'decimal_places' => $currency->decimal_places,
                'provisioned_branches' => $branches->count(),
                'gl_accounts' => $glAccounts,
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
        $this->requirePermission(Permission::ManageCurrencies);

        return view('system.currencies.edit', compact('currency'));
    }

    /**
     * Update the editable attributes of a currency.
     */
    public function update(UpdateCurrencyRequest $request, Currency $currency): RedirectResponse
    {
        $this->requirePermission(Permission::ManageCurrencies);

        $validated = $request->validated();

        $oldValues = [
            'name' => $currency->name,
            'symbol' => $currency->symbol,
            'decimal_places' => $currency->decimal_places,
            'rate_unit' => (string) $currency->rate_unit,
            'rate_inverse' => (bool) $currency->rate_inverse,
        ];

        $currency->update([
            ...$validated,
            'rate_inverse' => (bool) $validated['rate_inverse'],
        ]);

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
                'rate_unit' => (string) $currency->rate_unit,
                'rate_inverse' => (bool) $currency->rate_inverse,
            ]
        );

        // Quote-convention changes get the dedicated audit action used by the
        // other two entry points (index column, /rates/units) so all unit and
        // direction changes are traceable under one action name.
        if ($oldValues['rate_unit'] !== (string) $currency->rate_unit
            || $oldValues['rate_inverse'] !== (bool) $currency->rate_inverse) {
            $this->auditService->log(
                'rate_unit_changed',
                (int) auth()->id(),
                'Currency',
                null,
                [
                    'code' => $currency->code,
                    'rate_unit' => $oldValues['rate_unit'],
                    'rate_inverse' => $oldValues['rate_inverse'],
                ],
                [
                    'code' => $currency->code,
                    'rate_unit' => (string) $currency->rate_unit,
                    'rate_inverse' => (bool) $currency->rate_inverse,
                ]
            );
        }

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
        $this->requirePermission(Permission::ManageCurrencies);

        if (! $currency->is_active) {
            return redirect()->route('system.currencies.index')
                ->with('error', "Currency {$currency->code} is already disabled.");
        }

        $hasOpenTransactions = Transaction::query()
            ->where('currency_code', $currency->code)
            ->whereIn('status', [TransactionStatus::PendingApproval->value, TransactionStatus::PendingCancellation->value])
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

    /**
     * Re-enable a disabled currency. Provisioning runs inside the same
     * transaction so a currency disabled before dedicated GL accounts existed
     * (or one whose accounts were removed) gets its chart rows and mappings
     * back on the way in.
     */
    public function enable(Currency $currency): RedirectResponse
    {
        $this->requirePermission(Permission::ManageCurrencies);

        if ($currency->is_active) {
            return redirect()->route('system.currencies.index')
                ->with('error', "Currency {$currency->code} is already active.");
        }

        $actorId = auth()->id();
        $actorId = $actorId === null ? null : (int) $actorId;

        DB::transaction(function () use ($currency, $actorId) {
            $currency->update(['is_active' => true]);

            // Branches created while this currency was disabled never got
            // their pool/position rows (BranchService backfills active
            // currencies only) — re-enable restores the operational
            // footprint too. getOrCreateForBranch/lock are idempotent for
            // branches that already have the rows.
            $branches = Branch::where('is_active', true)
                ->where('type', '!=', Branch::TYPE_HEAD_OFFICE)
                ->get();
            foreach ($branches as $branch) {
                $this->branchPoolService->getOrCreateForBranch($branch, $currency->code);
                $this->positionLockService->lock((string) $branch->id, $currency->code);
            }

            $this->accountProvisioner->provision($currency, $actorId);

            $this->auditService->log(
                'currency_enabled',
                $actorId,
                'Currency',
                null,
                ['is_active' => false],
                ['code' => $currency->code, 'is_active' => true]
            );
        });

        return redirect()->route('system.currencies.index')
            ->with('success', "Currency {$currency->code} re-enabled.");
    }
}
