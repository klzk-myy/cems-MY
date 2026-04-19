<?php

namespace App\Exceptions\Domain;

use InvalidArgumentException;

class InvalidTransactionStateException extends InvalidArgumentException
{
    public function __construct(string $requiredState, ?string $currentState = null)
    {
        $message = $currentState
            ? "Transaction must be {$requiredState} to perform this operation. Current state: {$currentState}"
            : "Transaction must be {$requiredState} to perform this operation";
        parent::__construct($message);
    }
}
