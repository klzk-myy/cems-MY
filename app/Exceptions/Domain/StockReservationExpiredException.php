<?php

namespace App\Exceptions\Domain;

use RuntimeException;

class StockReservationExpiredException extends RuntimeException
{
    public function __construct(public readonly int $transactionId)
    {
        parent::__construct("Stock reservation expired or not found for transaction {$transactionId}");
    }
}
