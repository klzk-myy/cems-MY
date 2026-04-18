<?php

namespace App\Events;

use App\Models\StrDraft;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StrDraftGenerated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public StrDraft $strDraft
    ) {}
}
