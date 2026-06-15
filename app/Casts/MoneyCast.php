<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use InvalidArgumentException;

class MoneyCast implements CastsAttributes
{
    public function __construct(protected int $scale = 4) {}

    public function get($model, string $key, $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return $this->round($value);
    }

    public function set($model, string $key, $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_numeric($value)) {
            throw new InvalidArgumentException("{$key} must be numeric.");
        }

        return $this->round($value);
    }

    private function round(string|int|float $value): string
    {
        $value = (string) $value;
        $increment = $this->roundingIncrement();

        // Apply sign to increment for negative values
        if (str_starts_with($value, '-')) {
            $increment = '-'.$increment;
        }

        return bcadd($value, $increment, $this->scale);
    }

    private function roundingIncrement(): string
    {
        return '0.'.str_repeat('0', $this->scale).'5';
    }
}
