<?php

namespace App\Services\Risk;

use App\Models\Customer;
use App\Models\HighRiskCountry;
use App\Models\Transaction;

class GeographicRiskService
{
    /**
     * Calculate geographic risk score for a customer.
     *
     * Considers:
     * - Customer nationality (30 points if high-risk)
     * - Transaction counterparty countries (15 points per unique high-risk country in 90 days)
     *
     * @return int Risk score (0-40)
     */
    public function calculateScore(Customer $customer): int
    {
        $score = 0;

        $highRiskCountries = HighRiskCountry::pluck('country_code')->toArray();

        if ($this->isHighRiskCountry($customer->nationality, $highRiskCountries)) {
            $score += 30;
        }

        $recentCountries = $customer->transactions()
            ->where('created_at', '>=', now()->subDays(90))
            ->whereNotNull('counterparty_country')
            ->pluck('counterparty_country')
            ->filter()
            ->unique();

        foreach ($recentCountries as $country) {
            if (in_array($country, $highRiskCountries, true)) {
                $score += 15;
            }
        }

        return min($score, 40);
    }

    /**
     * Check if a country is high-risk.
     *
     * @param  string|null  $countryCode  ISO 3-letter country code
     * @param  array  $highRiskCountries  List of high-risk country codes
     */
    public function isHighRiskCountry(?string $countryCode, ?array $highRiskCountries = null): bool
    {
        if (! $countryCode) {
            return false;
        }

        if ($highRiskCountries === null) {
            $highRiskCountries = HighRiskCountry::pluck('country_code')->toArray();
        }

        return in_array($countryCode, $highRiskCountries, true);
    }

    /**
     * Get geographic risk tier for a customer.
     *
     * @return string 'low', 'medium', or 'high'
     */
    public function getRiskTier(Customer $customer): string
    {
        $score = $this->calculateScore($customer);

        if ($score >= 30) {
            return 'high';
        }

        if ($score >= 15) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Get all high-risk countries.
     *
     * @return array<string> Array of ISO 3-letter country codes
     */
    public function getHighRiskCountries(): array
    {
        return HighRiskCountry::pluck('country_code')->toArray();
    }
}
