<?php

namespace Tests\Unit\Models;

use App\Models\EnhancedDiligenceRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeadCastCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_enhanced_diligence_record_has_no_dead_casts(): void
    {
        $record = EnhancedDiligenceRecord::factory()->create();
        $this->assertArrayNotHasKey('responses', $record->getCasts());
        $this->assertArrayNotHasKey('documents_received', $record->getCasts());
    }
}
