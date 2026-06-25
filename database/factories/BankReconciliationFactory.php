<?php

namespace Database\Factories;

use App\Models\BankReconciliation;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankReconciliation>
 */
class BankReconciliationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_code' => ChartOfAccount::factory(),
            'statement_date' => $this->faker->date(),
            'reference' => 'REF-'.$this->faker->unique()->numberBetween(1000, 999999),
            'description' => $this->faker->sentence(),
            'debit' => $this->faker->randomFloat(2, 0, 5000),
            'credit' => $this->faker->randomFloat(2, 0, 5000),
            'status' => $this->faker->randomElement(['matched', 'unmatched', 'exception']),
            'matched_to_journal_entry_id' => JournalEntry::factory()->create()->id,
            'created_by' => User::factory(),
            'matched_at' => $this->faker->optional()->dateTimeThisMonth(),
            'notes' => $this->faker->optional()->sentence(),
            'check_number' => $this->faker->optional()->randomNumber(5),
            'check_date' => $this->faker->optional()->date(),
            'check_status' => $this->faker->optional()->randomElement(['issued', 'presented', 'cleared', 'returned', 'stopped']),
            'check_payee' => $this->faker->optional()->name(),
        ];
    }
}
