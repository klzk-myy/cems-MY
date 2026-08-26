<?php

namespace App\Exceptions\Domain;

class KycExpiredException extends DomainException
{
    public function __construct(public readonly int $customerId)
    {
        parent::__construct(
            "Customer #{$customerId} cannot transact: KYC identity documents have expired. Please renew the customer's documents before proceeding."
        );
    }

    public function getStatusCode(): int
    {
        return 403;
    }
}
