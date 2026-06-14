<?php

namespace Tests\Feature\Views;

use Illuminate\View\ComponentAttributeBag;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ComponentConsistencyTest extends TestCase
{
    #[DataProvider('forwardingComponentProvider')]
    public function test_components_forward_attributes(string $component, array $data): void
    {
        $data['attributes'] = new ComponentAttributeBag([
            'class' => 'custom-class',
            'data-test' => 'foo',
        ]);

        $html = view($component, $data)->render();

        $this->assertStringContainsString('custom-class', $html);
        $this->assertStringContainsString('data-test="foo"', $html);
    }

    public static function forwardingComponentProvider(): array
    {
        return [
            'button' => ['components.button', ['variant' => 'primary', 'slot' => 'Click']],
            'card' => ['components.card', ['slot' => '']],
            'card-section' => ['components.card-section', ['slot' => '']],
            'alert' => ['components.alert', ['type' => 'info', 'slot' => 'Message']],
            'badge' => ['components.badge', ['variant' => 'success', 'slot' => 'Active']],
            'table' => ['components.table', ['thead' => '', 'tbody' => '']],
            'data-table' => ['components.data-table', []],
            'stat-card' => ['components.stat-card', ['label' => 'X', 'value' => '1']],
            'filter-bar' => ['components.filter-bar', ['slot' => '']],
            'progress-bar' => ['components.progress-bar', ['value' => 50]],
            'chart-bar' => ['components.chart-bar', ['value' => 50]],
            'chart-trend' => ['components.chart-trend', ['title' => 'X', 'labels' => [], 'values' => []]],
        ];
    }
}
