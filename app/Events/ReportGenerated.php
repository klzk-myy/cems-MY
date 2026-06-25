<?php

namespace App\Events;

use App\Models\ReportRun;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReportGenerated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public ReportRun $reportRun
    ) {}
}
