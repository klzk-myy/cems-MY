<?php

namespace App\Exceptions\Domain;

class TransactionConfirmationRequiredException extends DomainException
{
    public function __construct(public readonly int $transactionId)
    {
        parent::__construct(
            "Transaction #{$transactionId} requires manager confirmation before approval. "
            .'A TransactionConfirmation with status Confirmed must exist for this transaction.'
        );
    }

    public function getStatusCode(): int
    {
        return 403;
    }

    public function getErrorCode(): string
    {
        return 'TRANSACTION_CONFIRMATION_REQUIRED';
    }
}
