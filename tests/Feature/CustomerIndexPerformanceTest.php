<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\RiskScoreSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CustomerIndexPerformanceTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function customer_index_uses_limited_queries(): void
    {
        $user = User::factory()->create(['role' => UserRole::Manager]);
        Customer::factory()->count(20)->create();

        DB::enableQueryLog();
        $response = $this->actingAs($user)->get(route('customers.index'));
        $queryCount = count(DB::getQueryLog());

        $response->assertStatus(200);
        $this->assertLessThanOrEqual(8, $queryCount, "Expected <= 8 queries, got {$queryCount}");
    }

    #[Test]
    public function latest_risk_snapshot_returns_most_recent_snapshot(): void
    {
        $customer = Customer::factory()->create();
        RiskScoreSnapshot::factory()->create([
            'customer_id' => $customer->id,
            'snapshot_date' => now()->subDays(5),
            'overall_score' => 10,
        ]);
        $latest = RiskScoreSnapshot::factory()->create([
            'customer_id' => $customer->id,
            'snapshot_date' => now(),
            'overall_score' => 90,
        ]);

        $found = $customer->fresh()->latestRiskSnapshot;

        $this->assertNotNull($found);
        $this->assertEquals($latest->id, $found->id);
        $this->assertEquals(90, $found->overall_score);
    }
}
