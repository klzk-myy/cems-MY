<?php

namespace Tests\Unit\Rules;

use App\Rules\ValidAmountForeign;
use Illuminate\Contracts\Validation\ValidationRule;
use Tests\TestCase;

class ValidAmountForeignTest extends TestCase
{
    public function test_passes_for_valid_amount(): void
    {
        $this->expectNotToPerformAssertions();

        $this->assertRulePasses(new ValidAmountForeign, 'amount_foreign', 100);
    }

    public function test_fails_when_below_minimum(): void
    {
        $this->assertRuleFails(
            new ValidAmountForeign,
            'amount_foreign',
            0,
            'The amount_foreign must be at least 0.01.'
        );
    }

    public function test_fails_for_non_numeric_value(): void
    {
        $this->assertRuleFails(
            new ValidAmountForeign,
            'amount_foreign',
            'abc',
            'The amount_foreign must be a number.'
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
