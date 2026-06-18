<?php

namespace App\Exceptions\Domain;

class StockReservationExpiredException extends DomainException
{
    public function __construct(public readonly int $transactionId)
    {
        parent::__construct("Stock reservation expired or not found for transaction {$transactionId}");
    }
}
