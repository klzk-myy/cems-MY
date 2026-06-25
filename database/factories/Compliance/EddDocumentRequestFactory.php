<?php

namespace Database\Factories\Compliance;

use App\Models\Compliance\EddDocumentRequest;
use App\Models\EnhancedDiligenceRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EddDocumentRequest>
 */
class EddDocumentRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'edd_record_id' => EnhancedDiligenceRecord::factory(),
            'document_type' => $this->faker->randomElement(['passport', 'id_card', 'proof_of_address', 'bank_statement', 'tax_return']),
            'file_path' => 'edd/documents/'.$this->faker->unique()->uuid.'.pdf',
            'status' => $this->faker->randomElement(['Pending', 'Received', 'Verified', 'Rejected']),
            'rejection_reason' => $this->faker->optional()->sentence(),
            'uploaded_at' => $this->faker->dateTimeThisMonth(),
            'verified_at' => $this->faker->optional()->dateTimeThisMonth(),
            'verified_by' => User::factory(),
        ];
    }
}
