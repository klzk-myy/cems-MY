<?php

namespace Tests\Feature\Views;

use Illuminate\View\ComponentAttributeBag;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FilterBarFormTest extends TestCase
{
    #[Test]
    public function filter_bar_renders_a_form_when_method_is_passed(): void
    {
        $html = view('components.filter-bar', ['method' => 'GET', 'slot' => '<input name="q">'])->render();

        $this->assertStringContainsString('<form method="GET"', $html);
        $this->assertStringContainsString('</form>', $html);
        $this->assertStringNotContainsString('<div', $html);
    }

    #[Test]
    public function filter_bar_renders_a_div_without_method(): void
    {
        $html = view('components.filter-bar', ['slot' => 'x'])->render();

        $this->assertStringContainsString('<div', $html);
        $this->assertStringNotContainsString('<form', $html);
    }

    #[Test]
    public function filter_bar_form_uses_custom_action(): void
    {
        $html = view('components.filter-bar', ['method' => 'GET', 'action' => '/reports/x', 'slot' => ''])->render();

        $this->assertStringContainsString('action="/reports/x"', $html);
    }

    #[Test]
    public function select_does_not_preselect_zero_option_when_value_is_null(): void
    {
        $html = view('components.select', [
            'name' => 'is_active',
            'options' => ['' => 'All', '1' => 'Active', '0' => 'Inactive'],
            'attributes' => new ComponentAttributeBag(['selected' => null]),
        ])->render();

        // The '0' option must not be marked selected when nothing was requested.
        $this->assertMatchesRegularExpression('/<option value="0"(?![^>]*selected)[^>]*>\s*Inactive/', $html);
    }

    #[Test]
    public function select_marks_zero_option_selected_when_explicitly_requested(): void
    {
        $html = view('components.select', [
            'name' => 'is_active',
            'options' => ['' => 'All', '1' => 'Active', '0' => 'Inactive'],
            'attributes' => new ComponentAttributeBag(['selected' => '0']),
        ])->render();

        $this->assertMatchesRegularExpression('/<option value="0"[^>]*selected[^>]*>\s*Inactive/', $html);
    }
}
