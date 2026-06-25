<?php

namespace Tests\Unit\FormRequests;

use App\Http\Requests\StoreCustomerRequest;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StoreCustomerRequestTest extends TestCase
{
    #[Test]
    public function it_returns_expected_validation_rules(): void
    {
        $request = new StoreCustomerRequest;
        $rules = $request->rules();

        $this->assertIsArray($rules);
        $this->assertArrayHasKey('full_name', $rules);
        $this->assertArrayHasKey('id_type', $rules);
        $this->assertArrayHasKey('id_number', $rules);
        $this->assertArrayHasKey('date_of_birth', $rules);
        $this->assertArrayHasKey('nationality', $rules);
        $this->assertArrayHasKey('phone', $rules);
        $this->assertArrayHasKey('email', $rules);
    }

    #[Test]
    public function it_requires_full_name_to_be_present(): void
    {
        $request = new StoreCustomerRequest;
        $rules = $request->rules();

        $this->assertStringContainsString('required', $rules['full_name']);
        $this->assertStringContainsString('string', $rules['full_name']);
        $this->assertStringContainsString('max:255', $rules['full_name']);
    }

    #[Test]
    public function it_requires_id_type_to_be_valid(): void
    {
        $request = new StoreCustomerRequest;
        $rules = $request->rules();

        $this->assertContains('required', (array) $rules['id_type']);
    }

    #[Test]
    public function it_phone_has_valid_format_when_provided(): void
    {
        $request = new StoreCustomerRequest;
        $rules = $request->rules();

        // Phone is nullable but when provided must match regex
        $this->assertContains('nullable', (array) $rules['phone']);
        $this->assertContains('regex:/^(\+?6?01)[0-9]{8,9}$/', $rules['phone']);
    }
}
