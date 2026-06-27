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
        $this->seedCustomer(
            'simplified@example.com',
            'Simplified Test Customer',
            'MyKad',
            encrypt('900101-01-1234'),
            '1990-01-01',
            '123 Jalan Test, Kuala Lumpur',
            '+60123456789',
            false,
            false,
            'Low'
        );

        $this->seedCustomer(
            'standard@example.com',
            'Standard Test Customer',
            'MyKad',
            encrypt('900102-02-5678'),
            '1990-02-02',
            '456 Jalan Standard, Kuala Lumpur',
            '+60123456790',
            false,
            false,
            'Medium'
        );

        $this->seedCustomer(
            'enhanced@example.com',
            'Enhanced Test Customer',
            'Passport',
            encrypt('A12345678'),
            '1980-03-03',
            '789 Jalan VIP, Kuala Lumpur',
            '+60123456791',
            true,
            false,
            'High'
        );

        $this->seedCustomer(
            'sanctioned@example.com',
            'Sanctioned Test Customer',
            'MyKad',
            encrypt('900104-04-9012'),
            '1990-04-04',
            '321 Jalan Blocked, Kuala Lumpur',
            '+60123456792',
            false,
            true,
            'High'
        );

        $this->seedCustomer(
            'returning@example.com',
            'Returning Test Customer',
            'MyKad',
            encrypt('900105-05-3456'),
            '1990-05-05',
            '654 Jalan Regular, Kuala Lumpur',
            '+60123456793',
            false,
            false,
            'Low'
        );

        $this->command->info('Test customers seeded');
    }

    private function seedCustomer(
        string $email,
        string $fullName,
        string $idType,
        string $idNumberEncrypted,
        string $dateOfBirth,
        string $address,
        string $phone,
        bool $pepStatus,
        bool $sanctionHit,
        string $riskRating
    ): void {
        $customer = Customer::firstOrNew(['email' => $email]);

        if (! $customer->exists) {
            $customer->fill([
                'full_name' => $fullName,
                'id_type' => $idType,
                'id_number_encrypted' => $idNumberEncrypted,
                'nationality' => 'Malaysian',
                'date_of_birth' => $dateOfBirth,
                'address' => $address,
                'phone' => $phone,
                'pep_status' => $pepStatus,
                'is_active' => true,
            ]);
            $customer->sanction_hit = $sanctionHit;
            $customer->risk_rating = $riskRating;
            $customer->save();
        }
    }

    private function seedTransactionHistory(): void
    {
        $returningCustomer = Customer::where('email', 'returning@example.com')->first();

        if ($returningCustomer && $returningCustomer->transactions()->count() === 0) {
            // Create 5 recent transactions for velocity testing
            for ($i = 0; $i < 5; $i++) {
                $transaction = Transaction::create([
                    'customer_id' => $returningCustomer->id,
                    'user_id' => 1,
                    'type' => TransactionType::Buy,
                    'currency_code' => 'USD',
                    'amount_foreign' => '100.00',
                    'amount_local' => '450.00',
                    'rate' => '4.50',
                    'cdd_level' => CddLevel::Simplified,
                    'purpose' => 'Travel',
                    'source_of_funds' => 'Salary',
                    'till_id' => '1',
                    'idempotency_key' => uniqid('seed_', true),
                    'created_at' => now()->subHours(2 - $i),
                ]);

                $transaction->status = TransactionStatus::Completed;
                $transaction->save();
            }

            // Create 2 structuring pattern transactions (just below RM 3K)
            for ($i = 0; $i < 2; $i++) {
                $transaction = Transaction::create([
                    'customer_id' => $returningCustomer->id,
                    'user_id' => 1,
                    'type' => TransactionType::Buy,
                    'currency_code' => 'USD',
                    'amount_foreign' => '650.00',
                    'amount_local' => '2925.00',
                    'rate' => '4.50',
                    'cdd_level' => CddLevel::Simplified,
                    'purpose' => 'Travel',
                    'source_of_funds' => 'Salary',
                    'till_id' => '1',
                    'idempotency_key' => uniqid('seed_', true),
                    'created_at' => now()->subMinutes(30 - $i * 10),
                ]);

                $transaction->status = TransactionStatus::Completed;
                $transaction->save();
            }

            $this->command->info('Transaction history seeded for velocity/structuring tests');
        }
    }
}
