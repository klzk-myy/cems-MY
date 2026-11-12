<?php

namespace Tests\Unit;

use App\Enums\KpiMetric;
use App\Models\Bi\BiAlertRule;
use App\Services\Bi\KpiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class KpiMetricTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function alert_rule_metrics_are_derived_from_the_catalog(): void
    {
        $this->assertSame(KpiMetric::labelMap(), BiAlertRule::availableMetrics());
    }

    #[Test]
    public function every_metric_resolves_a_label_a_unit_and_a_real_drill_route(): void
    {
        foreach (KpiMetric::cases() as $metric) {
            $this->assertNotSame('', $metric->label());
            $this->assertContains($metric->unit(), ['myr', 'percent', 'seconds', 'count']);
            $this->assertNotNull(
                Route::getRoutes()->getByName($metric->drillRoute()),
                "{$metric->value} points at a route that does not exist"
            );
        }
    }

    #[Test]
    public function every_catalog_metric_is_resolvable_by_the_alert_evaluator(): void
    {
        $metrics = app(KpiService::class)->periodMetrics(today()->subDays(6), today(), null);

        foreach (KpiMetric::cases() as $metric) {
            $this->assertArrayHasKey($metric->value, $metrics);
        }
    }
}
