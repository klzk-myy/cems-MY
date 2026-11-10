<?php

namespace App\Support;

use App\Services\System\MathService;

/**
 * BCMath Helper
 *
 * Static convenience facade over {@see MathService}. All arithmetic is
 * delegated to MathService so the application has a single implementation
 * of high-precision BCMath operations (no duplicated logic).
 *
 * All methods accept numeric strings and return boolean results.
 *
 * DECISION: Default scale is set to 4 to match database decimal(18,4) storage
 * precision for monetary amounts. This aligns with MathService and prevents
 * silent rounding mismatches between internal calculations and DB storage.
 */
class BcmathHelper
{
    /**
     * Default scale for operations.
     *
     * Set to 4 to match database decimal(18,4) precision for monetary amounts.
     */
    protected static int $scale = 4;

    /**
     * Set the scale for BCMath operations.
     *
     * @param  int  $scale  Number of decimal places
     */
    public static function setScale(int $scale): void
    {
        self::$scale = $scale;
    }

    /**
     * Get the current scale.
     *
     * @return int Current scale value
     */
    public static function getScale(): int
    {
        return self::$scale;
    }

    /**
     * Greater than comparison.
     *
     * @param  string  $a  First operand
     * @param  string  $b  Second operand
     * @param  int|null  $scale  Decimal places (uses default if null)
     * @return bool True if a > b
     */
    public static function gt(string $a, string $b, ?int $scale = null): bool
    {
        return self::compare($a, $b, $scale) > 0;
    }

    /**
     * Greater than or equal comparison.
     *
     * @param  string  $a  First operand
     * @param  string  $b  Second operand
     * @param  int|null  $scale  Decimal places (uses default if null)
     * @return bool True if a >= b
     */
    public static function gte(string $a, string $b, ?int $scale = null): bool
    {
        return self::compare($a, $b, $scale) >= 0;
    }

    /**
     * Less than comparison.
     *
     * @param  string  $a  First operand
     * @param  string  $b  Second operand
     * @param  int|null  $scale  Decimal places (uses default if null)
     * @return bool True if a < b
     */
    public static function lt(string $a, string $b, ?int $scale = null): bool
    {
        return self::compare($a, $b, $scale) < 0;
    }

    /**
     * Less than or equal comparison.
     *
     * @param  string  $a  First operand
     * @param  string  $b  Second operand
     * @param  int|null  $scale  Decimal places (uses default if null)
     * @return bool True if a <= b
     */
    public static function lte(string $a, string $b, ?int $scale = null): bool
    {
        return self::compare($a, $b, $scale) <= 0;
    }

    /**
     * Equal comparison.
     *
     * @param  string  $a  First operand
     * @param  string  $b  Second operand
     * @param  int|null  $scale  Decimal places (uses default if null)
     * @return bool True if a == b
     */
    public static function eq(string $a, string $b, ?int $scale = null): bool
    {
        return self::compare($a, $b, $scale) === 0;
    }

    /**
     * Check if value is zero.
     *
     * @param  string  $value  Value to check
     * @param  int|null  $scale  Decimal places (uses default if null)
     * @return bool True if value == 0
     */
    public static function isZero(string $value, ?int $scale = null): bool
    {
        return self::eq($value, '0', $scale);
    }

    /**
     * Check if value is not zero.
     *
     * @param  string  $value  Value to check
     * @param  int|null  $scale  Decimal places (uses default if null)
     * @return bool True if value != 0
     */
    public static function isNotZero(string $value, ?int $scale = null): bool
    {
        return ! self::isZero($value, $scale);
    }

    /**
     * Get the maximum of two values.
     *
     * @param  string  $a  First operand
     * @param  string  $b  Second operand
     * @param  int|null  $scale  Decimal places (uses default if null)
     * @return string The larger value
     */
    public static function max(string $a, string $b, ?int $scale = null): string
    {
        return self::gte($a, $b, $scale) ? $a : $b;
    }

    /**
     * Get the minimum of two values.
     *
     * @param  string  $a  First operand
     * @param  string  $b  Second operand
     * @param  int|null  $scale  Decimal places (uses default if null)
     * @return string The smaller value
     */
    public static function min(string $a, string $b, ?int $scale = null): string
    {
        return self::lte($a, $b, $scale) ? $a : $b;
    }

    /**
     * Compare two values and return -1, 0, or 1.
     *
     * @param  string  $a  First operand
     * @param  string  $b  Second operand
     * @param  int|null  $scale  Decimal places (uses default if null)
     * @return int -1 if a < b, 0 if a == b, 1 if a > b
     */
    public static function compare(string $a, string $b, ?int $scale = null): int
    {
        return self::math($scale)->compare($a, $b);
    }

    /**
     * Add two numbers with high precision.
     *
     * @param  string  $a  First operand
     * @param  string  $b  Second operand
     * @param  int|null  $scale  Decimal places (uses default if null)
     * @return string Sum of a and b
     */
    public static function add(string $a, string $b, ?int $scale = null): string
    {
        return self::math($scale)->add($a, $b);
    }

    /**
     * Subtract two numbers with high precision.
     *
     * @param  string  $a  First operand
     * @param  string  $b  Second operand
     * @param  int|null  $scale  Decimal places (uses default if null)
     * @return string Difference of a and b
     */
    public static function subtract(string $a, string $b, ?int $scale = null): string
    {
        return self::math($scale)->subtract($a, $b);
    }

    /**
     * Multiply two numbers with high precision.
     *
     * @param  string  $a  First operand
     * @param  string  $b  Second operand
     * @param  int|null  $scale  Decimal places (uses default if null)
     * @return string Product of a and b
     */
    public static function multiply(string $a, string $b, ?int $scale = null): string
    {
        return self::math($scale)->multiply($a, $b);
    }

    /**
     * Divide two numbers with high precision.
     *
     * @param  string  $a  Dividend
     * @param  string  $b  Divisor
     * @param  int|null  $scale  Decimal places (uses default if null)
     * @return string Quotient of a and b
     *
     * @throws \InvalidArgumentException If divisor is zero
     */
    public static function divide(string $a, string $b, ?int $scale = null): string
    {
        return self::math($scale)->divide($a, $b);
    }

    /**
     * Get the absolute value of a number.
     *
     * @param  string  $value  Value to get absolute value of
     * @param  int|null  $scale  Decimal places (uses default if null)
     * @return string Absolute value
     */
    public static function abs(string $value, ?int $scale = null): string
    {
        return self::math($scale)->abs($value);
    }

    /**
     * Build a MathService instance configured with the effective scale.
     */
    protected static function math(?int $scale): MathService
    {
        return new MathService($scale ?? self::$scale);
    }
}
