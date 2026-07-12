<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\TellerAllocation;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TellerAllocationMyActiveTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function teller_can_view_own_active_allocation_with_standard_envelope(): void
    {
        $branch = Branch::factory()->create();
        $teller = User::factory()->create([
            'role' => UserRole::Teller,
            'branch_id' => $branch->id,
        ]);
        $counter = Counter::factory()->create([
            'branch_id' => $branch->id,
        ]);

        $allocation = TellerAllocation::factory()->active()->create([
            'user_id' => $teller->id,
            'branch_id' => $branch->id,
            'counter_id' => $counter->id,
            'currency_code' => 'USD',
        ]);

        $response = $this->actingAs($teller, 'sanctum')
            ->getJson('/api/v1/allocations/my-active?currency_code=USD');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'currency_code',
                    'current_balance',
                ],
            ])
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Active allocation retrieved')
            ->assertJsonPath('data.id', $allocation->id);
    }

    #[Test]
    public function teller_sees_null_data_when_no_active_allocation_exists(): void
    {
        $branch = Branch::factory()->create();
        $teller = User::factory()->create([
            'role' => UserRole::Teller,
            'branch_id' => $branch->id,
        ]);

        $response = $this->actingAs($teller, 'sanctum')
            ->getJson('/api/v1/allocations/my-active?currency_code=USD');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'No active allocation found',
                'data' => null,
            ]);
    }

    #[Test]
    public function teller_sees_null_data_when_allocation_is_not_active(): void
    {
        $branch = Branch::factory()->create();
        $teller = User::factory()->create([
            'role' => UserRole::Teller,
            'branch_id' => $branch->id,
        ]);

        TellerAllocation::factory()->pending()->create([
            'user_id' => $teller->id,
            'branch_id' => $branch->id,
            'currency_code' => 'USD',
        ]);

        $response = $this->actingAs($teller, 'sanctum')
            ->getJson('/api/v1/allocations/my-active?currency_code=USD');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'No active allocation found',
                'data' => null,
            ]);
    }
}
