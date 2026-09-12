<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SanctionsListUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $source, // sanction_lists.slug e.g. 'ofac_sdn', 'un_consolidated'
        public ?string $previousVersion,
        public ?string $newVersion,
        public int $newEntriesCount = 0,
        public int $removedEntriesCount = 0
    ) {}
}
