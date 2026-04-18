<?php

namespace Database\Seeders;

use App\Models\HighRiskCountry;
use Illuminate\Database\Seeder;

class HighRiskCountrySeeder extends Seeder
{
    public function run(): void
    {
        $countries = [
            // FATF High Risk Jurisdictions
            ['country_code' => 'AF', 'country_name' => 'Afghanistan', 'risk_level' => 'High', 'source' => 'FATF'],
            ['country_code' => 'KP', 'country_name' => 'Democratic People\'s Republic of Korea (North Korea)', 'risk_level' => 'High', 'source' => 'FATF'],
            ['country_code' => 'IR', 'country_name' => 'Iran', 'risk_level' => 'High', 'source' => 'FATF'],
            ['country_code' => 'MM', 'country_name' => 'Myanmar', 'risk_level' => 'High', 'source' => 'FATF'],

            // FATF Grey List
            ['country_code' => 'BY', 'country_name' => 'Belarus', 'risk_level' => 'Grey', 'source' => 'FATF'],
            ['country_code' => 'BI', 'country_name' => 'Burundi', 'risk_level' => 'Grey', 'source' => 'FATF'],
            ['country_code' => 'CF', 'country_name' => 'Central African Republic', 'risk_level' => 'Grey', 'source' => 'FATF'],
            ['country_code' => 'CD', 'country_name' => 'Congo, Democratic Republic of', 'risk_level' => 'Grey', 'source' => 'FATF'],
            ['country_code' => 'IQ', 'country_name' => 'Iraq', 'risk_level' => 'Grey', 'source' => 'FATF'],
            ['country_code' => 'LB', 'country_name' => 'Lebanon', 'risk_level' => 'Grey', 'source' => 'FATF'],
            ['country_code' => 'LY', 'country_name' => 'Libya', 'risk_level' => 'Grey', 'source' => 'FATF'],
            ['country_code' => 'ML', 'country_name' => 'Mali', 'risk_level' => 'Grey', 'source' => 'FATF'],
            ['country_code' => 'NI', 'country_name' => 'Nicaragua', 'risk_level' => 'Grey', 'source' => 'FATF'],
            ['country_code' => 'RU', 'country_name' => 'Russia', 'risk_level' => 'Grey', 'source' => 'FATF'],
            ['country_code' => 'SO', 'country_name' => 'Somalia', 'risk_level' => 'Grey', 'source' => 'FATF'],
            ['country_code' => 'SS', 'country_name' => 'South Sudan', 'risk_level' => 'Grey', 'source' => 'FATF'],
            ['country_code' => 'SD', 'country_name' => 'Sudan', 'risk_level' => 'Grey', 'source' => 'FATF'],
            ['country_code' => 'SY', 'country_name' => 'Syria', 'risk_level' => 'Grey', 'source' => 'FATF'],
            ['country_code' => 'VE', 'country_name' => 'Venezuela', 'risk_level' => 'Grey', 'source' => 'FATF'],
            ['country_code' => 'YE', 'country_name' => 'Yemen', 'risk_level' => 'Grey', 'source' => 'FATF'],
            ['country_code' => 'ZW', 'country_name' => 'Zimbabwe', 'risk_level' => 'Grey', 'source' => 'FATF'],
            ['country_code' => 'TX', 'country_name' => 'Turkmenistan', 'risk_level' => 'Grey', 'source' => 'FATF'],
        ];

        foreach ($countries as $country) {
            HighRiskCountry::firstOrCreate(
                ['country_code' => $country['country_code']],
                array_merge($country, ['list_date' => now()->toDateString()])
            );
        }

        $this->command->info('Seeded '.count($countries).' high-risk countries');
    }
}
