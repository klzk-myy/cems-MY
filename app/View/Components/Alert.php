<?php

namespace App\View\Components;

use Closure;
use Illuminate\View\Component;
use Illuminate\View\View;

class Alert extends Component
{
    public function __construct(
        public string $type = 'info',
        public ?string $title = null,
        public bool $dismissible = false,
        public bool $showIcon = true,
    ) {}

    /**
     * Get style classes based on type
     */
    public function getStyleClasses(): string
    {
        return match ($this->type) {
            'success' => 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800 text-green-700 dark:text-green-300',
            'error' => 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800 text-red-700 dark:text-red-300',
            'warning' => 'bg-yellow-50 dark:bg-yellow-900/20 border-yellow-200 dark:border-yellow-800 text-yellow-700 dark:text-yellow-300',
            'info' => 'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800 text-blue-700 dark:text-blue-300',
            default => 'bg-gray-50 dark:bg-gray-800 border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300',
        };
    }

    /**
     * Get icon SVG path based on type
     */
    public function getIconPath(): string
    {
        return match ($this->type) {
            'success' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
            'error' => 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z',
            'warning' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
            'info' => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
            default => '',
        };
    }

    /**
     * Determine if alert should be rendered
     */
    public function shouldRender(): bool
    {
        // Always render - slot check doesn't work in class context
        return true;
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): Closure|View
    {
        return function () {
            return view('components.alert', [
                'classes' => $this->getStyleClasses(),
                'iconPath' => $this->showIcon ? $this->getIconPath() : null,
            ]);
        };
    }
}
