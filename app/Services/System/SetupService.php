<?php

namespace App\Services\System;

use App\Enums\AccountCode;
use App\Enums\AccountingPeriodStatus;
use App\Enums\AccountingPeriodType;
use App\Enums\UserRole;
use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\BranchPool;
use App\Models\CurrencyPosition;
use App\Models\ExchangeRate;
use App\Models\FiscalYear;
use App\Models\PasswordHistory;
use App\Models\User;
use App\Services\Accounting\AccountingService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SetupService
{
    public function __construct(
        protected MathService $mathService,
        protected AccountingService $accountingService,
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

    protected function validateAdminPassword(string $password): void
    {
        if (strlen($password) < 12) {
            throw new \InvalidArgumentException('Admin password must be at least 12 characters');
        }

        if (! preg_match('/[A-Z]/', $password)) {
            throw new \InvalidArgumentException('Admin password must contain at least one uppercase letter');
        }

        if (! preg_match('/[a-z]/', $password)) {
            throw new \InvalidArgumentException('Admin password must contain at least one lowercase letter');
        }

        if (! preg_match('/[0-9]/', $password)) {
            throw new \InvalidArgumentException('Admin password must contain at least one digit');
        }

        if (! preg_match('/[^A-Za-z0-9]/', $password)) {
            throw new \InvalidArgumentException('Admin password must contain at least one special character');
        }
    }

    /**
     * Post the opening balance journal entry (cash debits + owner equity credit).
     *
     * Extracted from SetupController so the controller only coordinates setup.
     * The financial behaviour (entry number, posted status, MYR/foreign cash
     * debits, equity credit and the BCMath totals) is preserved exactly.
     *
     * @param  array<string, mixed>  $balanceData
     */
    public function createOpeningBalance(array $balanceData): void
    {
        $fiscalYear = FiscalYear::where('status', 'Open')->first();
        $period = AccountingPeriod::where('status', 'Open')->first();
        $adminUser = User::where('role', 'admin')->first();

        if (! $fiscalYear || ! $period || ! $adminUser) {
            return;
        }

        $openingDate = $fiscalYear->start_date;
        $entryNumber = 'OB-'.$fiscalYear->year_code.'-0001';

        // Calculate total opening balance using BCMath for precision
        $totalMyr = $balanceData['opening_balance_myr'] ?? '0';
        $totalForeign = '0';
        foreach ($balanceData['opening_balance_foreign'] ?? [] as $currency => $amount) {
            $totalForeign = $this->mathService->add($totalForeign, (string) $amount);
        }
        $totalBalance = $this->mathService->add((string) $totalMyr, $totalForeign);

        if ($this->mathService->compare($totalBalance, '0') <= 0) {
            return;
        }

        $lines = [];

        // Cash in MYR
        if ($this->mathService->compare((string) $balanceData['opening_balance_myr'], '0') > 0) {
            $lines[] = [
                'account_code' => AccountCode::CASH_MYR->value,
                'debit' => (string) $balanceData['opening_balance_myr'],
                'credit' => '0.00',
                'description' => 'Opening balance - MYR Cash',
            ];
        }

        // Cash in Foreign Currencies (grouped). Foreign opening stock must land
        // on the same account buys and sells flow through (2000 Foreign
        // Currency Inventory) — posting it to 1011 leaves that account
        // permanently unrelieved while every sale drives 2000 negative.
        $totalForeignBalance = '0';
        foreach ($balanceData['opening_balance_foreign'] ?? [] as $currency => $amount) {
            if ($this->mathService->compare((string) $amount, '0') > 0) {
                $totalForeignBalance = $this->mathService->add($totalForeignBalance, (string) $amount);
            }
        }

        if ($this->mathService->compare($totalForeignBalance, '0') > 0) {
            $lines[] = [
                'account_code' => AccountCode::FOREIGN_CURRENCY_INVENTORY->value,
                'debit' => $totalForeignBalance,
                'credit' => '0.00',
                'description' => 'Opening balance - Foreign Currency Inventory',
            ];
        }

        // Credit side - Equity
        $lines[] = [
            'account_code' => AccountCode::CAPITAL->value,
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

        $rates = ExchangeRate::query()
            ->pluck('rate_buy', 'currency_code');

        foreach ($stockData['initial_stock'] as $currencyCode => $amount) {
            // Zero-amount entries still create pool/position rows so every
            // currency selected in step 3 is mapped into the accounting
            // system, not only the ones with opening stock.
            BranchPool::create([
                'branch_id' => $branch->id,
                'currency_code' => $currencyCode,
                'available_balance' => (string) $amount,
                'allocated_balance' => '0.00',
            ]);

            // Transaction stock validation reads currency_positions, not
            // branch_pools — seed both so a fresh install can actually sell
            // the stock it was set up with.
            $cost = $currencyCode === 'MYR'
                ? '1'
                : (string) ($rates[$currencyCode] ?? '0');
            $totalCost = $this->mathService->multiply((string) $amount, $cost);

            CurrencyPosition::create([
                'branch_id' => $branch->id,
                'currency_code' => $currencyCode,
                'quantity' => (string) $amount,
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
