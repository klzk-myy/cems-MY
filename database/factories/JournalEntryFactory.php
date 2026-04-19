<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\JournalEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JournalEntry>
 */
class JournalEntryFactory extends Factory
{
    protected $model = JournalEntry::class;

    public function definition(): array
    {
        return [
            'period_id' => null,
            'entry_date' => now()->toDateString(),
            'description' => fake()->sentence(),
            'status' => 'Draft',
            'reference_type' => 'Manual',
            'reference_id' => null,
            'posted_by' => null,
            'created_by' => null,
            'branch_id' => null,
        ];
    }

    public function forBranch(Branch $branch): static
    {
        return $this->state(fn (array $attributes) => [
            'branch_id' => $branch->id,
        ]);
    }
}
