<?php

namespace App\Services;

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
class MathService
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
     * @return string Sum of a and b
     */
    public function add(string $a, string $b): string
    {
        return bcadd($a, $b, $this->scale);
    }

    /**
     * Subtract two numbers with high precision.
     *
     * @param  string  $a  First operand
     * @param  string  $b  Second operand
     * @return string Difference of a and b
     */
    public function subtract(string $a, string $b): string
    {
        return bcsub($a, $b, $this->scale);
    }

    /**
     * Multiply two numbers with high precision.
     *
     * @param  string  $a  First operand
     * @param  string  $b  Second operand
     * @return string Product of a and b
     */
    public function multiply(string $a, string $b): string
    {
        return bcmul($a, $b, $this->scale);
    }

    /**
     * Divide two numbers with high precision.
     *
     * @param  string  $a  Dividend
     * @param  string  $b  Divisor
     * @return string Quotient of a and b
     *
     * @throws \InvalidArgumentException If divisor is zero
     */
    public function divide(string $a, string $b): string
    {
        if (bccomp($b, '0', $this->scale) === 0) {
            throw new \InvalidArgumentException('Division by zero');
        }

        return bcdiv($a, $b, $this->scale);
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
        return bccomp($a, $b, $this->scale);
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
     * @return string New weighted average cost
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
     * @return string Revaluation P&L (positive = gain, negative = loss)
     */
    public function calculateRevaluationPnl(
        string $positionAmount,
        string $oldRate,
        string $newRate,
        ?int $precision = null
    ): string {
        $rateDiff = $this->subtract($newRate, $oldRate);
        $precision = $precision ?? $this->scale;

        return $this->multiply($positionAmount, $rateDiff);
    }

    /**
     * Calculate transaction amount in local currency.
     *
     * Formula: Foreign Amount × Exchange Rate
     *
     * @param  string  $foreignAmount  Amount in foreign currency
     * @param  string  $rate  Exchange rate
     * @return string Amount in local currency (MYR)
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
     * @return string Absolute value
     */
    public function abs(string $number): string
    {
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
     * @return string Rounded number
     */
    public function round(string $number, int $precision = 0): string
    {
        $multiplier = bcpow('10', (string) $precision, $this->scale);
        $multiplied = bcmul($number, $multiplier, $this->scale);
        $rounded = round((float) $multiplied);

        return bcdiv((string) $rounded, $multiplier, $precision);
    }
}
