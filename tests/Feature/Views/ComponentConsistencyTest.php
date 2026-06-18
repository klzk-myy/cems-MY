<?php

namespace Tests\Feature\Views;

use Illuminate\View\ComponentAttributeBag;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ComponentConsistencyTest extends TestCase
{
    #[DataProvider('forwardingComponentProvider')]
    #[Test]
    public function components_forward_attributes(string $component, array $data): void
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
            'textarea' => ['components.textarea', ['name' => 'notes', 'slot' => '']],
            'checkbox' => ['components.checkbox', ['name' => 'is_active', 'label' => 'Active', 'slot' => '']],
            'radio-group' => ['components.radio-group', ['name' => 'risk_level', 'options' => ['low' => 'Low'], 'slot' => '']],
            'empty-state-div' => ['components.empty-state', ['as' => 'div', 'slot' => '']],
            'verify-card' => ['pages.mfa.verify', []],
            'link' => ['components.link', ['href' => '/dashboard', 'slot' => 'Dashboard']],
            'status-dot' => ['components.status-dot', ['color' => 'success', 'slot' => '']],
            'icon-circle' => ['components.icon-circle', ['color' => 'info', 'slot' => '']],
        ];
    }

    #[Test]
    public function mfa_verify_uses_card_component(): void
    {
        $path = resource_path('views/pages/mfa/verify.blade.php');
        $content = file_get_contents($path);

        $this->assertStringContainsString('<x-card', $content);
        $this->assertStringNotContainsString('bg-surface rounded-lg shadow p-6', $content);
    }
}
