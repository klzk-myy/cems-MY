<?php

namespace Tests\Unit\FormRequests;

use App\Http\Requests\StoreUserRequest;
use Illuminate\Validation\Rules\Unique;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StoreUserRequestTest extends TestCase
{
    #[Test]
    public function it_returns_expected_validation_rules(): void
    {
        $request = new StoreUserRequest;
        $rules = $request->rules();

        $this->assertIsArray($rules);
        $this->assertArrayHasKey('username', $rules);
        $this->assertArrayHasKey('email', $rules);
        $this->assertArrayHasKey('password', $rules);
        $this->assertArrayHasKey('password_confirmation', $rules);
        $this->assertArrayHasKey('role', $rules);
    }

    #[Test]
    public function it_requires_password_for_new_users(): void
    {
        $request = new StoreUserRequest;
        $rules = $request->rules();

        $this->assertContains('required', (array) $rules['password']);
        $this->assertContains('min:12', (array) $rules['password']);
    }

    #[Test]
    public function it_requires_username_and_email_to_be_unique(): void
    {
        $request = new StoreUserRequest;
        $rules = $request->rules();

        $usernameHasUnique = collect((array) $rules['username'])
            ->contains(fn ($rule) => $rule instanceof Unique);
        $emailHasUnique = collect((array) $rules['email'])
            ->contains(fn ($rule) => $rule instanceof Unique);

        $this->assertTrue($usernameHasUnique, 'Username rule should be unique.');
        $this->assertTrue($emailHasUnique, 'Email rule should be unique.');
    }
}
