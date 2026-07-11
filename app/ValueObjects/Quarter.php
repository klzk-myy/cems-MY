<?php

namespace App\ValueObjects;

use Carbon\Carbon;

final class Quarter
{
    public function __construct(
        public readonly int $year,
        public readonly int $quarter,
    ) {
        if ($quarter < 1 || $quarter > 4) {
            throw new \InvalidArgumentException("Quarter must be between 1 and 4, got {$quarter}");
        }
    }

    public static function fromString(string $quarter): self
    {
        if (! preg_match('/^(\d{4})-Q([1-4])$/', $quarter, $matches)) {
            throw new \InvalidArgumentException("Invalid quarter format: {$quarter}. Expected YYYY-QN.");
        }

        return new self((int) $matches[1], (int) $matches[2]);
    }

    public function toString(): string
    {
        return "{$this->year}-Q{$this->quarter}";
    }

    public function startDate(): Carbon
    {
        $month = ($this->quarter - 1) * 3 + 1;

        return Carbon::create($this->year, $month, 1)->startOfDay();
    }

    public function endDate(): Carbon
    {
        return $this->startDate()->copy()->addMonths(3)->subDay()->endOfDay();
    }
}
