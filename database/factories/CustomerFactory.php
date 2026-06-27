<?php

namespace Database\Factories;

use App\Enums\CddLevel;
use App\Enums\RiskRating;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'full_name' => fake()->name(),
            'id_type' => fake()->randomElement(['MyKad', 'Passport', 'Others']),
            'id_number_encrypted' => fake()->uuid(),
            'nationality' => fake()->country(),
            'date_of_birth' => fake()->dateTimeBetween('-70 years', '-18 years'),
            'address' => fake()->address(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->optional()->safeEmail(),
            'pep_status' => false,
            'is_active' => true,
        ];
    }

    public function make($attributes = [], ?Model $parent = null): Customer|Collection
    {
        $raw = $this->raw();
        $result = parent::make($attributes, $parent);
        $customers = $result instanceof Collection
            ? $result
            : new Collection([$result]);

        $workflowFields = [
            'risk_score',
            'risk_rating',
            'risk_assessed_at',
            'cdd_level',
            'is_frozen',
            'freeze_reason',
            'frozen_at',
            'transactions_blocked',
            'rejection_reason',
            'sanction_hit',
        ];

        $customers->each(function (Customer $customer) use ($raw, $workflowFields) {
            foreach ($workflowFields as $field) {
                if (array_key_exists($field, $raw)) {
                    $customer->{$field} = $raw[$field];
                }
            }

            $customer->risk_score ??= fake()->numberBetween(0, 100);
            $customer->risk_rating ??= fake()->randomElement([
                RiskRating::Low->value,
                RiskRating::Medium->value,
                RiskRating::High->value,
            ]);
            $customer->risk_assessed_at ??= now();
            $customer->cdd_level ??= fake()->randomElement(CddLevel::cases());
            $customer->is_frozen ??= false;
            $customer->transactions_blocked ??= false;
            $customer->sanction_hit ??= false;
        });

        return $result;
    }

    public function frozen(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_frozen' => true,
        ]);
    }

    public function pep(): static
    {
        return $this->state(fn (array $attributes) => [
            'pep_status' => true,
        ]);
    }

    public function sanctioned(): static
    {
        return $this->state(fn (array $attributes) => [
            'sanction_hit' => true,
        ]);
    }

    public function highRisk(): static
    {
        return $this->state(fn (array $attributes) => [
            'risk_rating' => RiskRating::High,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
