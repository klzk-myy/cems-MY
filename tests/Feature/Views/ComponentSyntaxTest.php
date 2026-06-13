<?php

namespace Tests\Feature\Views;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ComponentSyntaxTest extends TestCase
{
    #[DataProvider('componentProvider')]
    public function test_component_renders_without_syntax_errors(string $component, array $data): void
    {
        $html = view($component, $data)->render();

        $this->assertStringNotContainsString('<@props', $html);
    }

    public static function componentProvider(): array
    {
        return [
            'button' => ['components.button', ['variant' => 'primary', 'slot' => 'Click']],
            'page-header' => ['components.page-header', ['title' => 'Page', 'description' => '', 'slot' => '']],
            'badge' => ['components.badge', ['variant' => 'success', 'slot' => 'Active']],
            'stat-card' => ['components.stat-card', ['label' => 'Total', 'value' => '100']],
            'table' => ['components.table', ['thead' => '', 'tbody' => '']],
            'card-section' => ['components.card-section', ['slot' => '']],
            'card' => ['components.card', ['slot' => '']],
            'chart-bar' => ['components.chart-bar', ['value' => 75]],
            'progress-bar' => ['components.progress-bar', ['value' => 50]],
        ];
    }
}
