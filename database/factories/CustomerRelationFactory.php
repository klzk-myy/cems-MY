<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerRelation;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerRelationFactory extends Factory
{
    protected $model = CustomerRelation::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'related_customer_id' => null,
            'relation_type' => $this->faker->randomElement([
                'spouse', 'child', 'parent', 'sibling',
                'close_associate', 'business_partner',
                'beneficial_owner', 'director', 'signatory',
                'related_entity',
            ]),
            'related_name' => $this->faker->name(),
            'id_type' => $this->faker->randomElement(['MyKad', 'Passport', null]),
            'id_number_encrypted' => null,
            'date_of_birth' => $this->faker->date(),
            'nationality' => 'MY',
            'address' => $this->faker->address(),
            'is_pep' => false,
            'additional_info' => null,
        ];
    }

    public function pep(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_pep' => true,
        ]);
    }

    public function spouse(): static
    {
        return $this->state(fn (array $attributes) => [
            'relation_type' => 'spouse',
        ]);
    }

    public function closeAssociate(): static
    {
        return $this->state(fn (array $attributes) => [
            'relation_type' => 'close_associate',
        ]);
    }

    public function child(): static
    {
        return $this->state(fn (array $attributes) => [
            'relation_type' => 'child',
        ]);
    }
}
