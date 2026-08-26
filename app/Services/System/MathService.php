<?php

namespace App\Services\System;

use App\Exceptions\Domain\MathValidationException;
use App\Services\Contracts\MathServiceInterface;

/**
 * Math Service
 *
 * Provides high-precision mathematical operations using BCMath extension.
 * Essential for financial calculations to prevent floating-point precision errors.
 *
 * All monetary amounts are handled as strings to maintain precision.
 *
 * DECISION: Default scale is set to 4 to match database decimal(18,4) storage
 * precision for monetary amounts (amount_local, amount_foreign, balance,
 * unrealized_pnl). This prevents silent rounding mismatches between internal
 * calculations and database storage. Exchange rates use explicit scale=6
 * where needed (decimal(18,6) in DB).
 */
class MathService implements MathServiceInterface
{
    /**
     * Decimal scale for BCMath operations.
     *
     * Set to 4 to match database decimal(18,4) precision for monetary amounts.
     * Using scale=6 while DB stores at scale=4 caused silent rounding issues.
     */
    protected int $scale = 4;

    /**
     * Create a new MathService instance.
     *
     * @param  int  $scale  Number of decimal places for calculations (default: 4)
     */
    public function __construct(int $scale = 4)
    {
        $this->scale = $scale;
    }

    /**
     * Add two numbers with high precision.
     *
     * @param  string  $a  First operand
     * @param  string  $b  Second operand
     * @return numeric-string Sum of a and b
     */
    public function add(string $a, string $b): string
    {
        return bcadd($this->assertNumeric($a), $this->assertNumeric($b), $this->scale);
    }

    /**
     * Subtract two numbers with high precision.
     *
     * @param  string  $a  First operand
     * @param  string  $b  Second operand
     * @return numeric-string Difference of a and b
     */
    public function subtract(string $a, string $b): string
    {
        return bcsub($this->assertNumeric($a), $this->assertNumeric($b), $this->scale);
    }

    /**
     * Negate a decimal amount.
     *
     * @param  string  $amount  Amount to negate
     * @return numeric-string Negated amount at the configured scale
     */
    public function negate(string $amount): string
    {
        return bcsub('0', $this->assertNumeric($amount), $this->scale);
    }

    /**
     * Multiply two numbers with high precision.
     *
     * @param  string  $a  First operand
     * @param  string  $b  Second operand
     * @param  int|null  $scale  Optional decimal scale override
     * @return numeric-string Product of a and b
     */
    public function multiply(string $a, string $b, ?int $scale = null): string
    {
        return bcmul($this->assertNumeric($a), $this->assertNumeric($b), $scale ?? $this->scale);
    }

    /**
     * Divide two numbers with high precision.
     *
     * @param  string  $a  Dividend
     * @param  string  $b  Divisor
     * @param  int|null  $scale  Optional decimal scale override
     * @return numeric-string Quotient of a and b
     *
     * @throws \InvalidArgumentException If divisor is zero
     */
    public function divide(string $a, string $b, ?int $scale = null): string
    {
        $dividend = $this->assertNumeric($a);
        $divisor = $this->assertNumeric($b);

        if (bccomp($divisor, '0', $scale ?? $this->scale) === 0) {
            throw new MathValidationException('Division by zero');
        }

        return bcdiv($dividend, $divisor, $scale ?? $this->scale);
    }

    /**
     * Divide two numbers, returning '0' when the divisor is zero.
     *
     * Ratios (e.g. financial ratios) must not throw when a denominator is
     * zero; this is the single shared implementation of that guard.
     *
     * @param  string  $a  Dividend
     * @param  string  $b  Divisor
     * @param  int|null  $precision  Optional precision override (default: scale)
     * @return numeric-string Quotient of a and b, or '0' when b is zero
     */
    public function safeDivide(string $a, string $b, ?int $precision = null): string
    {
        $dividend = $this->assertNumeric($a);
        $divisor = $this->assertNumeric($b);

        if (bccomp($divisor, '0', $this->scale) === 0) {
            return '0';
        }

        return bcdiv($dividend, $divisor, $precision ?? $this->scale);
    }

    /**
     * Compare two numbers.
     *
     * @param  string  $a  First operand
     * @param  string  $b  Second operand
     * @return int 0 if equal, 1 if a > b, -1 if a < b
     */
    public function compare(string $a, string $b): int
    {
        return bccomp($this->assertNumeric($a), $this->assertNumeric($b), $this->scale);
    }

    /**
     * Calculate weighted average cost for foreign currency inventory.
     *
     * Formula: (Old Balance × Old Avg Cost + Transaction Amount × Transaction Rate) / New Balance
     *
     * @param  string  $oldBalance  Current balance
     * @param  string  $oldAvgCost  Current average cost rate
     * @param  string  $transactionAmount  Amount being added
     * @param  string  $transactionRate  Rate of new transaction
     * @return numeric-string New weighted average cost
     */
    public function calculateAverageCost(
        string $oldBalance,
        string $oldAvgCost,
        string $transactionAmount,
        string $transactionRate
    ): string {
        $oldValue = $this->multiply($oldBalance, $oldAvgCost);
        $newValue = $this->multiply($transactionAmount, $transactionRate);
        $totalValue = $this->add($oldValue, $newValue);
        $newBalance = $this->add($oldBalance, $transactionAmount);

        return $this->divide($totalValue, $newBalance);
    }

    /**
     * Calculate revaluation profit/loss for foreign currency positions.
     *
     * Formula: Position Amount × (New Rate - Old Rate)
     *
     * @param  string  $positionAmount  Current position balance
     * @param  string  $oldRate  Previous valuation rate
     * @param  string  $newRate  Current market rate
     * @param  int|null  $precision  Optional precision override (default: scale)
     * @return numeric-string Revaluation P&L (positive = gain, negative = loss)
     */
    public function calculateRevaluationPnl(
        string $positionAmount,
        string $oldRate,
        string $newRate,
        ?int $precision = null
    ): string {
        $outputPrecision = $precision ?? $this->scale;

        // Compute the rate difference at a working scale high enough to keep
        // every digit the requested output precision needs; otherwise a diff
        // like 0.000002 would truncate to '0.0000' at scale 4 before the
        // multiply ever sees it.
        $workingScale = max($this->scale, $outputPrecision);
        $rateDiff = bcsub($this->assertNumeric($newRate), $this->assertNumeric($oldRate), $workingScale);

        return bcmul($this->assertNumeric($positionAmount), $this->assertNumeric($rateDiff), $outputPrecision);
    }

    /**
     * Calculate transaction amount in local currency.
     *
     * Formula: Foreign Amount × Exchange Rate
     *
     * @param  string  $foreignAmount  Amount in foreign currency
     * @param  string  $rate  Exchange rate
     * @return numeric-string Amount in local currency (MYR)
     */
    public function calculateTransactionAmount(
        string $foreignAmount,
        string $rate
    ): string {
        return $this->multiply($foreignAmount, $rate);
    }

    /**
     * Get the absolute value of a number.
     *
     * @param  string  $number  The number
     * @return numeric-string Absolute value
     */
    public function abs(string $number): string
    {
        $number = $this->assertNumeric($number);

        if (bccomp($number, '0', $this->scale) < 0) {
            return bcsub('0', $number, $this->scale);
        }

        return $number;
    }

    /**
     * Get the current scale.
     *
     * @return int Current scale value
     */
    public function getScale(): int
    {
        return $this->scale;
    }

    /**
     * Round a number to specified decimal places.
     *
     * @param  string  $number  The number to round
     * @param  int  $precision  Number of decimal places
     * @return numeric-string Rounded number
     */
    public function round(string $number, int $precision = 0): string
    {
        // BCMath half-up rounding without float casts.
        //
        // Algorithm:
        //   1. Multiply number by 10^precision (shift decimal right)
        //   2. Add 0.5 for positive numbers, subtract 0.5 for negatives (half-up)
        //   3. Truncate to integer (BCMath truncates toward zero at scale 0)
        //   4. Divide by 10^precision (shift decimal back)
        //
        // Example: round('123.4567', 2)
        //   multiplier = 100
        //   multiplied = 12345.67 + 0.5 → truncated to 12346 → / 100 = 123.46
        $multiplier = $this->assertNumeric(bcpow('10', (string) $precision, $this->scale));
        $number = $this->assertNumeric($number);

        // Use extra precision to preserve the rounding digit after multiplication
        $workingScale = max($this->scale, $precision + 2);
        $multiplied = bcmul($number, $multiplier, $workingScale);

        // Apply half-up: add 0.5 for positive, subtract 0.5 for negative
        if (bccomp($multiplied, '0', $workingScale) >= 0) {
            $adjusted = bcadd($multiplied, '0.5', $workingScale);
        } else {
            $adjusted = bcsub($multiplied, '0.5', $workingScale);
        }

        // Truncate to integer (BCMath truncates toward zero at scale 0)
        $truncated = bcadd($adjusted, '0', 0);

        return bcdiv($truncated, $multiplier, $precision);
    }

    /**
     * Validate that an operand is a numeric string before handing it to BCMath.
     *
     * @return numeric-string
     */
    private function assertNumeric(string $value): string
    {
        if (! is_numeric($value)) {
            throw new MathValidationException("Value '{$value}' is not a valid numeric string.");
        }

        return $value;
    }
}
