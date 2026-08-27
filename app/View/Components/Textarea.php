<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class Textarea extends Component
{
    public function __construct() {}

    public function render(): View
    {
        return view('components.textarea');
    }
}
