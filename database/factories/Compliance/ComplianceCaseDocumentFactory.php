<?php

namespace Database\Factories\Compliance;

use App\Models\Compliance\ComplianceCase;
use App\Models\Compliance\ComplianceCaseDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ComplianceCaseDocument>
 */
class ComplianceCaseDocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        static $caseIdCounter = 0;

        return [
            'case_id' => ComplianceCase::factory(),
            'file_name' => 'document_'.++$caseIdCounter.'.pdf',
            'file_path' => 'compliance/documents/'.$this->faker->unique()->uuid.'.pdf',
            'file_type' => $this->faker->mimeType('pdf'),
            'uploaded_by' => User::factory(),
            'uploaded_at' => $this->faker->dateTimeThisMonth(),
            'verified_at' => $this->faker->optional()->dateTimeThisMonth(),
            'verified_by' => User::factory(),
        ];
    }
}
