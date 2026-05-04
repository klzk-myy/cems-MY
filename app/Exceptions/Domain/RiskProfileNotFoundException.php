<?php

namespace App\Exceptions\Domain;

use Exception;

class RiskProfileNotFoundException extends Exception
{
    public function __construct(int $customerId)
    {
        parent::__construct("Risk profile not found for customer ID: {$customerId}");
    }
}
