<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class StatCard extends Component
{
    public function __construct() {}

    public function render(): View
    {
        return view('components.stat-card');
    }
}
