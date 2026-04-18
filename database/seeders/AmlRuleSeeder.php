<?php

namespace Database\Seeders;

use App\Enums\AmlRuleType;
use App\Models\AmlRule;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * AML Rule Seeder
 *
 * Seeds default BNM-appropriate AML rules for detecting suspicious transactions.
 * These rules align with Bank Negara Malaysia AML/CFT requirements.
 */
class AmlRuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminUser = User::where('role', 'admin')->first();
        $createdBy = $adminUser?->id;

        $rules = [
            // Velocity Rule - High transaction volume
            [
                'rule_code' => 'VEL-001',
                'rule_name' => 'High Velocity Alert',
                'description' => 'Detects when a customer conducts more than 10 transactions within 24 hours, which may indicate money laundering activity',
                'rule_type' => AmlRuleType::Velocity->value,
                'conditions' => [
                    'window_hours' => 24,
                    'max_transactions' => 10,
                    'cumulative_threshold' => null,
                ],
                'action' => 'flag',
                'risk_score' => 25,
                'is_active' => true,
                'created_by' => $createdBy,
            ],

            // Velocity Rule - High cumulative amount
            [
                'rule_code' => 'VEL-002',
                'rule_name' => 'High Cumulative Amount Alert',
                'description' => 'Detects when cumulative transactions exceed RM 50,000 in a 24-hour window',
                'rule_type' => AmlRuleType::Velocity->value,
                'conditions' => [
                    'window_hours' => 24,
                    'max_transactions' => 10,
                    'cumulative_threshold' => 50000,
                ],
                'action' => 'flag',
                'risk_score' => 30,
                'is_active' => true,
                'created_by' => $createdBy,
            ],

            // Structuring Rule - Multiple small transactions
            [
                'rule_code' => 'STR-001',
                'rule_name' => 'Structuring Detection',
                'description' => 'Detects potential structuring where multiple transactions in a single day sum to over RM 45,000, possibly indicating attempts to avoid reporting thresholds',
                'rule_type' => AmlRuleType::Structuring->value,
                'conditions' => [
                    'window_days' => 1,
                    'min_transaction_count' => 3,
                    'aggregate_threshold' => 45000,
                ],
                'action' => 'hold',
                'risk_score' => 40,
                'is_active' => true,
                'created_by' => $createdBy,
            ],

            // Amount Threshold - Large single transaction
            [
                'rule_code' => 'AMT-001',
                'rule_name' => 'Large Transaction Alert',
                'description' => 'Flags single transactions of RM 50,000 or more as per BNM Large Transaction Report requirements',
                'rule_type' => AmlRuleType::AmountThreshold->value,
                'conditions' => [
                    'min_amount' => 50000,
                    'currency' => 'MYR',
                ],
                'action' => 'flag',
                'risk_score' => 20,
                'is_active' => true,
                'created_by' => $createdBy,
            ],

            // Amount Threshold - Very large transaction
            [
                'rule_code' => 'AMT-002',
                'rule_name' => 'Very Large Transaction Hold',
                'description' => 'Holds transactions of RM 100,000 or more for enhanced due diligence review',
                'rule_type' => AmlRuleType::AmountThreshold->value,
                'conditions' => [
                    'min_amount' => 100000,
                    'currency' => 'MYR',
                ],
                'action' => 'hold',
                'risk_score' => 35,
                'is_active' => true,
                'created_by' => $createdBy,
            ],

            // Frequency Rule - Rapid transactions
            [
                'rule_code' => 'FREQ-001',
                'rule_name' => 'High Frequency Alert',
                'description' => 'Detects unusually high transaction frequency (more than 10 transactions in 1 hour) which may indicate automated money laundering',
                'rule_type' => AmlRuleType::Frequency->value,
                'conditions' => [
                    'window_hours' => 1,
                    'max_transactions' => 10,
                ],
                'action' => 'flag',
                'risk_score' => 25,
                'is_active' => true,
                'created_by' => $createdBy,
            ],

            // Frequency Rule - Very rapid transactions
            [
                'rule_code' => 'FREQ-002',
                'rule_name' => 'Rapid Fire Transactions',
                'description' => 'Detects more than 5 transactions within 15 minutes, indicating possible structuring or testing behavior',
                'rule_type' => AmlRuleType::Frequency->value,
                'conditions' => [
                    'window_hours' => 0.25, // 15 minutes
                    'max_transactions' => 5,
                ],
                'action' => 'hold',
                'risk_score' => 45,
                'is_active' => true,
                'created_by' => $createdBy,
            ],

            // Geographic Rule - FATF High-Risk Countries
            [
                'rule_code' => 'GEO-001',
                'rule_name' => 'FATF High-Risk Countries',
                'description' => 'Flags transactions involving customers from countries subject to FATF countermeasures or high-risk jurisdictions',
                'rule_type' => AmlRuleType::Geographic->value,
                'conditions' => [
                    'countries' => ['IR', 'KP', 'SY', 'MM', 'AF'], // Iran, North Korea, Syria, Myanmar, Afghanistan
                    'match_field' => 'customer_nationality',
                ],
                'action' => 'hold',
                'risk_score' => 50,
                'is_active' => true,
                'created_by' => $createdBy,
            ],

            // Geographic Rule - Additional monitored countries
            [
                'rule_code' => 'GEO-002',
                'rule_name' => 'Enhanced Monitoring Countries',
                'description' => 'Flags transactions from countries requiring enhanced monitoring due to AML/CFT concerns',
                'rule_type' => AmlRuleType::Geographic->value,
                'conditions' => [
                    'countries' => ['UA', 'VE', 'ZW', 'LY', 'YE'], // Ukraine, Venezuela, Zimbabwe, Libya, Yemen
                    'match_field' => 'customer_nationality',
                ],
                'action' => 'flag',
                'risk_score' => 30,
                'is_active' => true,
                'created_by' => $createdBy,
            ],

            // Combined Structuring Rule - Week-long
            [
                'rule_code' => 'STR-002',
                'rule_name' => 'Week-Long Structuring Detection',
                'description' => 'Detects potential structuring across a week-long period where multiple transactions sum to over RM 80,000',
                'rule_type' => AmlRuleType::Structuring->value,
                'conditions' => [
                    'window_days' => 7,
                    'min_transaction_count' => 5,
                    'aggregate_threshold' => 80000,
                ],
                'action' => 'hold',
                'risk_score' => 35,
                'is_active' => true,
                'created_by' => $createdBy,
            ],
        ];

        foreach ($rules as $ruleData) {
            AmlRule::updateOrCreate(
                ['rule_code' => $ruleData['rule_code']],
                $ruleData
            );
        }

        $this->command->info('Seeded '.count($rules).' AML rules');
    }
}
