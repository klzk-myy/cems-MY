<?php

namespace App\Services\Contracts;

interface MathServiceInterface
{
    public function add(string $a, string $b): string;

    public function subtract(string $a, string $b): string;

    public function multiply(string $a, string $b): string;

    public function divide(string $a, string $b): string;

    public function compare(string $a, string $b): int;

    public function calculateAverageCost(
        string $oldBalance,
        string $oldAvgCost,
        string $transactionAmount,
        string $transactionRate
    ): string;

    public function calculateRevaluationPnl(
        string $positionAmount,
        string $oldRate,
        string $newRate,
        ?int $precision = null
    ): string;

    public function calculateTransactionAmount(
        string $foreignAmount,
        string $rate
    ): string;

    public function abs(string $number): string;

    public function getScale(): int;

    public function round(string $number, int $precision = 0): string;
}
