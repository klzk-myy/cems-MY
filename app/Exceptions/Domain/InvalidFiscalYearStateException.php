<?php

namespace App\Exceptions\Domain;

class InvalidFiscalYearStateException extends DomainException
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }
}
