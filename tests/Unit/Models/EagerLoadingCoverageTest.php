<?php

namespace Tests\Unit\Models;

use App\Models\Compliance\Alert;
use App\Models\Compliance\ComplianceCase;
use App\Models\Compliance\EnhancedDiligenceRecord;
use App\Models\Compliance\FlaggedTransaction;
use App\Models\Compliance\ScreeningResult;
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
        // Models no longer use aggressive $with eager loading.
        // Eager loading is applied per-query in the service/controller layer.
        $this->assertSame([], $this->getModelWithProperty(new ComplianceCase));
        $this->assertSame([], $this->getModelWithProperty(new Alert));
        $this->assertSame([], $this->getModelWithProperty(new FlaggedTransaction));
        $this->assertSame([], $this->getModelWithProperty(new ScreeningResult));
        $this->assertSame([], $this->getModelWithProperty(new EnhancedDiligenceRecord));
    }
}
