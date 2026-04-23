<?php

namespace Database\Factories;

use App\Models\FiscalYear;
use Illuminate\Database\Eloquent\Factories\Factory;

class FiscalYearFactory extends Factory
{
    protected $model = FiscalYear::class;

    public function definition(): array
    {
        $year = (int) date('Y');

        return [
            'year_code' => (string) $year,
            'start_date' => "{$year}-01-01",
            'end_date' => "{$year}-12-31",
            'status' => 'Open',
        ];
    }

    public function forYear(int $year): static
    {
        return $this->state(fn (array $attributes) => [
            'year_code' => (string) $year,
            'start_date' => "{$year}-01-01",
            'end_date' => "{$year}-12-31",
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Closed',
        ]);
    }
}
