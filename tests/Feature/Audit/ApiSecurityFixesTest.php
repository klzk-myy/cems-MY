<?php

namespace Tests\Feature\Audit;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiSecurityFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_teller_cannot_generate_msb2_report_via_api(): void
    {
        $teller = User::factory()->for(Branch::factory()->create())->create([
            'role' => UserRole::Teller,
        ]);

        $this->actingAs($teller, 'sanctum')
            ->postJson(route('api.v1.reports.msb2'), ['date' => today()->subDay()->toDateString()])
            ->assertForbidden();
    }
}
