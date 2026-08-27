<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class AuthLayout extends Component
{
    public function __construct(
        public ?string $title = null,
    ) {}

    public function render(): View
    {
        return view('components.auth-layout');
    }
}
