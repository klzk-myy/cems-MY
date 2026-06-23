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
}
