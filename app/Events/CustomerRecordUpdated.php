<?php

namespace App\Events;

use App\Models\Customer;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CustomerRecordUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Customer $customer,
        public array $changedFields = [],
        public ?int $updatedBy = null
    ) {}
}
