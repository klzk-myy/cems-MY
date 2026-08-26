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
        $user = auth()->user();

        $view->with('currentUser', $user);
        $view->with('userRole', $user?->role);
        $view->with('userName', $user ? $user->username : 'Guest');
    }
}
