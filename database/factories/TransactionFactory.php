<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        $amountLocal = fake()->randomFloat(2, 100, 100000);
        $rate = fake()->randomFloat(6, 3.5, 5.0);
        $amountForeign = round($amountLocal / $rate, 4);

        return [
            'customer_id' => Customer::factory(),
            'user_id' => User::factory(),
            'branch_id' => Branch::factory(),
            'till_id' => 'MAIN',
            'type' => fake()->randomElement(['Buy', 'Sell']),
            'currency_code' => fn () => Currency::inRandomOrder()->first()?->code ?? Currency::factory()->create()->code,
            'amount_local' => $amountLocal,
            'amount_foreign' => $amountForeign,
            'rate' => $rate,
            'purpose' => fake()->sentence(3),
            'source_of_funds' => fake()->randomElement(['Salary', 'Business', 'Savings', 'Investment']),
            'status' => 'Completed',
            'hold_reason' => null,
            'approved_by' => null,
            'approved_at' => null,
            'cdd_level' => 'Standard',
        ];
    }

    public function buy(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'Buy',
        ]);
    }

    public function sell(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'Sell',
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Completed',
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Pending',
        ]);
    }

    public function largeAmount(): static
    {
        return $this->state(fn (array $attributes) => [
            'amount_local' => fake()->randomFloat(2, 50000, 200000),
        ]);
    }
}
