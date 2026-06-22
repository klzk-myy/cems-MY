<?php

namespace Tests\Unit\View\Components;

use App\View\Components\AppLayout;
use Illuminate\Contracts\View\View;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AppLayoutTest extends TestCase
{
    #[Test]
    public function it_renders_a_view_instance(): void
    {
        $component = new AppLayout;

        $view = $component->render();

        $this->assertInstanceOf(View::class, $view);
    }

    #[Test]
    public function it_accepts_a_title(): void
    {
        $component = new AppLayout('Dashboard');

        $this->assertEquals('Dashboard', $component->title);
    }
}
