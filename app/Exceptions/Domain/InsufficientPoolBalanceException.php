<?php

namespace App\Exceptions\Domain;

class InsufficientPoolBalanceException extends DomainException
{
    public function __construct(
        public readonly string $currency,
        public readonly string $available,
        public readonly string $requested
    ) {
        parent::__construct(
            "Insufficient available balance in branch pool. Currency: {$currency}, Available: {$available}, Requested: {$requested}"
        );
    }
}
