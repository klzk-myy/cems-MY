<?php

namespace Database\Factories;

use App\Models\AccountingPeriod;
use App\Models\FiscalYear;
use Illuminate\Database\Eloquent\Factories\Factory;

class AccountingPeriodFactory extends Factory
{
    protected $model = AccountingPeriod::class;

    public function definition(): array
    {
        return [
            'fiscal_year_id' => null,
            'period_code' => date('Y').'-01',
            'period_type' => 'month',
            'start_date' => date('Y').'-01-01',
            'end_date' => date('Y').'-01-31',
            'status' => 'open',
        ];
    }

    public function open(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'open',
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'closed',
        ]);
    }

    public function forMonth(int $month, ?int $fiscalYearId = null): static
    {
        $year = $fiscalYearId ? FiscalYear::find($fiscalYearId)?->year_code ?? date('Y') : date('Y');
        $monthStr = str_pad($month, 2, '0', STR_PAD_LEFT);

        return $this->state(fn (array $attributes) => [
            'fiscal_year_id' => $fiscalYearId,
            'period_code' => "{$year}-{$monthStr}",
            'start_date' => "{$year}-{$monthStr}-01",
            'end_date' => "{$year}-{$monthStr}-31",
        ]);
    }
}
