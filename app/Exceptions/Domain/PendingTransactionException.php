<?php

namespace App\Exceptions\Domain;

class PendingTransactionException extends DomainException
{
    public function __construct(public readonly int $transactionId, public readonly string $status)
    {
        parent::__construct("Transaction {$transactionId} is pending ({$status}) and cannot be modified");
    }
}
