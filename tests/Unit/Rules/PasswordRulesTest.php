<?php

namespace Tests\Unit\Rules;

use App\Rules\PasswordComplexityRule;
use App\Rules\PasswordNotRecentlyUsed;
use App\Rules\PasswordRules;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PasswordRulesTest extends TestCase
{
    #[Test]
    public function for_new_requires_confirmed_complex_password(): void
    {
        $rules = PasswordRules::forNew();

        $this->assertContains('required', $rules);
        $this->assertContains('string', $rules);
        $this->assertContains('confirmed', $rules);
        $this->assertTrue(
            collect($rules)->contains(fn ($rule) => $rule instanceof PasswordComplexityRule)
        );
        $this->assertFalse(
            collect($rules)->contains(fn ($rule) => $rule instanceof PasswordNotRecentlyUsed),
            'New credentials have no history to check against.'
        );
    }

    #[Test]
    public function for_change_adds_recent_history_check(): void
    {
        $rules = PasswordRules::forChange(null);

        $this->assertContains('confirmed', $rules);
        $this->assertTrue(
            collect($rules)->contains(fn ($rule) => $rule instanceof PasswordComplexityRule)
        );
        $this->assertTrue(
            collect($rules)->contains(fn ($rule) => $rule instanceof PasswordNotRecentlyUsed)
        );
    }

    #[Test]
    public function confirmed_can_be_dropped_for_non_form_callers(): void
    {
        $this->assertNotContains('confirmed', PasswordRules::forNew(confirmed: false));
        $this->assertNotContains('confirmed', PasswordRules::forChange(null, confirmed: false));
    }
}
