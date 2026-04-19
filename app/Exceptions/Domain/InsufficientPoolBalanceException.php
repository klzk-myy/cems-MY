<?php

namespace App\Exceptions\Domain;

use RuntimeException;

class InsufficientPoolBalanceException extends RuntimeException
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
