<?php

namespace App\Services\Contracts;

interface ReportingServiceInterface
{
    public function generateMSB2(string $date): string;

    public function generateMSB2Data(string $date): array;

    public function generateCurrencyPositionReport(): array;

    public function generateUnrealizedPnLReport(): array;

    public function generateFormLMCA(string $month): array;

    public function generateFormLMCACsv(string $month): string;

    public function generateQuarterlyLargeValueReport(string $quarter): array;

    public function generateQuarterlyLargeValueCsv(string $quarter): string;

    public function generatePositionLimitReport(): array;

    public function generatePositionLimitCsv(): string;
}
