<?php

namespace Tests\Unit\FormRequests;

use App\Http\Requests\StoreTransactionRequest;
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
        $this->assertArrayHasKey('amount_foreign', $rules);
        $this->assertArrayHasKey('rate', $rules);
        $this->assertArrayHasKey('purpose', $rules);
        $this->assertArrayHasKey('source_of_funds', $rules);
        $this->assertArrayHasKey('branch_id', $rules);
        $this->assertArrayHasKey('counter_id', $rules);
        $this->assertArrayHasKey('idempotency_key', $rules);
    }

    #[Test]
    public function it_requires_customer_id_to_be_present(): void
    {
        $request = new StoreTransactionRequest;
        $rules = $request->rules();

        $this->assertStringContainsString('required', $rules['customer_id']);
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
    public function it_requires_amount_foreign_to_be_numeric(): void
    {
        $request = new StoreTransactionRequest;
        $rules = $request->rules();

        $this->assertStringContainsString('required', $rules['amount_foreign']);
        $this->assertStringContainsString('numeric', $rules['amount_foreign']);
        $this->assertStringContainsString('min:0.01', $rules['amount_foreign']);
    }
}
