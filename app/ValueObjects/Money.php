<?php

namespace App\ValueObjects;

use App\Services\MathService;
use InvalidArgumentException;

/**
 * Money Value Object
 *
 * Immutable value object representing monetary amounts with BCMath precision.
 * Ensures all monetary calculations are type-safe and precise.
 */
final class Money
{
    /**
     * The amount as a string for BCMath precision.
     */
    public readonly string $amount;

    /**
     * The currency code (ISO 4217).
     */
    public readonly string $currency;

    /**
     * Math service for calculations.
     */
    private static ?MathService $mathService = null;

    /**
     * Create a new Money instance.
     *
     * @param  string|int|float  $amount  The amount (will be converted to string)
     * @param  string  $currency  The currency code
     *
     * @throws InvalidArgumentException If amount is negative
     */
    public function __construct(string|int|float $amount, string $currency)
    {
        $this->amount = (string) $amount;
        $this->currency = $currency;

        if (self::getMathService()->compare($this->amount, '0') < 0) {
            throw new InvalidArgumentException("Money amount cannot be negative: {$this->amount}");
        }
    }

    /**
     * Get or create MathService instance.
     */
    private static function getMathService(): MathService
    {
        if (self::$mathService === null) {
            self::$mathService = app(MathService::class);
        }

        return self::$mathService;
    }

    /**
     * Create Money from string.
     */
    public static function fromString(string $amount, string $currency): self
    {
        return new self($amount, $currency);
    }

    /**
     * Create Money from float (with explicit acknowledgement of potential precision issues).
     */
    public static function fromFloat(float $amount, string $currency): self
    {
        // Format to avoid scientific notation
        $formatted = number_format($amount, 10, '.', '');
        // Trim trailing zeros
        $trimmed = rtrim(rtrim($formatted, '0'), '.');

        return new self($trimmed, $currency);
    }

    /**
     * Create Money from integer (cents).
     */
    public static function fromCents(int $cents, string $currency, int $decimalPlaces = 2): self
    {
        $amount = bcdiv((string) $cents, bcpow('10', (string) $decimalPlaces, 10), 10);

        return new self($amount, $currency);
    }

    /**
     * Zero money.
     */
    public static function zero(string $currency): self
    {
        return new self('0', $currency);
    }

    /**
     * Add another Money amount.
     *
     * @param  Money  $other  Must be same currency
     *
     * @throws InvalidArgumentException If currencies don't match
     */
    public function add(Money $other): self
    {
        $this->assertSameCurrency($other);
        $sum = self::getMathService()->add($this->amount, $other->amount);

        return new self($sum, $this->currency);
    }

    /**
     * Subtract another Money amount.
     *
     * @param  Money  $other  Must be same currency
     *
     * @throws InvalidArgumentException If result would be negative
     */
    public function subtract(Money $other): self
    {
        $this->assertSameCurrency($other);
        if ($this->lessThan($other)) {
            throw new InvalidArgumentException(
                "Cannot subtract {$other->amount} from {$this->amount} - result would be negative"
            );
        }
        $difference = self::getMathService()->subtract($this->amount, $other->amount);

        return new self($difference, $this->currency);
    }

    /**
     * Multiply by a factor.
     */
    public function multiply(string $factor): self
    {
        $product = self::getMathService()->multiply($this->amount, $factor);

        return new self($product, $this->currency);
    }

    /**
     * Convert to another currency at given rate.
     */
    public function convertTo(string $targetCurrency, string $rate): self
    {
        $converted = self::getMathService()->multiply($this->amount, $rate);

        return new self($converted, $targetCurrency);
    }

    /**
     * Divide by a divisor.
     */
    public function divide(string $divisor): self
    {
        if (self::getMathService()->compare($divisor, '0') === 0) {
            throw new InvalidArgumentException('Cannot divide by zero');
        }
        $quotient = self::getMathService()->divide($this->amount, $divisor);

        return new self($quotient, $this->currency);
    }

    /**
     * Compare with another Money amount.
     *
     * @return int -1 if less than, 0 if equal, 1 if greater than
     */
    public function compare(Money $other): int
    {
        $this->assertSameCurrency($other);

        return self::getMathService()->compare($this->amount, $other->amount);
    }

    /**
     * Check if equal to another Money amount.
     */
    public function equals(Money $other): bool
    {
        return $this->compare($other) === 0;
    }

    /**
     * Check if greater than another Money amount.
     */
    public function greaterThan(Money $other): bool
    {
        return $this->compare($other) > 0;
    }

    /**
     * Check if greater than or equal to another Money amount.
     */
    public function greaterThanOrEqual(Money $other): bool
    {
        return $this->compare($other) >= 0;
    }

    /**
     * Check if less than another Money amount.
     */
    public function lessThan(Money $other): bool
    {
        return $this->compare($other) < 0;
    }

    /**
     * Check if less than or equal to another Money amount.
     */
    public function lessThanOrEqual(Money $other): bool
    {
        return $this->compare($other) <= 0;
    }

    /**
     * Check if zero.
     */
    public function isZero(): bool
    {
        return self::getMathService()->compare($this->amount, '0') === 0;
    }

    /**
     * Check if positive.
     */
    public function isPositive(): bool
    {
        return self::getMathService()->compare($this->amount, '0') > 0;
    }

    /**
     * Format for display.
     *
     * @param  int  $decimalPlaces  Number of decimal places
     * @param  bool  $symbol  Whether to include currency symbol
     */
    public function format(int $decimalPlaces = 2, bool $symbol = true): string
    {
        $formatted = number_format((float) $this->amount, $decimalPlaces);

        return $symbol ? "{$this->currency} {$formatted}" : $formatted;
    }

    /**
     * Get absolute value.
     */
    public function abs(): self
    {
        $abs = self::getMathService()->abs($this->amount);

        return new self($abs, $this->currency);
    }

    /**
     * Round to specified decimal places.
     */
    public function round(int $precision = 2): self
    {
        $rounded = self::getMathService()->round($this->amount, $precision);

        return new self($rounded, $this->currency);
    }

    /**
     * Assert that currencies match.
     *
     * @throws InvalidArgumentException If currencies don't match
     */
    private function assertSameCurrency(Money $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                "Cannot operate on different currencies: {$this->currency} and {$other->currency}"
            );
        }
    }

    /**
     * Get string representation (for logging/debugging).
     */
    public function __toString(): string
    {
        return $this->format();
    }

    /**
     * Convert to array for serialization.
     */
    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency,
        ];
    }
}
