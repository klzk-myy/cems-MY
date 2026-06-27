<?php

namespace Database\Seeders;

use App\Enums\CddLevel;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Counter;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;

class LargeTransactionTestSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Creating 100 test transactions...');

        // Ensure prerequisites exist
        $this->ensurePrerequisites();

        // Get data for transactions
        $teller = User::where('role', 'teller')->first() ?? User::first();
        $customers = Customer::all();

        if ($customers->isEmpty()) {
            $this->command->error('No customers found. Please run TestTransactionWizardSeeder first.');

            return;
        }

        $currencies = ['USD', 'EUR', 'GBP', 'SGD', 'AUD'];
        $purposes = ['Travel', 'Business', 'Education', 'Medical', 'Family Support', 'Investment'];
        $sourceOfFunds = ['Salary', 'Business Income', 'Savings', 'Investment Returns', 'Loan', 'Gift'];

        $transactionsCreated = 0;
        $statuses = [
            TransactionStatus::Completed,
            TransactionStatus::Completed,
            TransactionStatus::Completed,
            TransactionStatus::Draft,
            TransactionStatus::PendingApproval,
            TransactionStatus::Approved,
        ];

        $cddLevels = [CddLevel::Simplified, CddLevel::Specific, CddLevel::Standard, CddLevel::Enhanced];

        // Create 100 transactions
        for ($i = 0; $i < 100; $i++) {
            $customer = $customers->random();
            $type = fake()->randomElement([TransactionType::Buy, TransactionType::Sell]);
            $currency = fake()->randomElement($currencies);
            $amountForeign = fake()->randomFloat(2, 50, 5000);
            $rate = fake()->randomFloat(4, 3, 8);
            $amountLocal = $amountForeign * $rate;

            // Random date within last 30 days
            $createdAt = fake()->dateTimeBetween('-30 days', 'now');

            // Some older transactions for variety
            if (fake()->boolean(10)) {
                $createdAt = fake()->dateTimeBetween('-90 days', '-30 days');
            }

            $transaction = Transaction::create([
                'customer_id' => $customer->id,
                'user_id' => $teller->id,
                'type' => $type,
                'currency_code' => $currency,
                'amount_foreign' => $amountForeign,
                'amount_local' => $amountLocal,
                'rate' => $rate,
                'cdd_level' => fake()->randomElement($cddLevels),
                'purpose' => fake()->randomElement($purposes),
                'source_of_funds' => fake()->randomElement($sourceOfFunds),
                'till_id' => '1',
                'idempotency_key' => uniqid('seed_', true),
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            $transaction->status = fake()->randomElement($statuses);
            $transaction->save();

            $transactionsCreated++;
        }

        $this->command->info("✓ Successfully created {$transactionsCreated} transactions");
    }

    private function ensurePrerequisites(): void
    {
        // Ensure currencies exist
        Currency::firstOrCreate(
            ['code' => 'USD'],
            ['name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2, 'is_active' => true]
        );
        Currency::firstOrCreate(
            ['code' => 'EUR'],
            ['name' => 'Euro', 'symbol' => '€', 'decimal_places' => 2, 'is_active' => true]
        );
        Currency::firstOrCreate(
            ['code' => 'GBP'],
            ['name' => 'British Pound', 'symbol' => '£', 'decimal_places' => 2, 'is_active' => true]
        );

        // Ensure counter/till exists
        Counter::firstOrCreate(
            ['id' => '1'],
            ['name' => 'Main Counter', 'branch_id' => 1, 'is_active' => true]
        );

        // Note: Till balances are created by counter opening workflow, not here

        // Ensure at least one customer exists
        if (Customer::count() === 0) {
            $customer = Customer::create([
                'full_name' => 'Test Customer',
                'id_type' => 'MyKad',
                'id_number_encrypted' => encrypt('900101-01-1234'),
                'nationality' => 'Malaysian',
                'date_of_birth' => '1990-01-01',
                'address' => '123 Test Street',
                'phone' => '+60123456789',
                'pep_status' => false,
                'is_active' => true,
            ]);

            $customer->sanction_hit = false;
            $customer->risk_rating = 'Low';
            $customer->save();
        }

        // Ensure at least one user exists
        if (User::count() === 0) {
            $user = User::create([
                'username' => 'Test Teller',
                'email' => 'teller@test.com',
                'role' => 'teller',
                'branch_id' => 1,
                'is_active' => true,
            ]);

            $user->password_hash = bcrypt('password');
            $user->save();
        }
    }
}
