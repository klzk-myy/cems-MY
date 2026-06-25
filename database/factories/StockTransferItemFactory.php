<?php

namespace Database\Factories;

use App\Models\Currency;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockTransferItem>
 */
class StockTransferItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'stock_transfer_id' => StockTransfer::factory(),
            'currency_code' => Currency::factory(),
            'quantity' => $this->faker->randomFloat(2, 10, 1000),
            'rate' => $this->faker->randomFloat(6, 0.5, 10),
            'value_myr' => $this->faker->randomFloat(2, 100, 50000),
            'quantity_received' => 0,
            'quantity_in_transit' => 0,
            'variance_notes' => null,
        ];
    }
}
