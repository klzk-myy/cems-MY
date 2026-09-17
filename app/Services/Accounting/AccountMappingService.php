<?php

namespace App\Services\Accounting;

use App\Enums\AccountMappingKey;
use App\Enums\AccountType;
use App\Exceptions\Domain\AccountingPeriodException;
use App\Models\AccountMapping;
use App\Models\ChartOfAccount;
use App\Services\AuditService;
use App\Services\System\CacheInvalidationService;
use App\Services\System\CacheKeys;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Account Mapping Service
 *
 * Single resolution point for "which GL account does posting path X use".
 * Reads the account_mappings table, cached per key; falls back to the
 * AccountMappingKey enum default when no row exists so a missing seed can
 * never break a posting path. Dynamic per-currency keys (cash.{CCY},
 * inventory.{CCY}) fall back to their *.default key, then the enum default.
 */
class AccountMappingService
{
    private const CACHE_TTL = 300;

    public function __construct(
        protected CacheInvalidationService $cacheInvalidationService,
        protected AuditService $auditService,
    ) {}

    /**
     * Resolve a fixed mapping key to a chart account code.
     */
    public function code(AccountMappingKey $key): string
    {
        return $this->resolve($key->value) ?? $key->defaultAccount()->value;
    }

    /**
     * Resolve a per-currency key: '{prefix}.{CCY}' → '{prefix}.default' →
     * the enum default for that default key.
     *
     * @param  string  $prefix  'cash' or 'inventory'
     */
    public function forCurrency(string $prefix, string $currencyCode): string
    {
        $resolved = $this->resolve("{$prefix}.{$currencyCode}");

        if ($resolved !== null) {
            return $resolved;
        }

        $defaultKey = AccountMappingKey::tryFrom("{$prefix}.default");

        if ($defaultKey === null) {
            throw new AccountingPeriodException("Unknown account mapping prefix '{$prefix}'");
        }

        return $this->resolve($defaultKey->value) ?? $defaultKey->defaultAccount()->value;
    }

    /**
     * Every fixed key with its effective account code (table row or enum
     * default) plus any dynamic per-currency rows, for the management page.
     * When the account_mappings table is absent (install-mappings not run
     * yet) the page still renders: every key shows its enum default and the
     * dynamic set is empty, matching what posting paths resolve.
     *
     * @return array{fixed: array<int, array{key: AccountMappingKey, account_code: string, is_default: bool}>, currency: array<int, AccountMapping>, installed: bool}
     */
    public function effectiveMappings(): array
    {
        $installed = Schema::hasTable('account_mappings');

        $rows = $installed ? AccountMapping::query()->get() : collect();

        $fixed = [];
        foreach (AccountMappingKey::cases() as $key) {
            $row = $rows->firstWhere('key', $key->value);
            $effective = $row !== null ? $row->account_code : $key->defaultAccount()->value;
            $fixed[] = [
                'key' => $key,
                'account_code' => $effective,
                // "Default" means the effective value is the enum default —
                // seeded rows exist for every key, so a missing row is not
                // the discriminator.
                'is_default' => $effective === $key->defaultAccount()->value,
            ];
        }

        $enumKeys = array_map(fn (AccountMappingKey $k) => $k->value, AccountMappingKey::cases());

        return [
            'fixed' => $fixed,
            'currency' => $installed
                ? AccountMapping::query()
                    ->whereNotIn('key', $enumKeys)
                    ->orderBy('key')
                    ->get()
                    ->all()
                : [],
            'installed' => $installed,
        ];
    }

    /**
     * Persist mapping changes. Each entry is validated against the chart and
     * the key's expected account type; every change is audit-logged with
     * old/new values, then the resolution cache is flushed. A null/empty
     * code on a dynamic per-currency key removes the override (fixed enum
     * keys can never be unset — a posting path must always resolve).
     *
     * @param  array<string, string|null>  $mappings  key => account_code
     */
    public function update(array $mappings, ?int $userId, ?string $reason = null): void
    {
        if (! Schema::hasTable('account_mappings')) {
            throw new AccountingPeriodException('Account mappings are not installed on this database — run accounting:install-mappings first');
        }

        DB::transaction(function () use ($mappings, $userId, $reason) {
            foreach ($mappings as $key => $accountCode) {
                $key = (string) $key;
                $existing = AccountMapping::where('key', $key)->first();

                if ($accountCode === null || $accountCode === '') {
                    if (AccountMappingKey::tryFrom($key) !== null) {
                        throw new AccountingPeriodException("Fixed mapping '{$key}' cannot be unset — every posting path must resolve to an account");
                    }

                    if ($existing !== null) {
                        $existing->delete();

                        $this->auditService->log(
                            'account_mapping_removed',
                            $userId,
                            'AccountMapping',
                            $existing->id,
                            ['key' => $key, 'account_code' => $existing->account_code],
                            ['key' => $key, 'reason' => $reason],
                        );
                    }

                    continue;
                }

                $this->validateAccount($key, $accountCode);

                $old = $existing?->account_code;

                if ($old === $accountCode) {
                    continue;
                }

                $row = AccountMapping::updateOrCreate(
                    ['key' => $key],
                    [
                        'account_code' => $accountCode,
                        'description' => $existing !== null
                            ? $existing->description
                            : AccountMappingKey::tryFrom($key)?->usedBy(),
                        'updated_by' => $userId,
                    ]
                );

                $this->auditService->log(
                    'account_mapping_updated',
                    $userId,
                    'AccountMapping',
                    $row->id,
                    ['key' => $key, 'account_code' => $old],
                    ['key' => $key, 'account_code' => $accountCode, 'reason' => $reason],
                );
            }
        });

        $this->cacheInvalidationService->invalidate(CacheKeys::AccountMappingsTag->value);
    }

    /**
     * A mapped account must exist, be active, and — for known keys — carry
     * the account type the posting path assumes. Dynamic per-currency keys
     * (cash.{CCY}, inventory.{CCY}) always hold Asset accounts.
     */
    public function validateAccount(string $key, string $accountCode): void
    {
        $account = ChartOfAccount::where('account_code', $accountCode)->first();

        if (! $account) {
            throw new AccountingPeriodException("Account '{$accountCode}' does not exist in the chart of accounts");
        }

        if (! $account->is_active) {
            throw new AccountingPeriodException("Account '{$accountCode}' is inactive");
        }

        $expected = AccountMappingKey::tryFrom($key)?->expectedType()
            ?? (preg_match('/^(cash|inventory)\.[A-Z]{3}$/', $key) === 1 ? AccountType::Asset : null);

        if ($expected === null) {
            throw new AccountingPeriodException("Unknown account mapping key '{$key}'");
        }

        // account_type is cast to AccountType on the model.
        if ($account->account_type !== $expected) {
            throw new AccountingPeriodException(
                "Mapping '{$key}' requires a {$expected->label()} account; '{$accountCode}' is {$account->account_type->label()}"
            );
        }
    }

    /**
     * Cached table lookup returning the stored code or null when unmapped.
     * The hasTable check inside the callback keeps posting paths alive on
     * databases where `accounting:install-mappings` has not run yet —
     * resolution silently falls back to the enum defaults.
     */
    protected function resolve(string $key): ?string
    {
        $callback = fn () => Schema::hasTable('account_mappings')
            ? AccountMapping::where('key', $key)->value('account_code')
            : null;

        return $this->cacheInvalidationService->supportsTags()
            ? Cache::tags([CacheKeys::AccountMappingsTag->value])
                ->remember(CacheKeys::accountMapping($key), self::CACHE_TTL, $callback)
            : Cache::remember(CacheKeys::accountMapping($key), self::CACHE_TTL, $callback);
    }
}
