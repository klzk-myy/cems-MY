<?php

namespace Database\Factories;

use App\Models\ExchangeRate;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExchangeRateFactory extends Factory
{
    protected $model = ExchangeRate::class;

    public function definition(): array
    {
        return [
            'currency_code' => 'USD',
            'rate_buy' => '4.5000',
            'rate_sell' => '4.6000',
            'rate_unit' => 1,
            'rate_inverse' => false,
            'source' => 'api',
            'fetched_at' => now(),
        ];
    }
}
