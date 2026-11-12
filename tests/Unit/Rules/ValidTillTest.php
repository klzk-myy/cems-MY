<?php

namespace Tests\Unit\Rules;

use App\Enums\CounterStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\User;
use App\Rules\ValidTill;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ValidTillTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_passes_for_active_counter_in_the_users_branch(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['role' => UserRole::Teller, 'branch_id' => $branch->id]);
        $counter = Counter::factory()->create([
            'branch_id' => $branch->id,
            'status' => CounterStatus::Active,
        ]);

        $this->actingAs($user);

        $this->assertRulePasses(new ValidTill, 'till_id', (string) $counter->code);
    }

    #[Test]
    public function test_fails_for_inactive_counter(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['role' => UserRole::Teller, 'branch_id' => $branch->id]);
        $counter = Counter::factory()->create([
            'branch_id' => $branch->id,
            'status' => CounterStatus::Inactive,
        ]);

        $this->actingAs($user);

        $this->assertRuleFails(
            new ValidTill,
            'till_id',
            (string) $counter->code,
            'The selected till_id is not open.'
        );
    }

    #[Test]
    public function test_fails_for_counter_from_another_branch(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['role' => UserRole::Teller, 'branch_id' => $branch->id]);
        $otherCounter = Counter::factory()->create(['status' => CounterStatus::Active]);

        $this->actingAs($user);

        $this->assertRuleFails(
            new ValidTill,
            'till_id',
            (string) $otherCounter->code,
            'The selected till_id is invalid.'
        );
    }

    #[Test]
    public function test_fails_for_nonexistent_counter(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['role' => UserRole::Teller, 'branch_id' => $branch->id]);

        $this->actingAs($user);

        $this->assertRuleFails(
            new ValidTill,
            'till_id',
            'missing-counter',
            'The selected till_id is invalid.'
        );
    }

    #[Test]
    public function test_fails_for_non_string_input(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['role' => UserRole::Teller, 'branch_id' => $branch->id]);

        $this->actingAs($user);

        $this->assertRuleFails(
            new ValidTill,
            'till_id',
            ['counter-code'],
            'The till_id must be a string.'
        );
    }

    #[Test]
    public function test_fails_closed_when_unauthenticated(): void
    {
        $counter = Counter::factory()->create(['status' => CounterStatus::Active]);

        // No acting user: the rule must refuse rather than fall back to an
        // unscoped lookup that would resolve another branch's counter.
        $this->assertRuleFails(
            new ValidTill,
            'till_id',
            (string) $counter->code,
            'The selected till_id could not be validated for an unauthenticated request.'
        );
    }

    #[Test]
    public function test_fails_for_user_without_a_branch(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin, 'branch_id' => null]);
        $counter = Counter::factory()->create(['status' => CounterStatus::Active]);

        $this->actingAs($user);

        $this->assertRuleFails(
            new ValidTill,
            'till_id',
            (string) $counter->code,
            'The selected till_id could not be validated without an assigned branch.'
        );
    }

    private function assertRuleFails(ValidationRule $rule, string $attribute, mixed $value, string $expectedMessage): void
    {
        $actualMessage = null;

        $rule->validate($attribute, $value, function ($message) use (&$actualMessage) {
            $actualMessage = $message;
        });

        $this->assertSame($expectedMessage, $actualMessage);
    }

    private function assertRulePasses(ValidationRule $rule, string $attribute, mixed $value): void
    {
        $failure = null;

        $rule->validate($attribute, $value, function ($message) use (&$failure) {
            $failure = $message;
        });

        $this->assertNull($failure, $failure ?? 'The rule failed validation.');
    }
}
