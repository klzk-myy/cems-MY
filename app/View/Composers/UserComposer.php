<?php

namespace App\View\Composers;

use Illuminate\View\View;

class UserComposer
{
    /**
     * Bind data to the view.
     */
    public function compose(View $view): void
    {
        $view->with('currentUser', auth()->user());
        $view->with('userRole', auth()->check() ? auth()->user()->role : null);
        $view->with('userName', auth()->check() ? auth()->user()->name : 'Guest');
    }
}
