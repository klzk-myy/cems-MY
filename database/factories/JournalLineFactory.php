<?php

namespace Database\Factories;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JournalLine>
 */
class JournalLineFactory extends Factory
{
    protected $model = \App\Models\JournalLine::class;

    public function definition(): array
    {
        // Get or create a ChartOfAccount for the account_code
        $account = ChartOfAccount::factory()->create();
        $amount = fake()->randomFloat(4, 1, 1000);

        // randomly decide if this is a debit or credit line
        $isDebit = fake()->boolean();

        return [
            'journal_entry_id' => JournalEntry::factory(),
            'account_code' => $account->account_code,
            'debit' => $isDebit ? $amount : 0,
            'credit' => $isDebit ? 0 : $amount,
            'description' => fake()->sentence(),
        ];
    }

    public function debit(): static
    {
        return $this->state(fn (array $attributes) => [
            'debit' => fake()->randomFloat(4, 1, 1000),
            'credit' => 0,
        ]);
    }

    public function credit(): static
    {
        return $this->state(fn (array $attributes) => [
            'debit' => 0,
            'credit' => fake()->randomFloat(4, 1, 1000),
        ]);
    }
}
