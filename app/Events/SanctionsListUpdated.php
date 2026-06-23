<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SanctionsListUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $source, // 'ofac', 'un', 'eu'
        public int $previousVersion,
        public int $newVersion,
        public int $newEntriesCount = 0,
        public int $removedEntriesCount = 0
    ) {}
}
