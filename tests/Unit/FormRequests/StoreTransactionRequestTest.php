<?php

namespace Tests\Unit\FormRequests;

use App\Http\Requests\StoreTransactionRequest;
use App\Rules\ValidQuantity;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StoreTransactionRequestTest extends TestCase
{
    #[Test]
    public function it_returns_expected_validation_rules(): void
    {
        $request = new StoreTransactionRequest;
        $rules = $request->rules();

        $this->assertIsArray($rules);
        $this->assertArrayHasKey('customer_id', $rules);
        $this->assertArrayHasKey('type', $rules);
        $this->assertArrayHasKey('currency_code', $rules);
        $this->assertArrayHasKey('quantity', $rules);
        $this->assertArrayHasKey('rate', $rules);
        $this->assertArrayHasKey('purpose', $rules);
        $this->assertArrayHasKey('source_of_funds', $rules);
        $this->assertArrayHasKey('branch_id', $rules);
        $this->assertArrayHasKey('counter_id', $rules);
        $this->assertArrayHasKey('idempotency_key', $rules);
    }

    #[Test]
    public function it_allows_nullable_customer_id_for_inline_registration(): void
    {
        $request = new StoreTransactionRequest;
        $rules = $request->rules();

        // customer_id is nullable — inline registration supplies identity
        // fields (full_name, id_number, ...) instead and resolves the record
        // server-side in CustomerService::resolveForBooking.
        $this->assertStringContainsString('nullable', $rules['customer_id']);
        $this->assertStringContainsString('exists:customers,id', $rules['customer_id']);
    }

    #[Test]
    public function it_requires_type_to_be_valid_enum(): void
    {
        $request = new StoreTransactionRequest;
        $rules = $request->rules();

        $this->assertContains('required', (array) $rules['type']);
    }

    #[Test]
    public function it_requires_quantity_with_strict_rule(): void
    {
        $request = new StoreTransactionRequest;
        $rules = $request->rules();

        // Web and API now share the strict rule set: required + ValidQuantity.
        $this->assertContains('required', $rules['quantity']);
        $this->assertContainsOnlyInstancesOf(
            ValidQuantity::class,
            array_filter($rules['quantity'], fn ($rule) => is_object($rule))
        );
    }
}
