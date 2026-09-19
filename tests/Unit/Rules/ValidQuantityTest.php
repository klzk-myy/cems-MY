<?php

namespace Tests\Unit\Rules;

use App\Rules\ValidQuantity;
use Illuminate\Contracts\Validation\ValidationRule;
use Tests\TestCase;

class ValidQuantityTest extends TestCase
{
    public function test_passes_for_valid_amount(): void
    {
        $passed = true;
        try {
            $this->assertRulePasses(new ValidQuantity, 'quantity', 100);
        } catch (\Throwable $e) {
            $passed = false;
        }

        $this->assertTrue($passed);
    }

    public function test_fails_when_below_minimum(): void
    {
        $this->assertRuleFails(
            new ValidQuantity,
            'quantity',
            0,
            'The quantity must be at least 0.01.'
        );
    }

    public function test_fails_for_non_numeric_value(): void
    {
        $this->assertRuleFails(
            new ValidQuantity,
            'quantity',
            'abc',
            'The quantity must be a number.'
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
