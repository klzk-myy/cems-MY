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
        $value = $this->normalizeInput((string) $value);
        $isNegative = str_starts_with($value, '-');
        $absValue = ltrim($isNegative ? substr($value, 1) : $value, '+');

        // Work on the decimal string directly so rounding decisions are never
        // lost to float precision or bcmath scale truncation.
        [$intPart, $fracPart] = array_pad(explode('.', $absValue, 2), 2, '');
        $intPart = $intPart === '' ? '0' : $intPart;

        // Digits we keep, plus the first dropped digit.
        $keptFrac = str_pad(substr($fracPart, 0, $this->scale), $this->scale, '0');
        $roundDigit = (int) ($fracPart[$this->scale] ?? '0');

        // Half-up rounding (round half away from zero): a rounding digit of 5
        // or more always rounds up. This matches MathService::round() so casted
        // attributes and value-object math agree - BNM reconciliation treats
        // mixed half-even/half-up conventions as drift. The sign is reapplied
        // afterwards on the absolute value, so "up" means away from zero.
        $shouldRoundUp = $roundDigit >= 5;

        // Rebuild the scaled integer (value * 10^scale) and carry the increment if needed.
        $operand = $intPart.$keptFrac;

        if (! ctype_digit($operand)) {
            throw new InvalidArgumentException("{$value} could not be rounded.");
        }

        /** @var numeric-string $operand */
        $scaledInt = $shouldRoundUp ? bcadd($operand, '1', 0) : $operand;

        // Insert the decimal point at the scale position.
        if ($this->scale > 0) {
            $padded = str_pad($scaledInt, $this->scale + 1, '0', STR_PAD_LEFT);
            $result = substr($padded, 0, -$this->scale).'.'.substr($padded, -$this->scale);
        } else {
            $result = $scaledInt;
        }

        // Never emit "-0.0000": a value that rounds to zero is plain zero
        /** @var numeric-string $result */
        if (bccomp($result, '0', $this->scale) === 0) {
            return '0'.($this->scale > 0 ? '.'.str_repeat('0', $this->scale) : '');
        }

        return $isNegative ? '-'.$result : $result;
    }

    /**
     * Convert inputs bcmath cannot parse (e.g. "1.0E-8", "2.5E+3") into plain
     * decimal strings before rounding. PHP casts float attributes to exponent
     * notation, which would otherwise throw a ValueError inside bcmul/bcadd.
     */
    private function normalizeInput(string $value): string
    {
        $trimmed = trim($value);

        if (stripos($trimmed, 'e') === false) {
            return $trimmed;
        }

        $normalized = sprintf('%.'.($this->scale + 10).'F', (float) $trimmed);

        // Trim trailing zeros (and trailing dot) but keep at least the integer part
        $normalized = rtrim(rtrim($normalized, '0'), '.');

        return $normalized === '' || $normalized === '-' ? '0' : $normalized;
    }
}
