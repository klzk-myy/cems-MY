<?php

namespace Tests\Feature\Views;

use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ThemeTokenUsageTest extends TestCase
{
    #[DataProvider('themedComponentProvider')]
    public function test_component_uses_theme_tokens(string $component, array $data, array $expectedTokens): void
    {
        $html = view($component, $data)->render();

        foreach ($expectedTokens as $token) {
            $this->assertStringContainsString($token, $html, "Component {$component} should use theme token {$token}.");
        }
    }

    public static function themedComponentProvider(): array
    {
        return [
            'app-layout' => ['components.app-layout', ['slot' => ''], ['bg-canvas-subtle', 'text-ink']],
            'card' => ['components.card', ['title' => 'Title', 'slot' => ''], ['bg-surface', 'border-border', 'text-ink']],
            'card-section' => ['components.card-section', ['title' => 'Title', 'slot' => ''], ['bg-surface', 'border-border', 'text-ink']],
            'button-primary' => ['components.button', ['variant' => 'primary', 'slot' => 'Click'], ['bg-primary']],
            'button-secondary' => ['components.button', ['variant' => 'secondary', 'slot' => 'Click'], ['bg-surface', 'border-border', 'text-ink-muted']],
            'button-primary-foreground' => ['components.button', ['variant' => 'primary', 'slot' => 'Click'], ['text-on-primary']],
            'button-danger-foreground' => ['components.button', ['variant' => 'danger', 'slot' => 'Click'], ['text-on-danger']],
            'button-success-foreground' => ['components.button', ['variant' => 'success', 'slot' => 'Click'], ['text-on-success']],
            'button-warning-foreground' => ['components.button', ['variant' => 'warning', 'slot' => 'Click'], ['text-on-warning']],
            'button-info-foreground' => ['components.button', ['variant' => 'info', 'slot' => 'Click'], ['text-on-info']],
            'button-primary-hover' => ['components.button', ['variant' => 'primary', 'slot' => 'Click'], ['bg-primary-hover']],
            'button-danger-hover' => ['components.button', ['variant' => 'danger', 'slot' => 'Click'], ['bg-danger-hover']],
            'button-success-hover' => ['components.button', ['variant' => 'success', 'slot' => 'Click'], ['bg-success-hover']],
            'button-warning-hover' => ['components.button', ['variant' => 'warning', 'slot' => 'Click'], ['bg-warning-hover']],
            'button-info-hover' => ['components.button', ['variant' => 'info', 'slot' => 'Click'], ['bg-info-hover']],
            'alert' => ['components.alert', ['type' => 'info', 'slot' => 'Message'], ['bg-info-subtle', 'border-info-border', 'text-info-text']],
            'badge' => ['components.badge', ['variant' => 'success', 'slot' => 'Active'], ['bg-success-subtle', 'text-success-text']],
            'badge-gray' => ['components.badge', ['variant' => 'gray', 'slot' => 'Draft'], ['bg-canvas-subtle', 'text-ink-muted']],
            'input' => ['components.input', ['name' => 'foo', 'errors' => new ViewErrorBag], ['bg-surface', 'border-border', 'text-ink']],
            'select' => ['components.select', ['name' => 'foo', 'options' => [], 'errors' => new ViewErrorBag], ['bg-surface', 'border-border', 'text-ink']],
            'input-error-text' => ['components.input', [
                'name' => 'foo',
                'label' => 'Foo',
                'errors' => (new ViewErrorBag)->put('default', new MessageBag(['foo' => ['The foo field is required.']])),
            ], ['text-danger-text']],
            'select-error-text' => ['components.select', [
                'name' => 'foo',
                'options' => [],
                'label' => 'Foo',
                'errors' => (new ViewErrorBag)->put('default', new MessageBag(['foo' => ['The foo field is required.']])),
            ], ['text-danger-text']],
            'table' => ['components.table', ['thead' => '', 'tbody' => ''], ['bg-surface', 'divide-border', 'bg-canvas-subtle']],
            'data-table' => ['components.data-table', [], ['bg-surface', 'border-border']],
            'stat-card' => ['components.stat-card', ['label' => 'X', 'value' => '1'], ['bg-surface', 'border-border', 'text-ink-muted']],
            'filter-bar' => ['components.filter-bar', ['slot' => ''], ['bg-surface', 'border-border']],
            'empty-state' => ['components.empty-state', [], ['text-ink-muted']],
            'progress-bar' => ['components.progress-bar', ['value' => 50], ['bg-canvas-subtle']],
            'chart-trend' => ['components.chart-trend', ['title' => 'X', 'labels' => [], 'values' => []], ['bg-surface', 'border-border', 'text-ink']],
            'navigation-tokens' => ['components.navigation', [], ['bg-sidebar', 'text-sidebar-text']],
        ];
    }

    public function test_button_primary_uses_on_primary_foreground(): void
    {
        $html = view('components.button', ['variant' => 'primary', 'slot' => 'Click'])->render();
        $this->assertStringContainsString('text-on-primary', $html);
    }

    #[DataProvider('themedComponentProvider')]
    public function test_component_avoids_hardcoded_colors(string $component, array $data): void
    {
        $html = view($component, $data)->render();

        $this->assertStringNotContainsString('bg-[#', $html, "Component {$component} uses hardcoded bg-[#...] color.");
        $this->assertStringNotContainsString('border-[#', $html, "Component {$component} uses hardcoded border-[#...] color.");
        $this->assertStringNotContainsString('text-gray-900', $html, "Component {$component} uses text-gray-900.");
        $this->assertStringNotContainsString('text-gray-500', $html, "Component {$component} uses text-gray-500.");
    }

    public function test_badge_purple_does_not_use_raw_tailwind_colors(): void
    {
        $html = view('components.badge', ['variant' => 'purple', 'slot' => 'VIP'])->render();
        $this->assertStringContainsString('bg-accent/10', $html, 'Badge purple variant should use bg-accent/10 token.');
        $this->assertStringContainsString('text-accent', $html, 'Badge purple variant should use text-accent token.');
        $this->assertStringNotContainsString('text-purple-700', $html, 'Badge purple variant should not use raw text-purple-700.');
        $this->assertStringNotContainsString('bg-purple-100', $html, 'Badge purple variant should not use raw bg-purple-100.');
    }
}
