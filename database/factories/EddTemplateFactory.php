<?php

namespace Database\Factories;

use App\Models\EddTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EddTemplate>
 */
class EddTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'type' => $this->faker->randomElement(['pep', 'high_risk_country', 'unusual_pattern', 'sanction_match', 'large_transaction', 'high_risk_industry']),
            'description' => $this->faker->sentence(),
            'questions' => [
                'sections' => [
                    [
                        'title' => 'Personal Information',
                        'questions' => [
                            ['question' => 'Full name', 'type' => 'text', 'required' => true],
                            ['question' => 'Date of birth', 'type' => 'date', 'required' => true],
                        ],
                    ],
                ],
            ],
            'version' => 1,
            'is_active' => $this->faker->boolean(80),
            'created_by' => User::factory(),
        ];
    }
}
