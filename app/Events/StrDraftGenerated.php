<?php

namespace App\Events;

use App\Models\StrReport;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StrDraftGenerated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public StrReport $strReport
    ) {}
}
