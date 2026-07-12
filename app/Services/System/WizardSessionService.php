<?php

namespace App\Services\System;

use Illuminate\Support\Facades\Cache;

class WizardSessionService
{
    public function put(string $sessionId, array $data, int $ttl = 3600): void
    {
        Cache::put(CacheKeys::wizardSession($sessionId), $data, now()->addSeconds($ttl));
    }

    public function get(string $sessionId): ?array
    {
        return Cache::get(CacheKeys::wizardSession($sessionId));
    }

    public function forget(string $sessionId): void
    {
        Cache::forget(CacheKeys::wizardSession($sessionId));
    }
}
