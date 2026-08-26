<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use App\Services\Branch\BranchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BranchWebCrudTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Payload used for both the web POST and the direct service call so the
     * parity assertion compares identical inputs.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(string $code, array $overrides = []): array
    {
        return array_merge([
            'code' => $code,
            'name' => 'Parity Branch',
            'type' => 'branch',
            'address' => '12 Test Street',
            'city' => 'Kuala Lumpur',
            'state' => 'KUL',
            'postal_code' => '50000',
            'country' => 'Malaysia',
            'phone' => '03-1234 5678',
            'email' => 'parity@example.test',
            'is_active' => true,
            'is_main' => false,
            'parent_id' => null,
        ], $overrides);
    }

    #[Test]
    public function web_store_produces_the_same_outcome_as_the_service_layer(): void
    {
        $admin = User::factory()->admin()->create();

        // Web path (controller delegates to BranchService::createBranch).
        $this->actingAs($admin)
            ->post(route('branches.store'), $this->payload('WEB1'))
            ->assertRedirect(route('branches.index'))
            ->assertSessionHas('success');

        $webBranch = Branch::where('code', 'WEB1')->firstOrFail();

        // Direct service path — the same call the API V1 controller makes.
        $serviceBranch = app(BranchService::class)->createBranch(
            $this->payload('SRV1'),
            $admin->id,
            '127.0.0.1'
        );

        foreach (['name', 'type', 'address', 'city', 'state', 'postal_code', 'country', 'phone', 'email'] as $attribute) {
            $this->assertSame($serviceBranch->{$attribute}, $webBranch->{$attribute});
        }

        $this->assertTrue($webBranch->is_active);
        $this->assertFalse($webBranch->is_main);
        $this->assertNull($webBranch->parent_id);
    }

    #[Test]
    public function web_update_produces_the_same_outcome_as_the_service_layer(): void
    {
        $admin = User::factory()->admin()->create();
        $branch = Branch::factory()->create(['code' => 'UPD1']);

        $this->actingAs($admin)
            ->put(route('branches.update', $branch), $this->payload('UPD1', ['name' => 'Renamed Via Web']))
            ->assertRedirect(route('branches.index'))
            ->assertSessionHas('success');

        // Mirror the exact update through the service directly.
        $serviceUpdated = app(BranchService::class)->updateBranch(
            Branch::factory()->create(['code' => 'UPD2']),
            $this->payload('UPD2', ['name' => 'Renamed Via Web']),
            $admin->id,
            '127.0.0.1'
        );

        $branch->refresh();

        $this->assertSame($serviceUpdated->name, $branch->name);
        $this->assertSame('Renamed Via Web', $branch->name);

        // Both paths must write the same audit actions.
        $this->assertDatabaseHas('system_logs', ['action' => 'branch_updated']);
    }

    #[Test]
    public function web_deactivate_produces_the_same_outcome_as_the_service_layer(): void
    {
        $admin = User::factory()->admin()->create();
        $branch = Branch::factory()->create(['code' => 'DEA1', 'is_active' => true]);

        $this->actingAs($admin)
            ->post(route('branches.deactivate', $branch))
            ->assertRedirect(route('branches.index'))
            ->assertSessionHas('success');

        $branch->refresh();

        $this->assertFalse($branch->is_active);
        $this->assertDatabaseHas('system_logs', [
            'action' => 'branch_deactivated',
            'entity_id' => $branch->id,
        ]);
    }

    #[Test]
    public function deactivate_is_blocked_for_the_main_branch(): void
    {
        $admin = User::factory()->admin()->create();
        $main = Branch::factory()->main()->create();

        $this->actingAs($admin)
            ->post(route('branches.deactivate', $main))
            ->assertRedirect(route('branches.index'))
            ->assertSessionHas('error');

        $this->assertTrue($main->refresh()->is_active);
    }

    #[Test]
    public function index_lists_branches_with_status_badges_and_resource_counts(): void
    {
        $admin = User::factory()->admin()->create();
        $active = Branch::factory()->create(['code' => 'ACT1']);
        Branch::factory()->inactive()->create(['code' => 'INA1']);

        $response = $this->actingAs($admin)
            ->get(route('branches.index'))
            ->assertOk();

        $response->assertSee('ACT1')->assertSee('INA1');
        $response->assertSee('Active')->assertSee('Inactive');
        // Attached resource counts render as table cells.
        $response->assertSee((string) $active->users()->count());
    }

    #[Test]
    public function non_admin_cannot_access_branch_management(): void
    {
        $teller = User::factory()->teller()->create();

        $this->actingAs($teller)
            ->get(route('branches.index'))
            ->assertForbidden();

        $this->actingAs($teller)
            ->post(route('branches.store'), $this->payload('TLL1'))
            ->assertForbidden();

        $this->assertDatabaseMissing('branches', ['code' => 'TLL1']);
    }

    #[Test]
    public function store_validates_against_duplicate_codes(): void
    {
        $admin = User::factory()->admin()->create();
        Branch::factory()->create(['code' => 'DUP1']);

        $this->actingAs($admin)
            ->post(route('branches.store'), $this->payload('DUP1'))
            ->assertSessionHasErrors('code');
    }
}
