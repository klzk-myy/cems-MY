<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\User;
use App\Services\System\CacheKeys;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class HomeController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        // Setup completion only flips false→true, so only the terminal state
        // is cached; a fresh install still re-probes until setup finishes.
        // A TTL (not forever) bounds staleness if the database is rebuilt
        // against a shared cache store.
        $isSetupComplete = Cache::get(CacheKeys::SetupComplete->value) ?? (function () {
            $complete = User::exists() &&
                        Currency::exists() &&
                        ExchangeRate::exists() &&
                        Branch::exists();

            if ($complete) {
                Cache::put(CacheKeys::SetupComplete->value, true, now()->addDay());
            }

            return $complete;
        })();

        if (! $isSetupComplete) {
            return redirect('/setup');
        }

        if (auth()->check()) {
            return redirect('/dashboard');
        }

        return redirect('/login');
    }
}
