<?php

namespace App\Exceptions\Domain;

use RuntimeException;

class TillClosedException extends RuntimeException
{
    public function __construct(?string $tillId = null)
    {
        $message = $tillId
            ? "Till {$tillId} is closed. Cannot perform operations on closed till."
            : 'Till is closed. Cannot perform operations on closed till.';
        parent::__construct($message);
    }
}
