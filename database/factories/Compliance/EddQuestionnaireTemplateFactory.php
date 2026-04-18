<?php

namespace Database\Factories\Compliance;

use App\Models\Compliance\EddQuestionnaireTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

class EddQuestionnaireTemplateFactory extends Factory
{
    protected $model = EddQuestionnaireTemplate::class;

    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Standard EDD', 'High Risk EDD', 'PEP EDD', 'Business EDD']),
            'version' => '1.0',
            'is_active' => true,
            'questions' => [
                [
                    'id' => 'q1',
                    'section' => 'source_of_funds',
                    'question' => 'What is your primary source of funds for this transaction?',
                    'type' => 'text',
                    'required' => true,
                ],
                [
                    'id' => 'q2',
                    'section' => 'source_of_funds',
                    'question' => 'Can you provide documentation to verify the source of funds?',
                    'type' => 'select',
                    'options' => ['Yes', 'No', 'Partially'],
                    'required' => true,
                ],
                [
                    'id' => 'q3',
                    'section' => 'purpose',
                    'question' => 'What is the purpose of this transaction?',
                    'type' => 'text',
                    'required' => true,
                ],
            ],
        ];
    }

    public function inactive(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
