<?php

namespace App\Models\Traits;

trait HasNotes
{
    public function initializeHasNotes(): void
    {
        $this->mergeFillable(['notes']);
    }
}
