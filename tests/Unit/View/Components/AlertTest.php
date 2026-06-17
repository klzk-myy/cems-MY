<?php

namespace Tests\Unit\View\Components;

use Tests\TestCase;

class AlertTest extends TestCase
{
    public function test_alert_renders_success_variant(): void
    {
        $html = view('components.alert', ['type' => 'success', 'slot' => 'Saved'])->render();
        $this->assertStringContainsString('bg-success-subtle', $html);
        $this->assertStringContainsString('Saved', $html);
    }

    public function test_alert_renders_error_variant(): void
    {
        $html = view('components.alert', ['type' => 'error', 'slot' => 'Failed'])->render();
        $this->assertStringContainsString('bg-danger-subtle', $html);
        $this->assertStringContainsString('Failed', $html);
    }

    public function test_alert_danger_alias_renders_error_styling(): void
    {
        $html = view('components.alert', ['type' => 'danger', 'slot' => 'Danger'])->render();
        $this->assertStringContainsString('bg-danger-subtle', $html);
        // also ensure no raw red classes
        $this->assertStringNotContainsString('bg-red-', $html);
        $this->assertStringNotContainsString('text-red-', $html);
    }

    public function test_alert_renders_title(): void
    {
        $html = view('components.alert', ['type' => 'info', 'title' => 'Note', 'slot' => 'Body'])->render();
        $this->assertStringContainsString('Note', $html);
        $this->assertStringContainsString('Body', $html);
    }

    public function test_alert_can_hide_icon(): void
    {
        $html = view('components.alert', ['type' => 'info', 'icon' => false, 'slot' => 'No icon'])->render();
        $this->assertStringNotContainsString('<svg', $html);
    }
}
