<?php

namespace App\Exceptions\Domain;

class DuplicateTransactionException extends DomainException
{
    public function __construct(int $cooldownSeconds = 30)
    {
        parent::__construct(
            "Potential duplicate transaction detected. Please wait {$cooldownSeconds} seconds before submitting again or check your recent transactions."
        );
    }
}
