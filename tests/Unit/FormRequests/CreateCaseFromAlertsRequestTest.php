<?php

namespace Tests\Unit\FormRequests;

use App\Http\Requests\CreateCaseFromAlertsRequest;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CreateCaseFromAlertsRequestTest extends TestCase
{
    #[Test]
    public function it_returns_expected_validation_rules()
    {
        $request = new CreateCaseFromAlertsRequest;
        $rules = $request->rules();

        $this->assertIsArray($rules);
        $this->assertArrayHasKey('alert_ids', $rules);
        $this->assertIsString($rules['alert_ids']);
        $this->assertStringContainsString('required', $rules['alert_ids']);
        $this->assertStringContainsString('array', $rules['alert_ids']);
        $this->assertStringContainsString('min:1', $rules['alert_ids']);
        $this->assertArrayHasKey('alert_ids.*', $rules);
        $this->assertSame('exists:alerts,id', $rules['alert_ids.*']);
    }
}
