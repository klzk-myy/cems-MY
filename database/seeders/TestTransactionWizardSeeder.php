<?php

namespace Database\Seeders;

use App\Enums\CddLevel;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Counter;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\TillBalance;
use App\Models\Transaction;
use Illuminate\Database\Seeder;

class TestTransactionWizardSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding test data for Transaction Wizard...');

        // Ensure we have currencies
        $this->seedCurrencies();

        // Ensure we have counters/tills
        $this->seedCounters();

        // Create test customers for different scenarios
        $this->seedTestCustomers();

        // Create transaction history for returning customers
        $this->seedTransactionHistory();

        $this->command->info('Test data seeding complete!');
    }

    private function seedCurrencies(): void
    {
        $currencies = [
            ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'rate' => 4.50],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'rate' => 5.20],
            ['code' => 'GBP', 'name' => 'British Pound', 'symbol' => '£', 'rate' => 6.10],
        ];

        foreach ($currencies as $currency) {
            Currency::firstOrCreate(
                ['code' => $currency['code']],
                [
                    'name' => $currency['name'],
                    'symbol' => $currency['symbol'],
                    'decimal_places' => 2,
                    'is_active' => true,
                ]
            );
        }

        $this->command->info('Currencies seeded');
    }

    private function seedCounters(): void
    {
        // Create main counter
        Counter::firstOrCreate(
            ['id' => '1'],
            [
                'name' => 'Main Counter',
                'branch_id' => 1,
                'is_active' => true,
            ]
        );

        // Create till balance for today
        TillBalance::firstOrCreate(
            [
                'till_id' => '1',
                'currency_code' => 'USD',
                'date' => today(),
            ],
            [
                'opening_balance' => '10000.00',
                'transaction_total' => '0',
                'foreign_total' => '0',
                'user_id' => 1,
                'branch_id' => 1,
            ]
        );

        $this->command->info('Counters seeded');
    }

    private function seedTestCustomers(): void
    {
        // 1. Simplified CDD Customer
        Customer::firstOrCreate(
            ['email' => 'simplified@example.com'],
            [
                'full_name' => 'Simplified Test Customer',
                'id_type' => 'MyKad',
                'id_number_encrypted' => encrypt('900101-01-1234'),
                'nationality' => 'Malaysian',
                'date_of_birth' => '1990-01-01',
                'address' => '123 Jalan Test, Kuala Lumpur',
                'phone' => '+60123456789',
                'pep_status' => false,
                'sanction_hit' => false,
                'risk_rating' => 'Low',
                'is_active' => true,
            ]
        );

        // 2. Standard CDD Customer
        Customer::firstOrCreate(
            ['email' => 'standard@example.com'],
            [
                'full_name' => 'Standard Test Customer',
                'id_type' => 'MyKad',
                'id_number_encrypted' => encrypt('900102-02-5678'),
                'nationality' => 'Malaysian',
                'date_of_birth' => '1990-02-02',
                'address' => '456 Jalan Standard, Kuala Lumpur',
                'phone' => '+60123456790',
                'pep_status' => false,
                'sanction_hit' => false,
                'risk_rating' => 'Medium',
                'is_active' => true,
            ]
        );

        // 3. Enhanced CDD Customer (PEP)
        Customer::firstOrCreate(
            ['email' => 'enhanced@example.com'],
            [
                'full_name' => 'Enhanced Test Customer',
                'id_type' => 'Passport',
                'id_number_encrypted' => encrypt('A12345678'),
                'nationality' => 'Malaysian',
                'date_of_birth' => '1980-03-03',
                'address' => '789 Jalan VIP, Kuala Lumpur',
                'phone' => '+60123456791',
                'pep_status' => true,
                'sanction_hit' => false,
                'risk_rating' => 'High',
                'is_active' => true,
            ]
        );

        // 4. Sanctioned Customer
        Customer::firstOrCreate(
            ['email' => 'sanctioned@example.com'],
            [
                'full_name' => 'Sanctioned Test Customer',
                'id_type' => 'MyKad',
                'id_number_encrypted' => encrypt('900104-04-9012'),
                'nationality' => 'Malaysian',
                'date_of_birth' => '1990-04-04',
                'address' => '321 Jalan Blocked, Kuala Lumpur',
                'phone' => '+60123456792',
                'pep_status' => false,
                'sanction_hit' => true,
                'risk_rating' => 'High',
                'is_active' => true,
            ]
        );

        // 5. Returning Customer (for velocity testing)
        Customer::firstOrCreate(
            ['email' => 'returning@example.com'],
            [
                'full_name' => 'Returning Test Customer',
                'id_type' => 'MyKad',
                'id_number_encrypted' => encrypt('900105-05-3456'),
                'nationality' => 'Malaysian',
                'date_of_birth' => '1990-05-05',
                'address' => '654 Jalan Regular, Kuala Lumpur',
                'phone' => '+60123456793',
                'pep_status' => false,
                'sanction_hit' => false,
                'risk_rating' => 'Low',
                'is_active' => true,
            ]
        );

        $this->command->info('Test customers seeded');
    }

    private function seedTransactionHistory(): void
    {
        $returningCustomer = Customer::where('email', 'returning@example.com')->first();

        if ($returningCustomer && $returningCustomer->transactions()->count() === 0) {
            // Create 5 recent transactions for velocity testing
            for ($i = 0; $i < 5; $i++) {
                Transaction::create([
                    'customer_id' => $returningCustomer->id,
                    'user_id' => 1,
                    'type' => TransactionType::Buy,
                    'currency_code' => 'USD',
                    'amount_foreign' => '100.00',
                    'amount_local' => '450.00',
                    'rate' => '4.50',
                    'status' => TransactionStatus::Completed,
                    'cdd_level' => CddLevel::Simplified,
                    'purpose' => 'Travel',
                    'source_of_funds' => 'Salary',
                    'till_id' => '1',
                    'idempotency_key' => uniqid('seed_', true),
                    'created_at' => now()->subHours(2 - $i),
                ]);
            }

            // Create 2 structuring pattern transactions (just below RM 3K)
            for ($i = 0; $i < 2; $i++) {
                Transaction::create([
                    'customer_id' => $returningCustomer->id,
                    'user_id' => 1,
                    'type' => TransactionType::Buy,
                    'currency_code' => 'USD',
                    'amount_foreign' => '650.00',
                    'amount_local' => '2925.00',
                    'rate' => '4.50',
                    'status' => TransactionStatus::Completed,
                    'cdd_level' => CddLevel::Simplified,
                    'purpose' => 'Travel',
                    'source_of_funds' => 'Salary',
                    'till_id' => '1',
                    'idempotency_key' => uniqid('seed_', true),
                    'created_at' => now()->subMinutes(30 - $i * 10),
                ]);
            }

            $this->command->info('Transaction history seeded for velocity/structuring tests');
        }
    }
}
