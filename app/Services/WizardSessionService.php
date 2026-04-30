<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class WizardSessionService
{
    public function put(string $sessionId, array $data, int $ttl = 3600): void
    {
        Cache::put("wizard:{$sessionId}", $data, now()->addSeconds($ttl));
    }

    public function get(string $sessionId): ?array
    {
        return Cache::get("wizard:{$sessionId}");
    }

    public function forget(string $sessionId): void
    {
        Cache::forget("wizard:{$sessionId}");
    }
}
