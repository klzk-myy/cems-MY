<?php

namespace App\Http\Controllers\Accounting;

use App\Enums\AccountType;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\UpdateAccountMappingsRequest;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Services\Accounting\AccountMappingService;
use App\Services\Accounting\CurrencyAccountProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * AccountMappingController
 *
 * Management page for the business-event → chart-of-accounts mapping stored
 * in account_mappings. Shows every fixed mapping key grouped by section with
 * its effective account (table row or enum default) and any per-currency
 * overrides. Edits go through AccountMappingService so chart validation and
 * audit logging live in one place. Route middleware enforces
 * role:manage_account_mappings; the POST additionally requires
 * password.confirm, matching other admin mutations.
 */
class AccountMappingController extends Controller
{
    public function __construct(
        protected AccountMappingService $accountMappingService,
        protected CurrencyAccountProvisioner $accountProvisioner,
    ) {}

    public function index(): View
    {
        $this->requirePermission(Permission::ManageAccountMappings);

        $effective = $this->accountMappingService->effectiveMappings();
        $mappingsInstalled = $effective['installed'];

        $sections = collect($effective['fixed'])
            ->groupBy(fn (array $row) => $row['key']->section());

        /** @var Collection<string, Collection<int, ChartOfAccount>> $accountsByType */
        $accountsByType = ChartOfAccount::where('is_active', true)
            ->orderBy('account_code')
            ->get()
            ->groupBy(fn (ChartOfAccount $a) => $a->account_type instanceof AccountType
                ? $a->account_type->value
                : (string) $a->account_type);

        $assetAccounts = $accountsByType->get('Asset', collect());

        // Per-currency overrides: one row per active non-base currency per
        // prefix (cash.{CCY}, inventory.{CCY}); empty select = remove override.
        $currencyRows = collect($effective['currency'])->keyBy('key');

        $currencies = Currency::where('is_active', true)
            ->where('code', '!=', Currency::baseCurrency())
            ->orderBy('code')
            ->get();

        // Active currencies missing either per-currency leg can be given
        // dedicated chart accounts from this page. Provisioning is
        // idempotent, so a currency with only one leg (e.g. a manual
        // cash.{CCY} override) is listed to complete the pair.
        $unprovisionedCurrencies = $mappingsInstalled
            ? $currencies->filter(
                fn (Currency $c) => ! $currencyRows->has("cash.{$c->code}")
                    || ! $currencyRows->has("inventory.{$c->code}")
            )->values()
            : collect();

        return view('accounting.mappings.index', compact(
            'sections',
            'accountsByType',
            'assetAccounts',
            'currencyRows',
            'currencies',
            'unprovisionedCurrencies',
            'mappingsInstalled',
        ));
    }

    /**
     * Provision dedicated Cash/Inventory chart accounts and the
     * cash.{CCY} / inventory.{CCY} mapping rows for a currency that does
     * not have any yet.
     */
    public function provision(Currency $currency): RedirectResponse
    {
        $this->requirePermission(Permission::ManageAccountMappings);

        if (! $currency->is_active) {
            return redirect()
                ->route('accounting.mappings.index')
                ->with('error', "Cannot provision {$currency->code}: the currency is disabled.");
        }

        // auth()->id() is int|string|null; the cast must not turn a missing
        // actor into user id 0, which would violate the updated_by FK.
        $actorId = auth()->id();

        $resolved = $this->accountProvisioner->provision(
            $currency,
            $actorId === null ? null : (int) $actorId
        );

        if ($resolved['cash'] === null && $resolved['inventory'] === null) {
            return redirect()
                ->route('accounting.mappings.index')
                ->with('success', "No dedicated accounts were needed for {$currency->code}.");
        }

        return redirect()
            ->route('accounting.mappings.index')
            ->with('success', "Provisioned {$currency->code} GL accounts — cash: "
                .($resolved['cash'] ?? 'none').', inventory: '.($resolved['inventory'] ?? 'none').'.');
    }

    public function update(UpdateAccountMappingsRequest $request): RedirectResponse
    {
        $this->requirePermission(Permission::ManageAccountMappings);

        $this->accountMappingService->update(
            $request->validatedMappings(),
            $request->user()->id,
            $request->validated('reason'),
        );

        return redirect()
            ->route('accounting.mappings.index')
            ->with('success', 'Account mappings updated. New postings will use the updated accounts.');
    }
}
