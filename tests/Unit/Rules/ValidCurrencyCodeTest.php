<?php

namespace Tests\Unit\Rules;

use App\Models\Currency;
use App\Rules\ValidCurrencyCode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ValidCurrencyCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_passes_for_active_currency(): void
    {
        $this->expectNotToPerformAssertions();

        Currency::factory()->create(['code' => 'USD', 'is_active' => true]);

        $this->assertRulePasses(new ValidCurrencyCode, 'currency_code', 'USD');
    }

    public function test_fails_for_inactive_currency(): void
    {
        Currency::factory()->create(['code' => 'TST', 'is_active' => false]);

        $this->assertRuleFails(
            new ValidCurrencyCode,
            'currency_code',
            'TST',
            'The selected currency_code is inactive.'
        );
    }

    public function test_fails_for_nonexistent_currency(): void
    {
        $this->assertRuleFails(
            new ValidCurrencyCode,
            'currency_code',
            'XYZ',
            'The selected currency_code is invalid.'
        );
    }

    public function test_fails_for_non_string_input(): void
    {
        $this->assertRuleFails(
            new ValidCurrencyCode,
            'currency_code',
            ['USD'],
            'The currency_code must be a string.'
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
