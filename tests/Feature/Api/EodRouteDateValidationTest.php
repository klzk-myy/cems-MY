<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regression guard: Api\V1 EOD routes carry `{date}` in the path. Form
 * requests only validate body/query by default; ApiFormRequest merges the
 * route param so a malformed path date is a 422, never a silent pass.
 */
class EodRouteDateValidationTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function malformed_route_date_is_rejected(): void
    {
        $branch = Branch::factory()->create();
        $manager = User::factory()->create([
            'role' => UserRole::Manager,
            'branch_id' => $branch->id,
        ]);

        $response = $this->actingAs($manager, 'sanctum')
            ->getJson('/api/v1/eod/reconciliation/not-a-date');

        $response->assertStatus(422)
            ->assertJsonValidationErrorFor('date');
    }
}
