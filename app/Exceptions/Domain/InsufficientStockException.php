<?php

namespace App\Exceptions\Domain;

class InsufficientStockException extends DomainException
{
    public function __construct(
        public readonly string $currency,
        public readonly string $requested,
        public readonly string $available,
    ) {
        parent::__construct(
            "Insufficient stock for {$currency}. Requested: {$requested}, Available: {$available}"
        );
    }
}
