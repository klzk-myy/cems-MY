<?php

namespace Tests\Unit\FormRequests;

use App\Http\Requests\ConfirmTransactionApprovalRequest;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConfirmTransactionApprovalRequestTest extends TestCase
{
    #[Test]
    public function it_returns_expected_validation_rules(): void
    {
        $request = new ConfirmTransactionApprovalRequest;
        $rules = $request->rules();

        $this->assertIsArray($rules);
        $this->assertArrayHasKey('confirmation_action', $rules);
        $this->assertArrayHasKey('notes', $rules);
    }

    #[Test]
    public function it_requires_confirmation_action_to_be_valid(): void
    {
        $request = new ConfirmTransactionApprovalRequest;
        $rules = $request->rules();

        $this->assertStringContainsString('required', $rules['confirmation_action']);
        $this->assertStringContainsString('in:confirm,reject', $rules['confirmation_action']);
    }

    #[Test]
    public function it_allows_optional_notes(): void
    {
        $request = new ConfirmTransactionApprovalRequest;
        $rules = $request->rules();

        $this->assertStringContainsString('nullable', $rules['notes']);
        $this->assertStringContainsString('string', $rules['notes']);
        $this->assertStringContainsString('max:500', $rules['notes']);
    }
}
