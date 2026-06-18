<?php

namespace Tests\Unit;

use App\Models\CustomerRelation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CustomerRelationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function customer_relation_can_store_engagement_assessment(): void
    {
        $relation = CustomerRelation::factory()->create();

        $relation->assessEngagement('direct', 'Works directly with PEP on financial transactions');

        $this->assertEquals('direct', $relation->engagement_level);
        $this->assertEquals('Works directly with PEP on financial transactions', $relation->engagement_notes);
        $this->assertNotNull($relation->engagement_assessed_at);
    }

    #[Test]
    public function customer_relation_can_store_indirect_engagement(): void
    {
        $relation = CustomerRelation::factory()->create();

        $relation->assessEngagement('indirect');

        $this->assertEquals('indirect', $relation->engagement_level);
        $this->assertNull($relation->engagement_notes);
        $this->assertNotNull($relation->engagement_assessed_at);
    }

    #[Test]
    public function customer_relation_can_store_minimal_engagement(): void
    {
        $relation = CustomerRelation::factory()->create();

        $relation->assessEngagement('minimal', 'Occasional social connection only');

        $this->assertEquals('minimal', $relation->engagement_level);
        $this->assertEquals('Occasional social connection only', $relation->engagement_notes);
    }
}
