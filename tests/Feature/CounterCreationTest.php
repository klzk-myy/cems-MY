<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Exceptions\Domain\InvalidStateException;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\User;
use App\Services\Branch\CounterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CounterCreationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(Branch $branch, array $overrides = []): array
    {
        return array_merge([
            'code' => 'CTR1',
            'name' => 'Counter One',
            'branch_id' => $branch->id,
            'status' => 'active',
        ], $overrides);
    }

    #[Test]
    public function admin_can_create_counter_at_any_trading_branch(): void
    {
        $admin = User::factory()->admin()->create();
        $branch = Branch::factory()->create();

        $this->actingAs($admin)
            ->post(route('counters.store'), $this->payload($branch))
            ->assertRedirect(route('counters.index'))
            ->assertSessionHas('success');

        $counter = Counter::where('code', 'CTR1')->firstOrFail();
        $this->assertSame($branch->id, $counter->branch_id);
        $this->assertDatabaseHas('system_logs', [
            'action' => 'counter_created',
            'entity_id' => $counter->id,
        ]);
    }

    #[Test]
    public function manager_can_create_counter_at_own_branch(): void
    {
        $branch = Branch::factory()->create();
        $manager = User::factory()->manager()->create(['branch_id' => $branch->id]);

        $this->actingAs($manager)
            ->post(route('counters.store'), $this->payload($branch))
            ->assertRedirect(route('counters.index'));

        $this->assertDatabaseHas('counters', ['code' => 'CTR1', 'branch_id' => $branch->id]);
    }

    #[Test]
    public function manager_cannot_create_counter_at_another_branch(): void
    {
        $own = Branch::factory()->create();
        $other = Branch::factory()->create();
        $manager = User::factory()->manager()->create(['branch_id' => $own->id]);

        $this->actingAs($manager)
            ->post(route('counters.store'), $this->payload($other))
            ->assertForbidden();

        $this->assertDatabaseMissing('counters', ['code' => 'CTR1']);
    }

    #[Test]
    public function teller_cannot_access_counter_creation(): void
    {
        $branch = Branch::factory()->create();
        $teller = User::factory()->teller()->create(['branch_id' => $branch->id]);

        $this->actingAs($teller)
            ->get(route('counters.create'))
            ->assertForbidden();

        $this->actingAs($teller)
            ->post(route('counters.store'), $this->payload($branch))
            ->assertForbidden();

        $this->assertDatabaseMissing('counters', ['code' => 'CTR1']);
    }

    #[Test]
    public function counter_cannot_be_created_at_head_office_via_web(): void
    {
        $admin = User::factory()->admin()->create();
        $hq = Branch::factory()->main()->create();

        $this->actingAs($admin)
            ->post(route('counters.store'), $this->payload($hq))
            ->assertSessionHasErrors('branch_id');

        $this->assertDatabaseMissing('counters', ['code' => 'CTR1']);
    }

    #[Test]
    public function service_rejects_counters_at_non_trading_branches(): void
    {
        $admin = User::factory()->admin()->create();
        $hq = Branch::factory()->main()->create();

        $this->expectException(InvalidStateException::class);

        app(CounterService::class)->createCounter($this->payload($hq), $admin);
    }

    #[Test]
    public function store_validates_against_duplicate_codes(): void
    {
        $admin = User::factory()->admin()->create();
        $branch = Branch::factory()->create();
        Counter::factory()->create(['code' => 'DUP1', 'branch_id' => $branch->id]);

        $this->actingAs($admin)
            ->post(route('counters.store'), $this->payload($branch, ['code' => 'DUP1']))
            ->assertSessionHasErrors('code');
    }

    #[Test]
    public function api_store_creates_counter_for_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $branch = Branch::factory()->create();

        $this->actingAs($admin)
            ->postJson('/api/v1/counters', $this->payload($branch))
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.counter.code', 'CTR1');

        $this->assertDatabaseHas('counters', ['code' => 'CTR1', 'branch_id' => $branch->id]);
    }

    #[Test]
    public function api_store_denies_tellers(): void
    {
        $branch = Branch::factory()->create();
        $teller = User::factory()->create(['role' => UserRole::Teller, 'branch_id' => $branch->id]);

        $this->actingAs($teller)
            ->postJson('/api/v1/counters', $this->payload($branch))
            ->assertStatus(403);
    }

    #[Test]
    public function api_store_denies_cross_branch_managers(): void
    {
        $own = Branch::factory()->create();
        $other = Branch::factory()->create();
        $manager = User::factory()->create(['role' => UserRole::Manager, 'branch_id' => $own->id]);

        $this->actingAs($manager)
            ->postJson('/api/v1/counters', $this->payload($other))
            ->assertStatus(403);

        $this->assertDatabaseMissing('counters', ['code' => 'CTR1']);
    }

    #[Test]
    public function api_store_returns_422_for_head_office_branch(): void
    {
        $admin = User::factory()->admin()->create();
        $hq = Branch::factory()->main()->create();

        $this->actingAs($admin)
            ->postJson('/api/v1/counters', $this->payload($hq))
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors('branch_id');
    }
}
