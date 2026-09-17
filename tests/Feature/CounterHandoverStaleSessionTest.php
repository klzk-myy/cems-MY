<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\CounterSession;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CounterHandoverStaleSessionTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function handover_page_renders_for_a_session_left_open_overnight(): void
    {
        $branch = Branch::factory()->create();
        $counter = Counter::factory()->create(['branch_id' => $branch->id]);
        $teller = User::factory()->create([
            'role' => UserRole::Teller,
            'branch_id' => $branch->id,
        ]);
        $manager = User::factory()->create([
            'role' => UserRole::Manager,
            'branch_id' => $branch->id,
        ]);

        // A session forgotten overnight keeps the counter open — the close and
        // handover POSTs already resolve it via the date-agnostic
        // findOpenSession, so the GET page must too (the index links here
        // whenever any open session exists).
        CounterSession::factory()->create([
            'counter_id' => $counter->id,
            'user_id' => $teller->id,
            'session_date' => now()->subDay()->toDateString(),
            'status' => 'open',
            'opened_by' => $teller->id,
        ]);

        $this->actingAs($manager)
            ->get(route('counters.handover.show', $counter))
            ->assertOk();
    }

    #[Test]
    public function handover_page_still_404s_with_no_open_session(): void
    {
        $branch = Branch::factory()->create();
        $counter = Counter::factory()->create(['branch_id' => $branch->id]);
        $manager = User::factory()->create([
            'role' => UserRole::Manager,
            'branch_id' => $branch->id,
        ]);

        $this->actingAs($manager)
            ->get(route('counters.handover.show', $counter))
            ->assertNotFound();
    }
}
