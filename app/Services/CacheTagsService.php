<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class CacheTagsService
{
    /**
     * Invalidate all cache entries with the given tag.
     */
    public function invalidate(string $tag): void
    {
        Cache::tags([$tag])->flush();
    }
}
