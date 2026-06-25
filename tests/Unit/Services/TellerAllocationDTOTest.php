<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\Branch\TellerAllocationService;
use App\Services\DTOs\AllocationValidationResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TellerAllocationDTOTest extends TestCase
{
    use RefreshDatabase;

    public function test_validate_allocation_returns_dto(): void
    {
        $service = app(TellerAllocationService::class);
        $user = User::factory()->create(['role' => 'teller']);

        $result = $service->validateTransaction(
            $user,
            'USD',
            '100.00',
            true
        );

        $this->assertInstanceOf(AllocationValidationResult::class, $result);
        $this->assertFalse($result->valid);
        $this->assertEquals('No active allocation for this currency', $result->reason);
        $this->assertNull($result->allocation);
    }
}
