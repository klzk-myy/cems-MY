<?php

namespace Tests\Unit\Rules;

use App\Rules\ValidRate;
use Illuminate\Contracts\Validation\ValidationRule;
use Tests\TestCase;

class ValidRateTest extends TestCase
{
    public function test_passes_for_valid_rate(): void
    {
        $this->expectNotToPerformAssertions();

        $this->assertRulePasses(new ValidRate, 'rate', 1.5);
    }

    public function test_fails_when_below_minimum(): void
    {
        $this->assertRuleFails(
            new ValidRate,
            'rate',
            0,
            'The rate must be at least 0.0001.'
        );
    }

    public function test_fails_for_non_numeric_value(): void
    {
        $this->assertRuleFails(
            new ValidRate,
            'rate',
            'abc',
            'The rate must be a number.'
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
