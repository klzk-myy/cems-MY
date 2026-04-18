<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\StrReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class StrReportFactory extends Factory
{
    protected $model = StrReport::class;

    public function definition(): array
    {
        return [
            'str_no' => 'STR-'.date('Y').'-'.str_pad($this->faker->unique()->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
            'branch_id' => 1,
            'customer_id' => Customer::factory(),
            'transaction_ids' => json_encode([1, 2, 3]),
            'reason' => $this->faker->paragraph(),
            'status' => $this->faker->randomElement(['draft', 'pending_review', 'submitted', 'acknowledged']),
            'filing_deadline' => now()->addDays(3),
            'created_by' => User::factory(),
        ];
    }
}
