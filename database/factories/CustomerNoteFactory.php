<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerNoteFactory extends Factory
{
    protected $model = CustomerNote::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'created_by' => User::factory(),
            'note' => $this->faker->paragraph(),
        ];
    }
}
