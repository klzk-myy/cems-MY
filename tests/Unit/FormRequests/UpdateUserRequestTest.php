<?php

namespace Tests\Unit\FormRequests;

use App\Http\Requests\UpdateUserRequest;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UpdateUserRequestTest extends TestCase
{
    #[Test]
    public function it_returns_expected_validation_rules(): void
    {
        $request = new UpdateUserRequest;
        $rules = $request->rules();

        $this->assertIsArray($rules);
        $this->assertArrayHasKey('username', $rules);
        $this->assertArrayHasKey('email', $rules);
        $this->assertArrayHasKey('role', $rules);
        $this->assertArrayHasKey('is_active', $rules);
        $this->assertArrayNotHasKey('password', $rules);
    }

    #[Test]
    public function it_allows_role_to_be_a_valid_user_role(): void
    {
        $request = new UpdateUserRequest;
        $rules = $request->rules();

        $this->assertContains('required', (array) $rules['role']);
    }

    #[Test]
    public function it_requires_is_active_boolean(): void
    {
        $request = new UpdateUserRequest;
        $rules = $request->rules();

        $this->assertStringContainsString('required', $rules['is_active']);
        $this->assertStringContainsString('boolean', $rules['is_active']);
    }
}
