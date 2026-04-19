<?php

namespace App\Exceptions\Domain;

use RuntimeException;

class PendingAllocationNotFoundException extends RuntimeException
{
    public function __construct(string $currency)
    {
        parent::__construct("No pending allocation found for {$currency}");
    }
}
