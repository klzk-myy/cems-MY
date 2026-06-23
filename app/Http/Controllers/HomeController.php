<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $isSetupComplete = User::exists() &&
                           Currency::exists() &&
                           ExchangeRate::exists() &&
                           Branch::exists();

        if (! $isSetupComplete) {
            return redirect('/setup');
        }

        if (auth()->check()) {
            return redirect('/dashboard');
        }

        return redirect('/login');
    }
}
