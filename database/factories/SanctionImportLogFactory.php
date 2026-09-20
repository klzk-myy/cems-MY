<?php

namespace Database\Factories;

use App\Enums\ImportStatus;
use App\Enums\ImportTrigger;
use App\Models\SanctionImportLog;
use App\Models\SanctionList;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SanctionImportLog>
 */
class SanctionImportLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'list_id' => SanctionList::factory(),
            'imported_at' => $this->faker->dateTimeThisMonth(),
            'records_added' => $this->faker->numberBetween(0, 1000),
            'records_updated' => $this->faker->numberBetween(0, 500),
            'records_deactivated' => $this->faker->numberBetween(0, 200),
            'status' => $this->faker->randomElement(ImportStatus::cases()),
            'error_message' => $this->faker->optional()->sentence(),
            'triggered_by' => $this->faker->randomElement(ImportTrigger::cases()),
            'user_id' => User::factory(),
        ];
    }
}
