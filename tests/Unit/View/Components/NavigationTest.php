<?php

namespace Tests\Unit\View\Components;

use App\View\Components\Navigation;
use Illuminate\Contracts\View\View;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    #[Test]
    public function it_renders_a_view_instance(): void
    {
        $component = new Navigation;

        $view = $component->render();

        $this->assertInstanceOf(View::class, $view);
    }

    #[Test]
    public function it_renders_with_collapsible_true_by_default(): void
    {
        $component = new Navigation;

        $this->assertTrue($component->collapsible);
    }

    #[Test]
    public function it_renders_with_collapsible_false(): void
    {
        $component = new Navigation(collapsible: false);

        $this->assertFalse($component->collapsible);
    }

    #[Test]
    public function it_renders_with_collapsed_true(): void
    {
        $component = new Navigation(collapsed: true);

        $this->assertTrue($component->collapsed);
    }

    #[Test]
    public function it_renders_navigation_view(): void
    {
        $component = new Navigation;
        $view = $component->render();

        $this->assertInstanceOf(View::class, $view);

        $html = $view->render();
        $this->assertStringContainsString('navigation', $html);
    }
}
