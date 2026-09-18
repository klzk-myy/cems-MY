<?php

namespace Database\Factories;

use App\Enums\PoolRemittanceStatus;
use App\Models\PoolRemittance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PoolRemittance>
 */
class PoolRemittanceFactory extends Factory
{
    protected $model = PoolRemittance::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'remittance_number' => 'REM-'.now()->format('Ymd').'-'.str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'from_branch_id' => null,
            'to_branch_id' => null,
            'currency_code' => 'MYR',
            'amount' => '100.0000',
            'status' => PoolRemittanceStatus::Pending,
            'initiated_by' => null,
            'initiated_at' => now(),
        ];
    }
}
