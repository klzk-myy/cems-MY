<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerDocumentFactory extends Factory
{
    protected $model = CustomerDocument::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'document_type' => 'MyKad',
            'file_path' => '/path/to/document.jpg',
            'file_hash' => fake()->sha1(),
            'file_size' => fake()->numberBetween(1000, 50000),
            'encrypted' => false,
            'uploaded_by' => User::factory(),
            'verified_by' => null,
            'verified_at' => null,
            'expiry_date' => now()->addYear(),
        ];
    }
}
