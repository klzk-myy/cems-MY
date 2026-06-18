<?php

namespace App\Exceptions\Domain;

class TransactionAlreadyProcessedException extends DomainException
{
    public function __construct(public readonly int $transactionId)
    {
        parent::__construct("Transaction {$transactionId} was already processed or modified");
    }
}
