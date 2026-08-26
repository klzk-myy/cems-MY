<?php

namespace Database\Factories;

use App\Models\AdverseMediaEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdverseMediaEntry>
 */
class AdverseMediaEntryFactory extends Factory
{
    protected $model = AdverseMediaEntry::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->name();
        $source = $this->faker->randomElement(['manual', 'news_feed', 'compliance_watchlist']);

        return [
            'name' => $name,
            'normalized_name' => mb_strtolower(trim($name)),
            'alias' => null,
            'article_title' => $this->faker->sentence(),
            'source' => $source,
            'url' => $this->faker->optional()->url(),
            'snippet' => $this->faker->optional()->paragraph(),
            'published_at' => $this->faker->optional()->date(),
            'severity' => $this->faker->randomElement(AdverseMediaEntry::SEVERITIES),
            'is_active' => true,
            'record_hash' => AdverseMediaEntry::buildRecordHash(
                $name.'-'.$this->faker->unique()->uuid,
                $source,
                null
            ),
        ];
    }
}
