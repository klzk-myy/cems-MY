<?php

namespace App\Exceptions\Domain;

class TransactionValidationException extends TransactionException
{
    public function getStatusCode(): int
    {
        return 422;
    }
}
