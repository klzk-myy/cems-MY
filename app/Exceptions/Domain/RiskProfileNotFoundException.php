<?php

namespace App\Exceptions\Domain;

class RiskProfileNotFoundException extends DomainException
{
    public function __construct(int $customerId)
    {
        parent::__construct("Risk profile not found for customer ID: {$customerId}");
    }
}
