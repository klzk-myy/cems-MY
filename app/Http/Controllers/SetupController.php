<?php

namespace App\Http\Controllers;

use App\Enums\JournalEntryStatus;
use App\Http\Requests\SetupRequest;
use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\User;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SetupController extends Controller
{
    public function index(Request $request)
    {
        $isSetupComplete = $this->isSetupComplete();

        if ($isSetupComplete) {
            return view('setup.index', [
                'isSetupComplete' => true,
                'currentStep' => 7,
                'progress' => 100,
            ]);
        }

        $step = $request->get('step', $this->getCurrentStep());

        return view('setup.index', [
            'isSetupComplete' => false,
            'currentStep' => (int) $step,
            'progress' => $this->calculateProgress(),
            'currencies' => Currency::all(),
        ]);
    }

    public function wizard(Request $request)
    {
        $step = $request->get('step', $this->getCurrentStep());

        return redirect()->route('setup.index', ['step' => $step]);
    }

    public function quickSetup(SetupRequest $request)
    {
        $validated = $request->validated();

        try {
            DB::beginTransaction();

            $this->runMigrations();
            $this->seedCoreData($validated);
            $this->seedOptionalData($validated);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Setup completed successfully!',
                'redirect' => '/login',
                'credentials' => [
                    'email' => $validated['admin_email'],
                    'password' => 'Use the password you provided',
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Setup failed: '.$e->getMessage(),
            ], 500);
        }
    }

    public function step1CompanyInfo(SetupRequest $request)
    {
        $validated = $request->validated();

        session(['setup.business' => $validated]);

        return redirect()->route('setup.wizard', ['step' => 2]);
    }

    public function step2AdminUser(SetupRequest $request)
    {
        $validated = $request->validated();

        session(['setup.admin' => $validated]);

        return redirect()->route('setup.wizard', ['step' => 3]);
    }

    public function step3Currencies(SetupRequest $request)
    {
        $validated = $request->validated();

        session(['setup.currencies' => $validated]);

        return redirect()->route('setup.wizard', ['step' => 4]);
    }

    public function step4ExchangeRates(SetupRequest $request)
    {
        $validated = $request->validated();

        session(['setup.rates' => $validated]);

        return redirect()->route('setup.wizard', ['step' => 5]);
    }

    public function step5InitialStock(SetupRequest $request)
    {
        $validated = $request->validated();

        // Merge initial_stock and initial_foreign_cash into a single stock array
        $stock = $validated['initial_stock'] ?? [];
        if (isset($validated['initial_foreign_cash'])) {
            $stock = array_merge($stock, $validated['initial_foreign_cash']);
        }

        // Add MYR cash as part of initial stock
        $stock['MYR'] = $validated['initial_myr_cash'];

        session(['setup.stock' => ['initial_stock' => $stock, 'initial_myr_cash' => $validated['initial_myr_cash']]]);

        return redirect()->route('setup.wizard', ['step' => 6]);
    }

    public function step6OpeningBalance(SetupRequest $request)
    {
        $validated = $request->validated();

        session(['setup.opening_balance' => $validated]);

        return redirect()->route('setup.wizard', ['step' => 7]);
    }

    public function completeSetup(Request $request)
    {
        $setupData = session('setup', []);

        try {
            DB::beginTransaction();

            $this->executeSetup($setupData);

            DB::commit();

            session()->forget('setup');

            return response()->json([
                'success' => true,
                'message' => 'Business setup completed successfully!',
                'redirect' => route('login'),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function checkStatus()
    {
        return response()->json([
            'is_complete' => $this->isSetupComplete(),
            'current_step' => $this->getCurrentStep(),
            'progress' => $this->calculateProgress(),
            'missing_components' => $this->getMissingComponents(),
        ]);
    }

    public function resetSetup(Application $app)
    {
        if ($app->environment('production')) {
            return response()->json([
                'success' => false,
                'message' => 'Reset not allowed in production',
            ], 403);
        }

        try {
            Artisan::call('migrate:fresh', ['--force' => true]);
            session()->forget('setup');

            return response()->json([
                'success' => true,
                'message' => 'Setup reset. You can start fresh.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    private function isSetupComplete(): bool
    {
        return User::exists() &&
               Currency::exists() &&
               ExchangeRate::exists() &&
               Branch::exists();
    }

    private function getCurrentStep(): int
    {
        if (! User::exists()) {
            return 1;
        }
        if (! Currency::exists()) {
            return 2;
        }
        if (! ExchangeRate::exists()) {
            return 3;
        }
        if (! Branch::exists()) {
            return 4;
        }

        return 5;
    }

    private function calculateProgress(): int
    {
        $checks = [
            User::exists(),
            Currency::exists(),
            ExchangeRate::exists(),
            Branch::exists(),
            ChartOfAccount::exists(),
        ];

        $completed = count(array_filter($checks));

        return (int) (($completed / count($checks)) * 100);
    }

    private function getMissingComponents(): array
    {
        $missing = [];

        if (! User::exists()) {
            $missing[] = 'admin_user';
        }
        if (! Currency::exists()) {
            $missing[] = 'currencies';
        }
        if (! ExchangeRate::exists()) {
            $missing[] = 'exchange_rates';
        }
        if (! Branch::exists()) {
            $missing[] = 'branches';
        }
        if (! ChartOfAccount::exists()) {
            $missing[] = 'chart_of_accounts';
        }

        return $missing;
    }

    private function runMigrations(): void
    {
        Artisan::call('migrate', ['--force' => true]);
    }

    private function seedCoreData(array $config): void
    {
        $admin = User::create([
            'username' => 'admin',
            'email' => $config['admin_email'],
            'password_hash' => Hash::make($config['admin_password']),
            'role' => 'admin',
            'mfa_enabled' => false,
            'is_active' => true,
        ]);

        Artisan::call('db:seed', [
            '--class' => 'CurrencySeeder',
            '--force' => true,
        ]);

        Artisan::call('db:seed', [
            '--class' => 'ChartOfAccountsSeeder',
            '--force' => true,
        ]);

        Branch::create([
            'code' => 'HQ',
            'name' => $config['business_name'].' - Head Office',
            'type' => 'head_office',
            'is_active' => true,
            'is_main' => true,
        ]);
    }

    private function seedOptionalData(array $config): void
    {
        if ($config['setup_exchange_rates'] ?? false) {
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

    private function executeSetup(array $setupData): void
    {
        $this->runMigrations();

        if (isset($setupData['admin'])) {
            User::create([
                'username' => $setupData['admin']['admin_name'],
                'email' => $setupData['admin']['admin_email'],
                'password_hash' => Hash::make($setupData['admin']['admin_password']),
                'role' => 'admin',
                'mfa_enabled' => false,
                'is_active' => true,
            ]);
        }

        Artisan::call('db:seed', ['--class' => 'CurrencySeeder', '--force' => true]);
        Artisan::call('db:seed', ['--class' => 'ChartOfAccountsSeeder', '--force' => true]);
        Artisan::call('db:seed', ['--class' => 'FiscalYearSeeder', '--force' => true]);
        Artisan::call('db:seed', ['--class' => 'AccountingPeriodSeeder', '--force' => true]);

        if (isset($setupData['business'])) {
            Branch::create([
                'code' => 'HQ',
                'name' => $setupData['business']['business_name'],
                'address' => $setupData['business']['business_address'] ?? null,
                'phone' => $setupData['business']['business_phone'] ?? null,
                'email' => $setupData['business']['business_email'] ?? null,
                'type' => 'head_office',
                'is_active' => true,
                'is_main' => true,
            ]);
        }

        if (isset($setupData['currencies'])) {
            // Base currency is set via session/config - no is_base column needed
            // The base currency code is stored in $setupData['currencies']['base_currency']
        }

        if (isset($setupData['rates']) && ($setupData['rates']['use_default_rates'] ?? false)) {
            Artisan::call('db:seed', ['--class' => 'ExchangeRateSeeder', '--force' => true]);
        }

        if (isset($setupData['stock'])) {
            $this->createInitialStock($setupData['stock']);
        }

        if (isset($setupData['opening_balance'])) {
            $this->createOpeningBalance($setupData['opening_balance']);
        }
    }

    private function createOpeningBalance(array $balanceData): void
    {
        $fiscalYear = FiscalYear::where('status', 'open')->first();
        $period = AccountingPeriod::where('status', 'open')->first();
        $adminUser = User::where('role', 'admin')->first();

        if (! $fiscalYear || ! $period || ! $adminUser) {
            return;
        }

        $openingDate = $fiscalYear->start_date;
        $entryNumber = 'OB-'.$fiscalYear->year_code.'-0001';

        // Calculate total opening balance
        $totalMyr = $balanceData['opening_balance_myr'] ?? 0;
        $totalForeign = 0;
        foreach ($balanceData['opening_balance_foreign'] ?? [] as $currency => $amount) {
            $totalForeign += (float) $amount;
        }
        $totalBalance = $totalMyr + $totalForeign;

        if ($totalBalance <= 0) {
            return;
        }

        DB::transaction(function () use ($fiscalYear, $period, $adminUser, $openingDate, $entryNumber, $totalBalance, $balanceData) {
            $journalEntry = JournalEntry::create([
                'entry_number' => $entryNumber,
                'fiscal_year_id' => $fiscalYear->id,
                'period_id' => $period->id,
                'entry_date' => $openingDate,
                'reference_type' => 'Opening Balance',
                'reference_id' => null,
                'description' => 'Initial opening balances - Business commencement',
                'total_amount' => (string) $totalBalance,
                'status' => JournalEntryStatus::Posted,
                'created_by' => $adminUser->id,
                'posted_by' => $adminUser->id,
                'posted_at' => now(),
            ]);

            // Cash in MYR
            if ($balanceData['opening_balance_myr'] > 0) {
                $cashMyrAccount = ChartOfAccount::where('account_code', '1010')->first();
                if ($cashMyrAccount) {
                    JournalLine::create([
                        'journal_entry_id' => $journalEntry->id,
                        'account_code' => $cashMyrAccount->account_code,
                        'debit' => (string) $balanceData['opening_balance_myr'],
                        'credit' => '0.00',
                        'description' => 'Opening balance - MYR Cash',
                    ]);
                }
            }

            // Cash in Foreign Currencies (grouped)
            $totalForeignBalance = 0;
            foreach ($balanceData['opening_balance_foreign'] ?? [] as $currency => $amount) {
                if ($amount > 0) {
                    $totalForeignBalance += $amount;
                }
            }

            if ($totalForeignBalance > 0) {
                $cashForeignAccount = ChartOfAccount::where('account_code', '1011')->first();
                if ($cashForeignAccount) {
                    JournalLine::create([
                        'journal_entry_id' => $journalEntry->id,
                        'account_code' => $cashForeignAccount->account_code,
                        'debit' => (string) $totalForeignBalance,
                        'credit' => '0.00',
                        'description' => 'Opening balance - Foreign Cash',
                    ]);
                }
            }

            // Credit side - Equity
            $equityAccount = ChartOfAccount::where('account_code', '3000')->first();
            if ($equityAccount) {
                JournalLine::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_code' => $equityAccount->account_code,
                    'debit' => '0.00',
                    'credit' => (string) $totalBalance,
                    'description' => 'Opening balance - Owner Equity',
                ]);
            }
        });
    }

    private function createInitialStock(array $stockData): void
    {
        $branch = Branch::where('code', 'HQ')->first();

        if ($branch && isset($stockData['initial_stock'])) {
            foreach ($stockData['initial_stock'] as $currencyCode => $amount) {
                if ($amount > 0) {
                    DB::table('branch_pools')->insert([
                        'branch_id' => $branch->id,
                        'currency_code' => $currencyCode,
                        'available_balance' => (string) $amount,
                        'allocated_balance' => '0.00',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }
}
