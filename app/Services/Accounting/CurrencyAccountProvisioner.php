<?php

namespace App\Services\Accounting;

use App\Enums\AccountCode;
use App\Enums\AccountType;
use App\Models\AccountMapping;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Services\AuditService;
use App\Services\System\CacheInvalidationService;
use App\Services\System\CacheKeys;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Currency Account Provisioner
 *
 * Gives a currency its own GL footprint: dedicated Cash and Inventory
 * chart accounts plus the account_mappings rows (cash.{CCY},
 * inventory.{CCY}) that route postings to them.
 *
 * Resolution order per leg: an existing mapping row always wins (a
 * remapped account is never silently replaced) → the dedicated enum
 * account when one exists (USD → 1001/2001, …) → the lowest free code in
 * the allocation window. Idempotent and safe to call for currencies that
 * are already provisioned.
 */
class CurrencyAccountProvisioner
{
    /**
     * Per-leg provisioning spec: the AccountCode case-name prefix for
     * enum-covered currencies, the account_class the cash-flow report
     * groups on, the account-name prefix for created rows, and the
     * free-code allocation window (1050 = petty cash, 1100+ = bank,
     * 2100+ = receivables).
     *
     * @var array<string, array{enum_prefix: string, class: string, name: string, range: array{0: int, 1: int}}>
     */
    private const LEGS = [
        'cash' => [
            'enum_prefix' => 'CASH_',
            'class' => 'Cash',
            'name' => 'Cash',
            'range' => [1008, 1049],
        ],
        'inventory' => [
            'enum_prefix' => 'FOREX_INVENTORY_',
            'class' => 'Inventory',
            'name' => 'Forex Inventory',
            'range' => [2008, 2099],
        ],
    ];

    /** How many allocation retries before a leg gives up (concurrent claims). */
    private const MAX_ALLOCATION_ATTEMPTS = 3;

    public function __construct(
        protected CacheInvalidationService $cacheInvalidationService,
        protected AuditService $auditService,
    ) {}

    /**
     * Provision both legs for a currency and return the resolved account
     * codes. The base currency is skipped — its legs are covered by the
     * fixed cash.myr / inventory.default mappings.
     *
     * Both legs and the audit event commit together: callers that already
     * wrap us in a transaction (currency store, setup) join the outer
     * transaction; the standalone provision endpoint gets its atomicity
     * here so a mid-way failure cannot leave a half-provisioned currency.
     *
     * @return array{cash: ?string, inventory: ?string}
     */
    public function provision(Currency $currency, ?int $userId = null): array
    {
        $code = $currency->code;

        if ($code === Currency::baseCurrency() || ! Schema::hasTable('account_mappings')) {
            return ['cash' => null, 'inventory' => null];
        }

        return DB::transaction(function () use ($code, $userId) {
            $legs = [
                'cash' => $this->provisionLeg($code, 'cash', $userId),
                'inventory' => $this->provisionLeg($code, 'inventory', $userId),
            ];

            $resolved = [
                'cash' => $legs['cash']['code'] ?? null,
                'inventory' => $legs['inventory']['code'] ?? null,
            ];

            // Only audit an actual state change — an idempotent re-run that
            // merely re-read existing mappings must not write a
            // "provisioned" event that implies something changed.
            $created = ($legs['cash']['created'] ?? false)
                || ($legs['inventory']['created'] ?? false);

            if ($created) {
                $this->auditService->log(
                    'currency_accounts_provisioned',
                    $userId,
                    'Currency',
                    null,
                    [],
                    ['code' => $code, 'accounts' => $resolved],
                );
            }

            return $resolved;
        });
    }

    /**
     * Resolve or create one leg for a currency. `code` is the mapped account
     * (an existing mapping always wins), `created` reports whether a mapping
     * row was actually written; `code` is null when the allocation window
     * is exhausted.
     *
     * @param  'cash'|'inventory'  $prefix
     * @return array{code: ?string, created: bool}
     */
    private function provisionLeg(string $code, string $prefix, ?int $userId): array
    {
        $key = "{$prefix}.{$code}";

        $existing = AccountMapping::where('key', $key)->value('account_code');

        if ($existing !== null) {
            return ['code' => $existing, 'created' => false];
        }

        $accountCode = $this->resolveAccountCode($code, $prefix);

        if ($accountCode === null) {
            return ['code' => null, 'created' => false];
        }

        $mapping = AccountMapping::firstOrCreate(
            ['key' => $key],
            [
                'account_code' => $accountCode,
                'description' => "Auto-provisioned for currency {$code}",
                'updated_by' => $userId,
            ],
        );

        $this->cacheInvalidationService->invalidate(CacheKeys::AccountMappingsTag->value);

        // Report the persisted code — a concurrent provision may have won
        // the unique-key race with a different account.
        return ['code' => $mapping->account_code, 'created' => true];
    }

    /**
     * Pick the account for one leg. The canonical enum account is used when
     * it exists and is usable, created when absent; other currencies fall
     * back to the lowest free code in the allocation window.
     *
     * A pre-existing row at a candidate code is only reused for the enum
     * account — and only when it is an active Asset, the same rule the
     * management page enforces via AccountMappingService::validateAccount.
     * Any other pre-existing row means a concurrently claimed allocation
     * or an unusable enum-coded row; the next free window code is tried
     * instead so a currency never silently lands on a mislabeled or
     * journal-rejecting account.
     *
     * @param  'cash'|'inventory'  $prefix
     */
    private function resolveAccountCode(string $code, string $prefix): ?string
    {
        $spec = self::LEGS[$prefix];

        $enumAccount = collect(AccountCode::cases())
            ->firstWhere('name', $spec['enum_prefix'].$code);

        $candidate = $enumAccount instanceof AccountCode ? $enumAccount->value : null;

        for ($attempt = 0; $attempt < self::MAX_ALLOCATION_ATTEMPTS; $attempt++) {
            $candidate ??= $this->allocateCode($spec['range']);

            if ($candidate === null) {
                return null;
            }

            $account = ChartOfAccount::firstOrCreate(
                ['account_code' => $candidate],
                [
                    'account_name' => $enumAccount instanceof AccountCode && $candidate === $enumAccount->value
                        ? $enumAccount->description()
                        : "{$spec['name']} ({$code})",
                    'account_type' => AccountType::Asset,
                    'account_class' => $spec['class'],
                    'is_active' => true,
                ],
            );

            if ($account->wasRecentlyCreated || $this->isUsableEnumAccount($account, $enumAccount, $candidate)) {
                return $candidate;
            }

            $candidate = null;
        }

        return null;
    }

    /**
     * The enum's canonical code may be reused only when the chart row is
     * active and Asset-typed.
     */
    private function isUsableEnumAccount(ChartOfAccount $account, ?AccountCode $enumAccount, string $candidate): bool
    {
        return $enumAccount instanceof AccountCode
            && $candidate === $enumAccount->value
            && $account->is_active
            && $account->account_type === AccountType::Asset;
    }

    /**
     * Lowest unused account code inside the allocation window.
     *
     * @param  array{0: int, 1: int}  $range
     */
    private function allocateCode(array $range): ?string
    {
        [$min, $max] = $range;

        $taken = ChartOfAccount::whereBetween('account_code', [(string) $min, (string) $max])
            ->pluck('account_code')
            ->all();

        for ($i = $min; $i <= $max; $i++) {
            if (! in_array((string) $i, $taken, true)) {
                return (string) $i;
            }
        }

        return null;
    }
}
