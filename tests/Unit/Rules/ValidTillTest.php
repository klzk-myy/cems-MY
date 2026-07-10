<?php

namespace Tests\Unit\Rules;

use App\Enums\CounterStatus;
use App\Models\Counter;
use App\Rules\ValidTill;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ValidTillTest extends TestCase
{
    use RefreshDatabase;

    public function test_passes_for_active_counter(): void
    {
        $this->expectNotToPerformAssertions();

        $counter = Counter::factory()->create(['status' => CounterStatus::Active]);

        $this->assertRulePasses(new ValidTill, 'till_id', (string) $counter->code);
    }

    public function test_fails_for_inactive_counter(): void
    {
        $counter = Counter::factory()->create(['status' => CounterStatus::Inactive]);

        $this->assertRuleFails(
            new ValidTill,
            'till_id',
            (string) $counter->code,
            'The selected till_id is not open.'
        );
    }

    public function test_fails_for_nonexistent_counter(): void
    {
        $this->assertRuleFails(
            new ValidTill,
            'till_id',
            'missing-counter',
            'The selected till_id is invalid.'
        );
    }

    public function test_fails_for_non_string_input(): void
    {
        $this->assertRuleFails(
            new ValidTill,
            'till_id',
            ['counter-code'],
            'The till_id must be a string.'
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
        $rule->validate($attribute, $value, fn ($message) => $this->fail($message));
    }
}
