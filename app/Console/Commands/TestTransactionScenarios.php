<?php

namespace App\Console\Commands;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\CounterSession;
use App\Models\Customer;
use App\Models\TellerAllocation;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class TestTransactionScenarios extends Command
{
    protected $signature = 'test:scenarios {--clean : Clean up test data before running}';

    protected $description = 'Execute CDD/Risk test scenarios';

    protected $results = [];

    protected $customers = [];

    protected $tellers = [];

    protected $counters = [];

    public function handle(): int
    {
        $this->info('=== CEMS-MY CDD/Risk Test Scenarios ===');
        $this->info('');

        if ($this->option('clean')) {
            $this->cleanTestData();
        }

        $this->setupTestData();
        $this->runScenarios();

        $this->displayReport();

        return Command::SUCCESS;
    }

    protected function cleanTestData(): void
    {
        $this->info('Cleaning up test data...');
        Transaction::whereIn('reference_no', array_column($this->results ?? [], 'reference_no'))->delete();
        Customer::where('email', 'LIKE', '%@test-cdd%')->delete();
        $this->info('Cleanup complete.');
    }

    protected function setupTestData(): void
    {
        $this->info('=== SETUP: Creating Test Users and Branches ===');

        // Get branches
        $branches = Branch::all();
        $this->info("Found {$branches->count()} branches");

        // Create tellers for each branch
        foreach ($branches as $branch) {
            $username = 'teller_'.strtolower($branch->code);
            $email = $username.'@test-cdd.my';

            $teller = User::updateOrCreate(
                ['username' => $username],
                [
                    'email' => $email,
                    'password_hash' => Hash::make('Test@1234'),
                    'role' => 'teller',
                    'branch_id' => $branch->id,
                    'mfa_enabled' => false,
                    'is_active' => true,
                ]
            );
            $this->tellers[$branch->code] = $teller;
            $this->info("  Teller: {$email} (Branch: {$branch->code})");

            // Open counter for teller
            $counter = Counter::where('branch_id', $branch->id)->first();
            if ($counter) {
                // Close any existing sessions
                CounterSession::where('user_id', $teller->id)->whereNull('closed_at')->update(['closed_at' => now()]);

                $session = CounterSession::create([
                    'counter_id' => $counter->id,
                    'user_id' => $teller->id,
                    'session_date' => now()->toDateString(),
                    'opened_at' => now(),
                    'status' => 'open',
                    'opened_by' => $teller->id,
                ]);

                $this->counters[$branch->code] = $counter;

                // Allocate stock to teller
                TellerAllocation::updateOrCreate(
                    ['user_id' => $teller->id, 'currency_code' => 'USD'],
                    [
                        'branch_id' => $branch->id,
                        'counter_id' => $counter->id,
                        'allocated_amount' => '50000',
                        'current_balance' => '50000',
                        'requested_amount' => '50000',
                        'daily_limit_myr' => '100000',
                        'daily_used_myr' => '0',
                        'session_date' => now()->toDateString(),
                        'opened_at' => now(),
                        'status' => 'active',
                    ]
                );
            }
        }

        $this->info('');
    }

    protected function runScenarios(): void
    {
        $scenarios = [
            // Scenario 1: Simplified CDD - New customer, small amount
            [
                'id' => 'S1',
                'branch' => 'HQ',
                'type' => 'Buy',
                'currency' => 'USD',
                'foreign_amount' => '100',
                'rate' => '4.50',
                'customer_name' => 'Ahmad Razak',
                'customer_ic' => '700101-14-1234',
                'customer_type' => 'individual',
                'nationality' => 'MY',
                'is_pep' => false,
                'is_high_risk_country' => false,
                'expected_cdd' => 'Simplified',
                'description' => 'New customer, RM 450 (below threshold)',
            ],
            // Scenario 2: Simplified CDD - Just below RM 3,000
            [
                'id' => 'S2',
                'branch' => 'HQ',
                'type' => 'Buy',
                'currency' => 'USD',
                'foreign_amount' => '666',
                'rate' => '4.50',
                'customer_name' => 'Lim Cheng Teik',
                'customer_ic' => '800202-15-2345',
                'customer_type' => 'individual',
                'nationality' => 'MY',
                'is_pep' => false,
                'is_high_risk_country' => false,
                'expected_cdd' => 'Simplified',
                'description' => 'New customer, RM 2,997 (just below threshold)',
            ],
            // Scenario 3: Standard CDD - At RM 3,000 threshold
            [
                'id' => 'S3',
                'branch' => 'HQ',
                'type' => 'Buy',
                'currency' => 'USD',
                'foreign_amount' => '667',
                'rate' => '4.50',
                'customer_name' => 'Tan Sri Wong',
                'customer_ic' => '590303-16-3456',
                'customer_type' => 'individual',
                'nationality' => 'MY',
                'is_pep' => false,
                'is_high_risk_country' => false,
                'expected_cdd' => 'Standard',
                'description' => 'New customer, RM 3,001.50 (at threshold - requires approval)',
            ],
            // Scenario 4: Standard CDD - Mid-range
            [
                'id' => 'S4',
                'branch' => 'BR001',
                'type' => 'Sell',
                'currency' => 'EUR',
                'foreign_amount' => '3000',
                'rate' => '4.80',
                'customer_name' => 'Fatimah Bt Ibrahim',
                'customer_ic' => '750505-17-4567',
                'customer_type' => 'individual',
                'nationality' => 'MY',
                'is_pep' => false,
                'is_high_risk_country' => false,
                'expected_cdd' => 'Standard',
                'description' => 'Sell transaction, RM 14,400 (standard CDD)',
            ],
            // Scenario 5: Standard CDD - Just below RM 50,000
            [
                'id' => 'S5',
                'branch' => 'BR001',
                'type' => 'Buy',
                'currency' => 'GBP',
                'foreign_amount' => '10000',
                'rate' => '4.95',
                'customer_name' => 'Othman Bin Malik',
                'customer_ic' => '780808-18-5678',
                'customer_type' => 'individual',
                'nationality' => 'MY',
                'is_pep' => false,
                'is_high_risk_country' => false,
                'expected_cdd' => 'Standard',
                'description' => 'Buy transaction, RM 49,500 (just below large threshold)',
            ],
            // Scenario 6: Enhanced CDD - At RM 50,000 threshold
            [
                'id' => 'S6',
                'branch' => 'BR002',
                'type' => 'Buy',
                'currency' => 'USD',
                'foreign_amount' => '11112',
                'rate' => '4.50',
                'customer_name' => 'Chen Mun Holdings',
                'customer_ic' => 'A123456789',
                'customer_type' => 'company',
                'nationality' => 'MY',
                'is_pep' => false,
                'is_high_risk_country' => false,
                'expected_cdd' => 'Enhanced',
                'description' => 'Company customer, RM 50,004 (at enhanced threshold)',
            ],
            // Scenario 7: Enhanced CDD - High amount
            [
                'id' => 'S7',
                'branch' => 'BR002',
                'type' => 'Sell',
                'currency' => 'AUD',
                'foreign_amount' => '20000',
                'rate' => '3.20',
                'customer_name' => 'Wei Jianlong',
                'customer_ic' => 'P987654321',
                'customer_type' => 'individual',
                'nationality' => 'CN',
                'is_pep' => false,
                'is_high_risk_country' => false,
                'expected_cdd' => 'Enhanced',
                'description' => 'High value transaction, RM 64,000',
            ],
            // Scenario 8: PEP Customer
            [
                'id' => 'S8',
                'branch' => 'BR003',
                'type' => 'Buy',
                'currency' => 'SGD',
                'foreign_amount' => '1500',
                'rate' => '3.40',
                'customer_name' => 'YB Datuk Seri',
                'customer_ic' => '600101-18-6789',
                'customer_type' => 'individual',
                'nationality' => 'MY',
                'is_pep' => true,
                'is_high_risk_country' => false,
                'expected_cdd' => 'Enhanced',
                'description' => 'PEP customer, RM 5,100 (any amount = Enhanced)',
            ],
            // Scenario 9: High-Risk Country
            [
                'id' => 'S9',
                'branch' => 'BR003',
                'type' => 'Buy',
                'currency' => 'USD',
                'foreign_amount' => '800',
                'rate' => '4.50',
                'customer_name' => 'Ali Akbar Tehrani',
                'customer_ic' => 'I123456789',
                'customer_type' => 'individual',
                'nationality' => 'IR', // Iran - high risk
                'is_pep' => false,
                'is_high_risk_country' => true,
                'expected_cdd' => 'Enhanced',
                'description' => 'High-risk country (Iran), RM 3,600',
            ],
            // Scenario 10: Structuring Pattern (multiple small transactions)
            [
                'id' => 'S10',
                'branch' => 'HQ',
                'type' => 'Buy',
                'currency' => 'USD',
                'foreign_amount' => '666',
                'rate' => '4.50',
                'customer_name' => 'Abu Structure',
                'customer_ic' => '900909-19-7890',
                'customer_type' => 'individual',
                'nationality' => 'MY',
                'is_pep' => false,
                'is_high_risk_country' => false,
                'expected_cdd' => 'Simplified',
                'description' => 'First of 3 structuring transactions (will trigger alert)',
                'structuring' => true,
            ],
        ];

        $this->info('=== EXECUTING TEST SCENARIOS ===');
        $this->info('');

        foreach ($scenarios as $scenario) {
            $this->executeScenario($scenario);
        }

        $this->info('');
    }

    protected function executeScenario(array $scenario): void
    {
        $this->info("  [{$scenario['id']}] {$scenario['description']}");
        $this->info("         Customer: {$scenario['customer_name']} ({$scenario['nationality']})");
        $this->info("         Amount: {$scenario['foreign_amount']} {$scenario['currency']} @ {$scenario['rate']} = RM ".number_format((float) $scenario['foreign_amount'] * (float) $scenario['rate'], 2));

        // Create or find customer by ID number hash
        $customer = Customer::where('id_number_hash', hash('sha256', $scenario['customer_ic']))->first();

        if (! $customer) {
            $customer = Customer::create([
                'full_name' => $scenario['customer_name'],
                'id_type' => 'MyKad',
                'id_number_encrypted' => encrypt($scenario['customer_ic']),
                'id_number_hash' => hash('sha256', $scenario['customer_ic']),
                'date_of_birth' => '1990-01-15',
                'customer_type' => $scenario['customer_type'],
                'nationality' => $scenario['nationality'],
                'branch_id' => Branch::where('code', $scenario['branch'])->first()->id,
                'risk_rating' => $scenario['is_pep'] ? 'high' : ($scenario['is_high_risk_country'] ? 'high' : 'medium'),
                'pep_status' => $scenario['is_pep'],
                'is_pep_associate' => false,
                'sanction_hit' => false,
                'is_active' => true,
                'created_by' => $this->tellers[$scenario['branch']]->id,
            ]);
        }

        // Mark PEP status
        if ($scenario['is_pep']) {
            $customer->update(['is_pep' => true]);
        }

        // Calculate MYR amount
        $myrAmount = bcmul($scenario['foreign_amount'], $scenario['rate'], 2);

        // Create transaction
        $counter = $this->counters[$scenario['branch']];
        $teller = $this->tellers[$scenario['branch']];

        $transaction = Transaction::create([
            'counter_id' => $counter->id,
            'branch_id' => Branch::where('code', $scenario['branch'])->first()->id,
            'user_id' => $teller->id,
            'customer_id' => $customer->id,
            'type' => $scenario['type'] === 'Buy' ? TransactionType::Buy : TransactionType::Sell,
            'currency_code' => $scenario['currency'],
            'amount_foreign' => $scenario['foreign_amount'],
            'rate' => $scenario['rate'],
            'amount_local' => $myrAmount,
            'myr_at_closing' => $myrAmount,
            'status' => TransactionStatus::PendingApproval,
            'reference_no' => 'TXN-'.$scenario['id'].'-'.uniqid(),
            'idempotency_key' => uniqid(),
            'cdd_level' => $scenario['expected_cdd'],
        ]);

        // Store result
        $this->results[] = [
            'id' => $scenario['id'],
            'reference_no' => $transaction->reference_no,
            'customer' => $scenario['customer_name'],
            'branch' => $scenario['branch'],
            'type' => $scenario['type'],
            'currency' => $scenario['currency'],
            'foreign_amount' => $scenario['foreign_amount'],
            'myr_amount' => $myrAmount,
            'expected_cdd' => $scenario['expected_cdd'],
            'actual_cdd' => $transaction->cdd_level,
            'status' => $transaction->status->value,
            'is_pep' => $scenario['is_pep'],
            'is_high_risk_country' => $scenario['is_high_risk_country'],
        ];

        $this->info("         Status: {$transaction->status->value} | CDD: {$transaction->cdd_level->value}");
        $this->info('');
    }

    protected function displayReport(): void
    {
        $this->info('=== TEST RESULTS SUMMARY ===');
        $this->info('');

        $headers = ['ID', 'Customer', 'Branch', 'Type', 'Currency', 'MYR Amount', 'Expected CDD', 'Actual CDD', 'Status', 'Flags'];
        $rows = [];

        foreach ($this->results as $result) {
            $flags = [];
            if ($result['is_pep']) {
                $flags[] = 'PEP';
            }
            if ($result['is_high_risk_country']) {
                $flags[] = 'HR-Country';
            }

            $rows[] = [
                $result['id'],
                $result['customer'],
                $result['branch'],
                $result['type'],
                $result['currency'].' '.$result['foreign_amount'],
                'RM '.number_format((float) $result['myr_amount'], 2),
                $result['expected_cdd'],
                $result['actual_cdd']->value,
                $result['status'],
                implode(', ', $flags) ?: '-',
            ];
        }

        $this->table($headers, $rows);

        // Summary statistics
        $this->info('');
        $this->info('=== SUMMARY ===');

        $simplified = collect($this->results)->where('expected_cdd', 'Simplified')->count();
        $standard = collect($this->results)->where('expected_cdd', 'Standard')->count();
        $enhanced = collect($this->results)->where('expected_cdd', 'Enhanced')->count();
        $passed = collect($this->results)->where('expected_cdd', 'actual_cdd')->count();

        $this->info("  Simplified CDD: {$simplified} transactions");
        $this->info("  Standard CDD: {$standard} transactions");
        $this->info("  Enhanced CDD: {$enhanced} transactions");
        $this->info('  Total: '.count($this->results).' transactions');

        // Store for verification
        file_put_contents(storage_path('test_results.json'), json_encode($this->results, JSON_PRETTY_PRINT));
        $this->info('');
        $this->info('Detailed results saved to: '.storage_path('test_results.json'));
    }
}
