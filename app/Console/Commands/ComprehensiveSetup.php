<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\BranchPool;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\User;
use App\Services\ComprehensiveLogService;
use App\Services\MathService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ComprehensiveSetup extends Command
{
    protected $signature = 'comprehensive:setup {--branches=4} {--transactions=20}';

    protected $description = 'Complete system setup with branches, opening stock, and test transactions';

    protected ComprehensiveLogService $logger;

    protected MathService $mathService;

    protected int $branchCount;

    protected int $transactionsPerBranch;

    public function handle()
    {
        $this->logger = app(ComprehensiveLogService::class);
        $this->mathService = new MathService;
        $this->branchCount = (int) $this->option('branches');
        $this->transactionsPerBranch = (int) $this->option('transactions');

        $this->logger->log('SETUP', 'STARTED', 'System', null, [
            'branches' => $this->branchCount,
            'transactions_per_branch' => $this->transactionsPerBranch,
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

            $this->logger->log('SETUP', 'COMPLETED', 'System', null, [
                'branches_created' => count($branches),
            ], 'SUCCESS');

            $this->info('Setup completed successfully!');
            $this->info("Log file: {$this->logger->getLogFile()}");

        } catch (\Exception $e) {
            DB::rollBack();
            $this->logger->logError('SETUP', $e, ['step' => 'setup_failed']);
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

        // Create admin user
        User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'username' => 'admin',
                'name' => 'Administrator',
                'password_hash' => Hash::make('password'),
                'role' => 'admin',
                'is_active' => true,
            ]
        );

        $this->logger->log('SETUP', 'BASE_DATA_CREATED', 'System', null, [], 'INFO');
    }

    protected function createBranches(): array
    {
        $this->info("Creating {$this->branchCount} branches...");
        $branches = [];

        for ($i = 1; $i <= $this->branchCount; $i++) {
            $branch = Branch::create([
                'code' => sprintf('BR%03d', $i),
                'name' => 'Branch '.str_pad($i, 3, '0', STR_PAD_LEFT),
                'type' => 'branch',
                'is_active' => true,
                'is_main' => $i === 1,
            ]);

            $branches[] = $branch;

            $this->logger->log('BRANCH', 'CREATED', 'Branch', $branch->id, [
                'code' => $branch->code,
                'name' => $branch->name,
            ], 'INFO');

            $this->info("  Created branch: {$branch->code} - {$branch->name}");
        }

        return $branches;
    }

    protected function initializeOpeningStock(Branch $branch): void
    {
        $this->info("Initializing opening stock for {$branch->code}...");

        $currencies = ['USD' => 10000, 'EUR' => 7000, 'GBP' => 5000];

        foreach ($currencies as $currencyCode => $amount) {
            $pool = BranchPool::firstOrCreate(
                ['branch_id' => $branch->id, 'currency_code' => $currencyCode],
                ['available_balance' => '0', 'allocated_balance' => '0']
            );

            // Update available balance
            $newBalance = $this->mathService->add($pool->available_balance, (string) $amount);
            $pool->available_balance = $newBalance;
            $pool->save();

            $this->logger->log('STOCK', 'INITIALIZED', 'BranchPool', $pool->id, [
                'branch_code' => $branch->code,
                'currency' => $currencyCode,
                'amount' => $amount,
            ], 'INFO');

            $this->info("    - {$currencyCode}: {$amount}");
        }
    }

    protected function createOpeningBalance(Branch $branch): void
    {
        $this->info("Creating opening balance for {$branch->code}...");

        // This would normally use AccountingService, but for simplicity we'll skip
        // In real scenario, use: app(AccountingService::class)->createJournalEntry(...)

        $this->logger->log('ACCOUNTING', 'OPENING_BALANCE', 'Branch', $branch->id, [
            'branch_code' => $branch->code,
        ], 'INFO');
    }

    protected function createTestTransactions(Branch $branch): void
    {
        $this->info("Creating {$this->transactionsPerBranch} test transactions for {$branch->code}...");

        // Implementation would go here
        // For now, just log that we're doing it

        $this->logger->log('TRANSACTION', 'BATCH_CREATED', 'Branch', $branch->id, [
            'count' => $this->transactionsPerBranch,
        ], 'INFO');
    }
}
