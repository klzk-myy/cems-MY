<?php

namespace App\Exceptions\Domain;

class TillClosedException extends DomainException
{
    public function __construct(?string $tillId = null)
    {
        $message = $tillId
            ? "Till {$tillId} is closed. Cannot perform operations on closed till."
            : 'Till is closed. Cannot perform operations on closed till.';
        parent::__construct($message);
    }
}
