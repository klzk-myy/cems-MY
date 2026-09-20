<?php

namespace Database\Seeders;

use App\Enums\RiskRating;
use App\Models\Customer;
use App\Models\RiskScoreSnapshot;
use Illuminate\Database\Seeder;

/**
 * RiskScoreSnapshotHistorySeeder — backfills realistic risk snapshot history
 * for staging/demo environments so the risk dashboard trends and rescreening
 * views have data to render. Repeatable and resumable: only missing
 * (customer_id, snapshot_date) rows are inserted, so re-runs complete any
 * partially-seeded customers and no-op once history exists.
 *
 * Run on demand: php artisan db:seed --class=RiskScoreSnapshotHistorySeeder
 */
class RiskScoreSnapshotHistorySeeder extends Seeder
{
    private const SNAPSHOT_COUNT = 6;

    private const INTERVAL_DAYS = 30;

    public function run(): void
    {
        $seeded = 0;

        Customer::query()->chunkById(500, function ($customers) use (&$seeded): void {
            /** @var array<int, list<string>> $existing */
            $existing = [];

            // withTrashed: a soft-deleted snapshot must still block insertion,
            // otherwise the re-inserted row duplicates the trashed
            // (customer_id, snapshot_date) pair.
            foreach (RiskScoreSnapshot::query()
                ->withTrashed()
                ->whereIn('customer_id', $customers->pluck('id'))
                ->get(['customer_id', 'snapshot_date']) as $snapshot) {
                $existing[$snapshot->customer_id][] = $snapshot->snapshot_date->toDateString();
            }

            $rows = [];

            foreach ($customers as $customer) {
                $rows = array_merge($rows, $this->historyRowsFor(
                    $customer,
                    $existing[$customer->id] ?? []
                ));
            }

            if ($rows !== []) {
                RiskScoreSnapshot::query()->insert($rows);
            }

            $seeded += count($rows);
        });

        $this->command->info("RiskScoreSnapshotHistorySeeder: {$seeded} snapshots created.");
    }

    /**
     * @param  list<string>  $existingDates
     * @return list<array<string, mixed>>
     */
    private function historyRowsFor(Customer $customer, array $existingDates): array
    {
        $score = fake()->numberBetween(15, 65);
        $previousScore = null;
        $previousRating = null;
        $recentScores = [];
        $rows = [];
        $now = now();

        for ($i = self::SNAPSHOT_COUNT; $i >= 1; $i--) {
            $snapshotDate = today()->subDays($i * self::INTERVAL_DAYS);
            $score = max(0, min(100, $score + fake()->numberBetween(-12, 12)));
            $rating = $this->ratingForScore($score);

            $recentScores[] = ['overall_score' => $score];

            if (! in_array($snapshotDate->toDateString(), $existingDates, true)) {
                $rows[] = [
                    'customer_id' => $customer->id,
                    'snapshot_date' => $snapshotDate->toDateString(),
                    'overall_score' => $score,
                    'overall_rating_label' => $rating->label(),
                    'previous_score' => $previousScore,
                    'previous_rating' => $previousRating?->value,
                    'velocity_score' => fake()->numberBetween(0, min(40, $score)),
                    'structuring_score' => fake()->numberBetween(0, min(30, $score)),
                    'geographic_score' => fake()->numberBetween(0, min(30, $score)),
                    'amount_score' => fake()->numberBetween(0, min(30, $score)),
                    'trend' => RiskScoreSnapshot::calculateTrend($recentScores)->value,
                    'factors' => json_encode($this->factorsFor($score)),
                    'next_screening_date' => $snapshotDate->copy()
                        ->addDays($this->rescreeningDays($score))
                        ->toDateString(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            $previousScore = $score;
            $previousRating = $rating;
        }

        return $rows;
    }

    /**
     * Mirrors CustomerRiskScoringService::ratingForScore thresholds.
     */
    private function ratingForScore(int $score): RiskRating
    {
        return match (true) {
            $score >= 80 => RiskRating::Critical,
            $score >= 60 => RiskRating::High,
            $score >= 30 => RiskRating::Medium,
            default => RiskRating::Low,
        };
    }

    /**
     * Mirrors CustomerRiskScoringService::calculateNextScreeningDate cadence.
     */
    private function rescreeningDays(int $score): int
    {
        return match (true) {
            $score >= 80 => 30,
            $score >= 60 => 60,
            $score >= 30 => 90,
            default => 180,
        };
    }

    /**
     * @return list<string>
     */
    private function factorsFor(int $score): array
    {
        $factors = [];

        if ($score >= 60) {
            $factors[] = 'Elevated transaction velocity';
        }

        if ($score >= 30) {
            $factors[] = 'Moderate exposure';
        }

        return $factors;
    }
}
