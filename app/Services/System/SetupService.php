<?php

namespace App\Services\System;

use App\Enums\AccountingPeriodStatus;
use App\Enums\AccountingPeriodType;
use App\Enums\AccountMappingKey;
use App\Enums\CounterStatus;
use App\Enums\FiscalYearStatus;
use App\Enums\UserRole;
use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\BranchPool;
use App\Models\ChartOfAccount;
use App\Models\Counter;
use App\Models\Currency;
use App\Models\CurrencyPosition;
use App\Models\ExchangeRate;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\PasswordHistory;
use App\Models\User;
use App\Rules\PasswordComplexityRule;
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\AccountMappingService;
use App\Services\Accounting\CurrencyAccountProvisioner;
use App\ValueObjects\QuoteConvention;
use Database\Seeders\SchemaSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SetupService
{
    public function __construct(
        protected MathService $mathService,
        protected AccountingService $accountingService,
        protected AccountMappingService $accountMappingService,
        protected CurrencyAccountProvisioner $accountProvisioner,
    ) {}

    /**
     * Persist the immutable setup-completed marker.
     *
     * Must be invoked inside the setup transaction so that a failure to record
     * the marker rolls back the entire setup (fail-closed).
     */
    public function markSetupComplete(): void
    {
        DB::table('setup_state')->updateOrInsert(
            ['id' => 1],
            [
                'setup_completed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    /**
     * Whether the immutable setup-completed marker is present.
     *
     * Returns false when the table does not exist yet (pre-migration installs)
     * so the data-derived fallback in EnsureSetupAccessible still applies.
     */
    public function isCompleted(): bool
    {
        try {
            return DB::table('setup_state')
                ->whereNotNull('setup_completed_at')
                ->exists();
        } catch (QueryException $e) {
            return false;
        }
    }

    /**
     * Clear the marker. Only reachable through the admin-gated,
     * non-production reset endpoint; SchemaSeeder already drops the table,
     * this is belt-and-braces for partial failures.
     */
    public function clearCompleted(): void
    {
        try {
            DB::table('setup_state')->delete();
        } catch (QueryException $e) {
            // Table absent - nothing to clear.
        }
    }

    /**
     * Normalize the step-3 custom currency rows into a clean list of
     * ['code' => ..., 'name' => ..., 'symbol' => ...] entries.
     *
     * Accepts the step-3 validated payload or the stored session slice.
     * Falls back to the legacy single-field keys (custom_currency_*) so a
     * wizard session started before the multi-row form still completes.
     *
     * @param  array<string, mixed>  $currenciesStep
     * @return array<int, array{code: string, name: string, symbol: string}>
     */
    public function customCurrencyRows(array $currenciesStep): array
    {
        $rows = collect((array) ($currenciesStep['custom_currencies'] ?? []))
            ->map(fn ($row) => [
                'code' => strtoupper(trim((string) ($row['code'] ?? ''))),
                'name' => trim((string) ($row['name'] ?? '')),
                'symbol' => trim((string) ($row['symbol'] ?? '')),
            ])
            ->filter(fn ($row) => $row['code'] !== '');

        $legacy = strtoupper(trim((string) ($currenciesStep['custom_currency_code'] ?? '')));
        if ($legacy !== '' && $rows->doesntContain('code', $legacy)) {
            $rows->push([
                'code' => $legacy,
                'name' => trim((string) ($currenciesStep['custom_currency_name'] ?? '')),
                'symbol' => trim((string) ($currenciesStep['custom_currency_symbol'] ?? '')),
            ]);
        }

        return $rows->unique('code')->values()->all();
    }

    public function seedCoreData(array $config): void
    {
        $this->validateAdminPassword($config['admin_password'] ?? '');

        // Route through the password mutator: User::creating rejects any
        // creation attempt whose password_hash is still empty.
        $admin = new User([
            'username' => $config['admin_username'] ?? 'admin',
            'email' => $config['admin_email'],
            'password' => $config['admin_password'],
            'mfa_enabled' => false,
            'is_active' => true,
        ]);

        $admin->role = UserRole::Admin;
        $admin->save();

        // Seed history with the initial hash so reuse prevention covers it.
        PasswordHistory::record($admin->id, $admin->password_hash);

        Artisan::call('db:seed', [
            '--class' => 'CurrencySeeder',
            '--force' => true,
        ]);

        Artisan::call('db:seed', [
            '--class' => 'EnhancedChartOfAccountsSeeder',
            '--force' => true,
        ]);

        Branch::create([
            'code' => 'HQ',
            'name' => $config['business_name'].' - Head Office',
            'type' => 'head_office',
            'is_active' => true,
            'is_main' => true,
        ]);

        $this->ensureFiscalYearAndPeriods();
    }

    /**
     * Ensure the current fiscal year and monthly accounting periods exist
     * and are open. Shared by both setup paths (quick setup and step
     * wizard) so accounting preconditions can never diverge between them.
     */
    public function ensureFiscalYearAndPeriods(): void
    {
        Artisan::call('db:seed', [
            '--class' => 'FiscalYearSeeder',
            '--force' => true,
        ]);

        Artisan::call('db:seed', [
            '--class' => 'AccountingPeriodSeeder',
            '--force' => true,
        ]);
    }

    public function seedOptionalData(array $config): void
    {
        // Default-enabled unless explicitly opted out so fresh installs get
        // usable reference rates without an extra decision during setup.
        if ($config['setup_exchange_rates'] ?? true) {
            Artisan::call('db:seed', [
                '--class' => 'ExchangeRateSeeder',
                '--force' => true,
            ]);
        }

        if ($config['setup_branch_pools'] ?? false) {
            Artisan::call('db:seed', [
                '--class' => 'BranchPoolSeeder',
                '--force' => true,
            ]);
        }
    }

    /**
     * Data-derived setup checks used by the wizard's progress UI. Distinct
     * from isCompleted(): these inspect real rows (the pre-marker fallback)
     * rather than the immutable setup_state flag.
     *
     * @return array{admin_user: bool, currencies: bool, exchange_rates: bool, branches: bool, chart_of_accounts: bool}
     */
    public function dataChecks(): array
    {
        return [
            'admin_user' => User::exists(),
            'currencies' => Currency::exists(),
            'exchange_rates' => ExchangeRate::exists(),
            'branches' => Branch::exists(),
            'chart_of_accounts' => ChartOfAccount::exists(),
        ];
    }

    public function isDataComplete(): bool
    {
        return collect($this->dataChecks())
            ->only(['admin_user', 'currencies', 'exchange_rates', 'branches'])
            ->every(fn ($check) => $check);
    }

    public function currentStep(): int
    {
        $checks = $this->dataChecks();

        if (! $checks['admin_user']) {
            return 1;
        }
        if (! $checks['currencies']) {
            return 2;
        }
        if (! $checks['exchange_rates']) {
            return 3;
        }
        if (! $checks['branches']) {
            return 4;
        }

        return 5;
    }

    public function progress(): int
    {
        $checks = $this->dataChecks();
        $completed = count(array_filter($checks));

        return (int) (($completed / count($checks)) * 100);
    }

    /**
     * @return array<int, string>
     */
    public function missingComponents(): array
    {
        return array_keys(array_filter(
            $this->dataChecks(),
            fn ($check) => ! $check
        ));
    }

    /**
     * Execute the step-wizard setup from the accumulated session payload.
     * Callers wrap this in a DB transaction and persist the completion
     * marker via markSetupComplete().
     *
     * @param  array<string, mixed>  $setupData
     */
    public function executeSetup(array $setupData): void
    {
        $this->ensureSchemaExists();

        $hqBranch = null;
        if (isset($setupData['business'])) {
            $hqBranch = Branch::create([
                'code' => 'HQ',
                'name' => $setupData['business']['business_name'],
                'address' => $setupData['business']['business_address'] ?? null,
                'phone' => $setupData['business']['business_phone'] ?? null,
                'email' => $setupData['business']['business_email'] ?? null,
                'type' => 'head_office',
                'is_active' => true,
                'is_main' => true,
            ]);

            // A usable business needs at least one till; there is no counter
            // CRUD UI, so create a default counter bound to HQ.
            Counter::firstOrCreate(
                ['code' => 'C01'],
                ['name' => 'Counter 1', 'status' => CounterStatus::Active->value, 'branch_id' => $hqBranch->id],
            );
        }

        if (isset($setupData['admin'])) {
            // Pass the plain password - the mutator hashes it once. Hashing
            // here as well would double-hash and lock the admin out.
            $user = User::create([
                'username' => $setupData['admin']['admin_name'],
                'email' => $setupData['admin']['admin_email'],
                'password' => $setupData['admin']['admin_password'],
                'branch_id' => $hqBranch?->id,
                'mfa_enabled' => false,
                'is_active' => true,
            ]);

            $user->role = UserRole::Admin;
            $user->save();
        }

        Artisan::call('db:seed', ['--class' => 'CurrencySeeder', '--force' => true]);
        Artisan::call('db:seed', ['--class' => 'EnhancedChartOfAccountsSeeder', '--force' => true]);

        // Custom "other" currencies entered in step 3 may not exist in the
        // seeded list — create them before applying the active set. Newly
        // created ones also get dedicated GL accounts + mapping rows so
        // postings route to the currency's own accounts. A soft-deleted row
        // with the same code would collide on insert (code is the PK), so
        // it is restored instead of crashing the whole setup transaction.
        foreach ($this->customCurrencyRows($setupData['currencies'] ?? []) as $row) {
            $currency = Currency::withTrashed()->firstWhere('code', $row['code']);

            if ($currency === null) {
                $currency = Currency::create([
                    'code' => $row['code'],
                    'name' => $row['name'] !== '' ? $row['name'] : $row['code'],
                    'symbol' => $row['symbol'] !== '' ? $row['symbol'] : $row['code'],
                    'decimal_places' => 2,
                    'is_active' => true,
                ]);

                $this->accountProvisioner->provision($currency);
            } elseif ($currency->trashed()) {
                $currency->restore();
            }
        }

        // Honor the step-3 selection: deactivate currencies the business did
        // not enable. MYR is the system base and must always stay active.
        if (isset($setupData['currencies']['active_currencies'])) {
            $active = $setupData['currencies']['active_currencies'];
            $active[] = Currency::baseCurrency();
            Currency::whereNotIn('code', $active)->update(['is_active' => false]);
            Currency::whereIn('code', $active)->update(['is_active' => true]);
        }

        // Shared with quickSetup so both install paths guarantee the same
        // fiscal-year / accounting-period preconditions.
        $this->ensureFiscalYearAndPeriods();

        if (isset($setupData['rates']) && ($setupData['rates']['use_default_rates'] ?? false)) {
            Artisan::call('db:seed', ['--class' => 'ExchangeRateSeeder', '--force' => true]);
        }

        // Step-4 custom rates (e.g. for a step-3 "other" currency the seeder
        // does not cover) become real exchange_rates rows. Entered values are
        // unit-quoted in the currency's configured quote unit (1 at setup).
        foreach ($setupData['rates']['custom_rates'] ?? [] as $code => $rate) {
            $code = strtoupper(trim((string) $code));
            if ($code === '' || ! isset($rate['buy'], $rate['sell'])) {
                continue;
            }

            $convention = QuoteConvention::for(Currency::find($code));
            ExchangeRate::updateOrCreate(
                ['currency_code' => $code],
                [
                    'rate_buy' => $rate['buy'],
                    'rate_sell' => $rate['sell'],
                    'rate_unit' => $convention->unit,
                    'rate_inverse' => $convention->inverse,
                    'source' => 'setup_custom',
                    'fetched_at' => now(),
                ],
            );
        }

        if (isset($setupData['stock'])) {
            $this->createInitialStock($setupData['stock']);
        }

        if (isset($setupData['opening_balance'])) {
            $this->createOpeningBalance($setupData['opening_balance']);
        }
    }

    /**
     * Build the schema only when it is missing. SchemaSeeder is
     * destructive (drops every table), so on an already-populated
     * database it must never re-run here; an existing schema is treated
     * as current.
     */
    public function ensureSchemaExists(): void
    {
        if (Schema::hasTable('users')) {
            return;
        }

        Artisan::call('db:seed', ['--class' => SchemaSeeder::class, '--force' => true]);
    }

    protected function validateAdminPassword(string $password): void
    {
        // Delegate to the shared rule so setup cannot drift from the policy
        // enforced at every other password entry point.
        (new PasswordComplexityRule)->validate(
            'admin_password',
            $password,
            fn (string $message) => throw new \InvalidArgumentException($message)
        );
    }

    /**
     * Post the opening balance journal entry (cash debits + owner equity credit).
     *
     * The single canonical opening-balance writer: every caller (setup wizard,
     * demo comprehensive setup) funnels through here so the entry always goes
     * through AccountingService::createJournalEntry with journal lines AND
     * account_ledger rows written atomically.
     *
     * @param  array<string, mixed>  $balanceData
     */
    public function createOpeningBalance(array $balanceData): void
    {
        $fiscalYear = FiscalYear::where('status', FiscalYearStatus::Open->value)->first();
        $period = AccountingPeriod::where('status', AccountingPeriodStatus::Open->value)->first();
        $adminUser = User::where('role', 'admin')->first();

        if (! $fiscalYear || ! $period || ! $adminUser) {
            return;
        }

        $openingDate = $fiscalYear->start_date;
        $entryNumber = $this->nextOpeningBalanceEntryNumber($fiscalYear);

        // Calculate total opening balance using BCMath for precision
        $totalMyr = $balanceData['opening_balance_myr'] ?? '0';
        $totalQuantity = '0';
        foreach ($balanceData['opening_balance_foreign'] ?? [] as $currency => $quantity) {
            $totalQuantity = $this->mathService->add($totalQuantity, (string) $quantity);
        }
        $totalBalance = $this->mathService->add((string) $totalMyr, $totalQuantity);

        if ($this->mathService->compare($totalBalance, '0') <= 0) {
            return;
        }

        $lines = [];

        // Cash in MYR
        if ($this->mathService->compare((string) $balanceData['opening_balance_myr'], '0') > 0) {
            $lines[] = [
                'account_code' => $this->accountMappingService->code(AccountMappingKey::CashMyr),
                'debit' => (string) $balanceData['opening_balance_myr'],
                'credit' => '0.00',
                'description' => 'Opening balance - MYR Cash',
            ];
        }

        // Cash in Foreign Currencies (grouped). Foreign opening stock must land
        // on the same account buys and sells flow through (2000 Foreign
        // Currency Inventory) — posting it to 1011 leaves that account
        // permanently unrelieved while every sale drives 2000 negative.
        $totalQuantity = '0';
        foreach ($balanceData['opening_balance_foreign'] ?? [] as $currency => $quantity) {
            if ($this->mathService->compare((string) $quantity, '0') > 0) {
                $totalQuantity = $this->mathService->add($totalQuantity, (string) $quantity);
            }
        }

        if ($this->mathService->compare($totalQuantity, '0') > 0) {
            $lines[] = [
                'account_code' => $this->accountMappingService->code(AccountMappingKey::InventoryDefault),
                'debit' => $totalQuantity,
                'credit' => '0.00',
                'description' => 'Opening balance - Foreign Currency Inventory',
            ];
        }

        // Credit side - Equity
        $lines[] = [
            'account_code' => $this->accountMappingService->code(AccountMappingKey::EquityCapital),
            'debit' => '0.00',
            'credit' => $totalBalance,
            'description' => 'Opening balance - Owner Equity',
        ];

        // The entry is dated at fiscal-year start, which may predate every
        // seeded period — create the covering period when missing so the
        // canonical posting path accepts it.
        $periodDate = $fiscalYear->start_date;
        AccountingPeriod::firstOrCreate(
            ['period_code' => $periodDate->format('Y-m')],
            [
                'fiscal_year_id' => $fiscalYear->id,
                'start_date' => $periodDate->copy()->startOfMonth()->toDateString(),
                'end_date' => $periodDate->copy()->endOfMonth()->toDateString(),
                'period_type' => AccountingPeriodType::Month->value,
                'status' => AccountingPeriodStatus::Open->value,
            ]
        );

        // Post through the canonical path so journal lines AND account_ledger
        // rows are written atomically — hand-writing lines leaves opening
        // balances invisible to every ledger-derived report.
        $entry = $this->accountingService->createJournalEntry(
            $lines,
            'Opening Balance',
            null,
            'Initial opening balances - Business commencement',
            $openingDate->toDateString(),
            $adminUser->id
        );

        $entry->entry_number = $entryNumber;
        $entry->save();
    }

    /**
     * Next opening-balance entry number for the fiscal year.
     *
     * Sequenced per fiscal year (OB-{year}-0001, -0002, ...) so repeated
     * calls — e.g. the per-branch demo setup — can never collide on the
     * fixed -0001 suffix the first call historically used.
     */
    protected function nextOpeningBalanceEntryNumber(FiscalYear $fiscalYear): string
    {
        $prefix = 'OB-'.$fiscalYear->year_code.'-';

        $sequence = JournalEntry::where('entry_number', 'like', $prefix.'%')->count() + 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Seed the HQ branch pool with the configured initial stock balances.
     *
     * Extracted from SetupController so the controller only coordinates setup.
     * One BranchPool row per currency (only when the amount is positive) is
     * created with the exact available/allocated balances as before.
     *
     * @param  array<string, mixed>  $stockData
     */
    public function createInitialStock(array $stockData): void
    {
        $branch = Branch::where('code', 'HQ')->first();

        if (! $branch || ! isset($stockData['initial_stock'])) {
            return;
        }

        // exchange_rates rows are unit-quoted (per rate_unit foreign units,
        // or foreign per rate_unit MYR when inverse); positions store
        // per-unit cost, so normalize by the row's own quote convention.
        $rates = ExchangeRate::query()
            ->get(['currency_code', 'rate_buy', 'rate_unit', 'rate_inverse'])
            ->keyBy('currency_code');

        foreach ($stockData['initial_stock'] as $currencyCode => $quantity) {
            // Zero-amount entries still create pool/position rows so every
            // currency selected in step 3 is mapped into the accounting
            // system, not only the ones with opening stock.
            BranchPool::create([
                'branch_id' => $branch->id,
                'currency_code' => $currencyCode,
                'available_balance' => (string) $quantity,
                'allocated_balance' => '0.00',
            ]);

            // Transaction stock validation reads currency_positions, not
            // branch_pools — seed both so a fresh install can actually sell
            // the stock it was set up with.
            $rateRow = $rates->get($currencyCode);
            $cost = $currencyCode === Currency::baseCurrency()
                ? '1'
                : ($rateRow === null
                    ? '0'
                    : $rateRow->perUnitRate((string) $rateRow->rate_buy));
            $totalCost = $this->mathService->multiply((string) $quantity, $cost);

            CurrencyPosition::create([
                'branch_id' => $branch->id,
                'currency_code' => $currencyCode,
                'quantity' => (string) $quantity,
                'average_cost' => $cost,
                'total_cost' => $totalCost,
                'current_rate' => $cost,
                'current_value' => $totalCost,
                'unrealized_gain_loss' => '0',
                'last_revalued_at' => null,
            ]);
        }
    }
}
