<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\RiskScoreSnapshot;
use Database\Seeders\RiskScoreSnapshotHistorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RiskScoreSnapshotHistorySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_six_snapshots_per_customer(): void
    {
        Customer::factory()->count(2)->create();

        $this->seed(RiskScoreSnapshotHistorySeeder::class);

        $this->assertSame(12, RiskScoreSnapshot::count());

        foreach (Customer::all() as $customer) {
            $this->assertSame(6, $customer->riskScoreSnapshots()->count());
        }
    }

    public function test_seeder_is_idempotent_and_resumable(): void
    {
        $customer = Customer::factory()->create();

        $this->seed(RiskScoreSnapshotHistorySeeder::class);
        $this->assertSame(6, RiskScoreSnapshot::count());

        // Physically-missing rows (e.g. an interrupted run) get refilled.
        $customer->riskScoreSnapshots()->oldest('snapshot_date')->limit(2)->get()
            ->each->forceDelete();
        $this->seed(RiskScoreSnapshotHistorySeeder::class);
        $this->assertSame(6, RiskScoreSnapshot::count());

        // A complete history is a no-op.
        $this->seed(RiskScoreSnapshotHistorySeeder::class);
        $this->assertSame(6, RiskScoreSnapshot::count());
    }

    public function test_seeder_never_duplicates_a_soft_deleted_snapshot(): void
    {
        $customer = Customer::factory()->create();

        $this->seed(RiskScoreSnapshotHistorySeeder::class);
        $customer->riskScoreSnapshots()->oldest('snapshot_date')->first()->delete();

        $this->seed(RiskScoreSnapshotHistorySeeder::class);

        $dupes = DB::table('risk_score_snapshots')
            ->selectRaw('customer_id, snapshot_date, COUNT(*) as c')
            ->groupBy('customer_id', 'snapshot_date')
            ->having('c', '>', 1)
            ->count();

        $this->assertSame(0, $dupes);
        $this->assertSame(5, $customer->riskScoreSnapshots()->count());
    }

    public function test_seeded_snapshots_carry_derived_fields(): void
    {
        $customer = Customer::factory()->create();

        $this->seed(RiskScoreSnapshotHistorySeeder::class);

        $snapshots = $customer->riskScoreSnapshots()
            ->orderBy('snapshot_date')
            ->get();

        // Every snapshot past the first chains previous_score/previous_rating.
        $previous = null;
        foreach ($snapshots as $snapshot) {
            $this->assertNotNull($snapshot->overall_rating_label);
            $this->assertNotNull($snapshot->next_screening_date);

            if ($previous !== null) {
                $this->assertSame($previous->overall_score, $snapshot->previous_score);
            }

            $previous = $snapshot;
        }
    }
}
