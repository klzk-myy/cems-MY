<?php

namespace Tests\Unit\Models;

use App\Models\Alert;
use App\Models\Compliance\ComplianceCase;
use App\Models\EnhancedDiligenceRecord;
use App\Models\FlaggedTransaction;
use App\Models\ScreeningResult;
use Tests\TestCase;

class EagerLoadingCoverageTest extends TestCase
{
    private function getModelWithProperty(object $model): array
    {
        $reflection = new \ReflectionClass($model);
        $property = $reflection->getProperty('with');
        $property->setAccessible(true);

        return $property->getValue($model) ?? [];
    }

    public function test_key_listing_models_eager_loading_configuration(): void
    {
        $this->assertSame(['customer', 'assignee'], $this->getModelWithProperty(new ComplianceCase));
        $this->assertSame(['flaggedTransaction', 'assignedTo', 'case'], $this->getModelWithProperty(new Alert));
        $this->assertSame(['transaction', 'customer', 'assignedTo', 'reviewer'], $this->getModelWithProperty(new FlaggedTransaction));
        $this->assertSame(['customer', 'transaction', 'sanctionEntry'], $this->getModelWithProperty(new ScreeningResult));
        $this->assertSame(['customer', 'reviewer', 'template'], $this->getModelWithProperty(new EnhancedDiligenceRecord));
    }
}
