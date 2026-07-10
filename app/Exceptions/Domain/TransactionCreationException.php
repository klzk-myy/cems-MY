<?php

namespace App\Exceptions\Domain;

class TransactionCreationException extends TransactionException
{
    public function getStatusCode(): int
    {
        return 422;
    }
}
