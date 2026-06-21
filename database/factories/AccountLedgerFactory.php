<?php

namespace Database\Factories;

use App\Models\AccountLedger;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountLedger>
 */
class AccountLedgerFactory extends Factory
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
            'journal_entry_id' => JournalEntry::factory(),
            'entry_date' => $this->faker->date(),
            'debit' => $this->faker->randomFloat(2, 0, 10000),
            'credit' => $this->faker->randomFloat(2, 0, 10000),
            'running_balance' => $this->faker->randomFloat(2, -10000, 10000),
        ];
    }
}
