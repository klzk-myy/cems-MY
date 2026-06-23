<?php

declare(strict_types=1);

namespace App\View\Components;

use Closure;
use Illuminate\View\Component;
use Illuminate\View\View;

class Navigation extends Component
{
    public function __construct(
        public bool $collapsible = true,
        public bool $collapsed = false,
    ) {}

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): Closure|View
    {
        return view('components.navigation');
    }
}
