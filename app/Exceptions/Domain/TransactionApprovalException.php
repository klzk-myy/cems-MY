<?php

namespace App\Exceptions\Domain;

class TransactionApprovalException extends TransactionException
{
    public function getStatusCode(): int
    {
        return 422;
    }
}
