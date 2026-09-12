<?php

namespace App\Exceptions\Domain;

class InsufficientPettyCashException extends DomainException
{
    public function __construct(
        public readonly string $available,
        public readonly string $requested
    ) {
        parent::__construct(
            "Insufficient petty cash float. Available: MYR {$available}, Requested: MYR {$requested}"
        );
    }
}
