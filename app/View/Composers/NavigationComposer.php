<?php

namespace App\View\Composers;

use App\Config\Navigation;
use Illuminate\View\View;

class NavigationComposer
{
    /**
     * Bind data to the view.
     */
    public function compose(View $view): void
    {
        $user = auth()->user();

        $navigation = $user
            ? Navigation::getForRole($user->role)
            : ['main' => Navigation::get()['main']];

        $view->with('navigation', $navigation);
    }
}
