<?php

namespace Tests\Unit\View\Components;

use App\View\Components\Alert;
use Tests\TestCase;

class AlertTest extends TestCase
{
    public function test_alert_renders_with_type(): void
    {
        $component = new Alert(type: 'success', title: 'Success!');

        $this->assertEquals('success', $component->type);
        $this->assertEquals('Success!', $component->title);
        $this->assertTrue($component->shouldRender());
    }

    public function test_alert_style_classes(): void
    {
        $success = new Alert(type: 'success');
        $error = new Alert(type: 'error');
        $warning = new Alert(type: 'warning');
        $info = new Alert(type: 'info');

        $this->assertStringContainsString('bg-green-50', $success->getStyleClasses());
        $this->assertStringContainsString('bg-red-50', $error->getStyleClasses());
        $this->assertStringContainsString('bg-yellow-50', $warning->getStyleClasses());
        $this->assertStringContainsString('bg-blue-50', $info->getStyleClasses());
    }

    public function test_alert_icon_paths(): void
    {
        $component = new Alert(type: 'success');

        $this->assertNotEmpty($component->getIconPath());
        $this->assertStringContainsString('M9 12l2 2 4-4', $component->getIconPath());
    }

    public function test_alert_renders_view(): void
    {
        $component = new Alert(type: 'error', title: 'Test Error');

        $view = $component->render();

        $this->assertNotNull($view);
    }
}
