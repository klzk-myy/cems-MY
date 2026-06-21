<?php

namespace Tests\Unit\Models\Relationships;

use App\Models\TestResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TestResultRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_belongs_to_executed_by_user(): void
    {
        $user = User::factory()->create();
        $result = TestResult::factory()->create(['executed_by' => $user->id]);

        $this->assertTrue($result->executedBy->is($user));
    }
}
