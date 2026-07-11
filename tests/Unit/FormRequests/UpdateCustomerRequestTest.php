<?php

namespace Tests\Unit\FormRequests;

use App\Http\Requests\UpdateCustomerRequest;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UpdateCustomerRequestTest extends TestCase
{
    #[Test]
    public function it_returns_expected_validation_rules(): void
    {
        $request = new UpdateCustomerRequest;
        $rules = $request->rules();

        $this->assertIsArray($rules);
        $this->assertArrayHasKey('full_name', $rules);
        $this->assertArrayHasKey('id_type', $rules);
        $this->assertArrayHasKey('id_number', $rules);
        $this->assertArrayHasKey('date_of_birth', $rules);
        $this->assertArrayHasKey('nationality', $rules);
        $this->assertArrayHasKey('phone', $rules);
        $this->assertArrayHasKey('email', $rules);
        $this->assertArrayHasKey('risk_rating', $rules);
        $this->assertArrayHasKey('is_active', $rules);
    }

    #[Test]
    public function it_marks_id_number_as_sometimes_required_for_updates(): void
    {
        $request = new UpdateCustomerRequest;
        $rules = $request->rules();

        $this->assertContains('sometimes', (array) $rules['id_number']);
        $this->assertContains('required', (array) $rules['id_number']);
    }

    #[Test]
    public function it_allows_risk_rating_to_be_low_medium_or_high(): void
    {
        $request = new UpdateCustomerRequest;
        $rules = $request->rules();

        $this->assertContains('nullable', (array) $rules['risk_rating']);
        $this->assertContains('in:Low,Medium,High', (array) $rules['risk_rating']);
    }

    #[Test]
    public function it_phone_has_valid_format_when_provided(): void
    {
        $request = new UpdateCustomerRequest;
        $rules = $request->rules();

        $this->assertContains('nullable', (array) $rules['phone']);
        $this->assertContains('regex:/^(\+?6?01)[0-9]{8,9}$/', $rules['phone']);
    }
}
