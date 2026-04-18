<?php

namespace App\Exceptions\Domain;

use RuntimeException;

class TransactionAlreadyProcessedException extends RuntimeException
{
    public function __construct(public readonly int $transactionId)
    {
        parent::__construct("Transaction {$transactionId} was already processed or modified");
    }
}
