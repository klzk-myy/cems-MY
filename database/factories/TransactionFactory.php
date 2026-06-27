<?php

namespace Database\Factories;

use App\Enums\CddLevel;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

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
            'type' => fake()->randomElement([
                TransactionType::Buy->value,
                TransactionType::Sell->value,
            ]),
            'currency_code' => fn () => Currency::inRandomOrder()->first()?->code ?? Currency::factory()->create()->code,
            'amount_local' => $amountLocal,
            'amount_foreign' => $amountForeign,
            'rate' => $rate,
            'purpose' => fake()->sentence(3),
            'source_of_funds' => fake()->randomElement(['Salary', 'Business', 'Savings', 'Investment']),
            'cdd_level' => CddLevel::Standard->value,
        ];
    }

    public function make($attributes = [], ?Model $parent = null): Transaction|Collection
    {
        $raw = $this->raw();
        $result = parent::make($attributes, $parent);
        $transactions = $result instanceof Collection
            ? $result
            : new Collection([$result]);

        $workflowFields = [
            'status',
            'approved_by',
            'approved_at',
            'hold_reason',
            'version',
            'transition_history',
            'is_refund',
            'cancelled_at',
            'cancelled_by',
            'cancellation_reason',
        ];

        $transactions->each(function (Transaction $transaction) use ($raw, $workflowFields) {
            foreach ($workflowFields as $field) {
                if (array_key_exists($field, $raw)) {
                    $transaction->{$field} = $raw[$field];
                }
            }

            $transaction->status ??= TransactionStatus::Completed;
            $transaction->version ??= 0;
            $transaction->transition_history ??= [];
        });

        return $result;
    }

    public function buy(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TransactionType::Buy->value,
        ]);
    }

    public function sell(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TransactionType::Sell->value,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TransactionStatus::Completed->value,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TransactionStatus::Pending->value,
        ]);
    }

    public function largeAmount(): static
    {
        return $this->state(fn (array $attributes) => [
            'amount_local' => fake()->randomFloat(2, 50000, 200000),
        ]);
    }
}
