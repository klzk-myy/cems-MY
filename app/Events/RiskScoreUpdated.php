<?php

namespace App\Events;

use App\Models\RiskScoreSnapshot;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RiskScoreUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public RiskScoreSnapshot $snapshot
    ) {}
}
