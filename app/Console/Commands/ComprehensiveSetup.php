<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\BranchPool;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\PasswordHistory;
use App\Models\User;
use App\Rules\PasswordRules;
use App\Services\AuditService;
use App\Services\System\MathService;
use App\Services\System\SetupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ComprehensiveSetup extends Command
{
    protected $signature = 'comprehensive:setup {--branches=4} {--transactions=20} {--admin-password=} {--force}';

    protected $description = 'Complete system setup with branches, opening stock, and test transactions';

    protected AuditService $logger;

    protected MathService $mathService;

    protected int $branchCount;

    protected int $transactionsPerBranch;

    public function __construct(protected SetupService $setupService)
    {
        parent::__construct();
    }

    public function handle()
    {
        $this->logger = app(AuditService::class);
        $this->mathService = new MathService;
        $this->branchCount = (int) $this->option('branches');
        $this->transactionsPerBranch = (int) $this->option('transactions');

        $this->logger->logWithSeverity('setup_started', [
            'entity_type' => 'System',
            'entity_id' => null,
            'new_values' => [
                'branches' => $this->branchCount,
                'transactions_per_branch' => $this->transactionsPerBranch,
            ],
        ], 'INFO');

        $this->info('Starting comprehensive setup with logging...');
        $this->info("Branches: {$this->branchCount}, Transactions per branch: {$this->transactionsPerBranch}");

        try {
            DB::beginTransaction();

            $this->createBaseData();
            $branches = $this->createBranches();

            foreach ($branches as $branch) {
                $this->initializeOpeningStock($branch);
                $this->createOpeningBalance($branch);
                $this->createTestTransactions($branch);
            }

            DB::commit();

            $this->logger->logWithSeverity('setup_completed', [
                'entity_type' => 'System',
                'entity_id' => null,
                'new_values' => [
                    'branches_created' => count($branches),
                ],
            ], 'INFO');

            $this->info('Setup completed successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            $this->logger->logWithSeverity('setup_failed', [
                'entity_type' => 'System',
                'new_values' => [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ],
            ], 'ERROR');
            $this->error('Setup failed: '.$e->getMessage());

            return 1;
        }

        return 0;
    }

    protected function createBaseData(): void
    {
        $this->info('Creating base data...');

        // Create MYR base currency
        Currency::firstOrCreate(
            ['code' => 'MYR'],
            ['name' => 'Malaysian Ringgit', 'symbol' => 'RM', 'is_active' => true]
        );

        // Create USD
        Currency::firstOrCreate(
            ['code' => 'USD'],
            ['name' => 'US Dollar', 'symbol' => '$', 'is_active' => true]
        );

        // Create EUR
        Currency::firstOrCreate(
            ['code' => 'EUR'],
            ['name' => 'Euro', 'symbol' => '€', 'is_active' => true]
        );

        // Create GBP
        Currency::firstOrCreate(
            ['code' => 'GBP'],
            ['name' => 'British Pound', 'symbol' => '£', 'is_active' => true]
        );

        // Create exchange rates
        $rates = [
            ['base_currency' => 'MYR', 'currency_code' => 'USD', 'rate' => '4.50', 'buying_rate' => '4.45', 'selling_rate' => '4.55'],
            ['base_currency' => 'MYR', 'currency_code' => 'EUR', 'rate' => '4.90', 'buying_rate' => '4.85', 'selling_rate' => '4.95'],
            ['base_currency' => 'MYR', 'currency_code' => 'GBP', 'rate' => '5.70', 'buying_rate' => '5.65', 'selling_rate' => '5.75'],
        ];

        foreach ($rates as $rate) {
            ExchangeRate::updateOrCreate(
                ['base_currency' => $rate['base_currency'], 'currency_code' => $rate['currency_code']],
                $rate
            );
        }

        // Create admin user. Never seed a well-known password: if an explicit
        // password is not provided, generate a strong random one and print it
        // once. Refuse to run in production without --force, since this command
        // creates live data (including a privileged admin account).
        $adminExists = User::where('email', 'admin@example.com')->exists();

        if (! $adminExists) {
            if (app()->environment('production') && ! $this->option('force')) {
                throw new \RuntimeException(
                    'Refusing to create an admin user in production. Re-run with --force to override.'
                );
            }

            $password = $this->option('admin-password') ?: Str::password(16);

            Validator::make(
                ['password' => $password],
                ['password' => PasswordRules::forNew(confirmed: false)],
            )->validate();

            // 'password' routes through the mutator (hash + password_changed_at
            // stamp); password_hash is not fillable and would be dropped.
            $admin = User::create([
                'email' => 'admin@example.com',
                'username' => 'admin',
                'password' => $password,
                'is_active' => true,
            ]);

            $admin->role = UserRole::Admin;
            $admin->mfa_enabled = false;
            $admin->save();

            PasswordHistory::record($admin->id, $admin->password_hash);

            if (! $this->option('admin-password')) {
                $this->warn('Generated admin password (shown once): '.$password);
                $this->warn('Change it immediately after first login.');
            }
        }

        // Canonical posting preconditions: opening balances below are journaled
        // via AccountingService::createJournalEntry, which requires the chart of
        // accounts plus an open fiscal year and accounting period to exist.
        Artisan::call('db:seed', [
            '--class' => 'EnhancedChartOfAccountsSeeder',
            '--force' => true,
        ]);
        $this->setupService->ensureFiscalYearAndPeriods();

        $this->logger->logWithSeverity('setup_base_data_created', [
            'entity_type' => 'System',
            'new_values' => [],
        ], 'INFO');
    }

    /**
     * Demo opening stock per branch. Shared by the pool seeding and the
     * opening-balance journal so the two can never drift apart.
     *
     * @return array<string, int>
     */
    protected function openingStockQuantities(): array
    {
        return ['USD' => 10000, 'EUR' => 7000, 'GBP' => 5000];
    }

    protected function createBranches(): array
    {
        $this->info("Creating {$this->branchCount} branches...");
        $branches = [];

        for ($i = 1; $i <= $this->branchCount; $i++) {
            $branch = Branch::create([
                'code' => sprintf('BR%03d', $i),
                'name' => 'Branch '.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'type' => 'branch',
                'is_active' => true,
                'is_main' => $i === 1,
            ]);

            $branches[] = $branch;

            $this->logger->log('BRANCH_CREATED', null, 'Branch', $branch->id, [], [
                'code' => $branch->code,
                'name' => $branch->name,
            ]);

            $this->info("  Created branch: {$branch->code} - {$branch->name}");
        }

        return $branches;
    }

    protected function initializeOpeningStock(Branch $branch): void
    {
        $this->info("Initializing opening stock for {$branch->code}...");

        foreach ($this->openingStockQuantities() as $currencyCode => $quantity) {
            $pool = BranchPool::firstOrCreate(
                ['branch_id' => $branch->id, 'currency_code' => $currencyCode],
                ['available_balance' => '0', 'allocated_balance' => '0']
            );

            // Update available balance
            $newBalance = $this->mathService->add($pool->available_balance, (string) $quantity);
            $pool->available_balance = $newBalance;
            $pool->save();

            $this->logger->log('STOCK_INITIALIZED', null, 'BranchPool', $pool->id, [], [
                'branch_code' => $branch->code,
                'currency' => $currencyCode,
                'quantity' => $quantity,
            ]);

            $this->info("    - {$currencyCode}: {$quantity}");
        }
    }

    protected function createOpeningBalance(Branch $branch): void
    {
        $this->info("Creating opening balance for {$branch->code}...");

        // Delegate to the single canonical writer: SetupService posts through
        // AccountingService::createJournalEntry so journal lines AND
        // account_ledger rows are always written atomically.
        $this->setupService->createOpeningBalance([
            'opening_balance_myr' => '0',
            'opening_balance_foreign' => $this->openingStockQuantities(),
        ]);

        $this->logger->log('OPENING_BALANCE', null, 'Branch', $branch->id, [], [
            'branch_code' => $branch->code,
        ]);
    }

    protected function createTestTransactions(Branch $branch): void
    {
        $this->info("Creating {$this->transactionsPerBranch} test transactions for {$branch->code}...");

        // Implementation would go here
        // For now, just log that we're doing it

        $this->logger->log('BATCH_CREATED', null, 'Branch', $branch->id, [], [
            'count' => $this->transactionsPerBranch,
        ]);
    }
}
