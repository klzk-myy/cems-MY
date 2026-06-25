<?php

namespace App\Events;

use App\Models\CustomerRelation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CustomerRelationRemoved
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public CustomerRelation $relation
    ) {}
}
