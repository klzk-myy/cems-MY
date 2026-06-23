<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\CounterSession;
use App\Models\Currency;
use App\Models\TellerAllocation;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CounterHandoverBranchScopeTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function handover_rejects_users_from_different_branch(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();
        $currency = Currency::factory()->create();

        $counter = Counter::factory()->create(['branch_id' => $branchA->id]);
        $fromUser = User::factory()->create([
            'role' => UserRole::Teller,
            'branch_id' => $branchA->id,
        ]);
        $toUser = User::factory()->create([
            'role' => UserRole::Teller,
            'branch_id' => $branchB->id,
        ]);
        $supervisor = User::factory()->create([
            'role' => UserRole::Manager,
            'branch_id' => $branchA->id,
        ]);

        CounterSession::factory()->create([
            'counter_id' => $counter->id,
            'user_id' => $fromUser->id,
            'session_date' => now()->toDateString(),
            'status' => 'open',
            'opened_by' => $fromUser->id,
        ]);
        TellerAllocation::factory()->create([
            'user_id' => $fromUser->id,
            'branch_id' => $branchA->id,
            'counter_id' => $counter->id,
            'currency_code' => $currency->code,
            'status' => 'active',
        ]);

        $this->actingAs($supervisor)
            ->post(route('counters.handover', $counter), [
                'from_user_id' => $fromUser->id,
                'to_user_id' => $toUser->id,
                'supervisor_id' => $supervisor->id,
                'physical_counts' => [
                    ['currency_id' => $currency->code, 'amount' => '100'],
                ],
            ])
            ->assertSessionHas('error');
    }
}
