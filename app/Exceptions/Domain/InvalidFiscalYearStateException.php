<?php

namespace App\Exceptions\Domain;

use InvalidArgumentException;

class InvalidFiscalYearStateException extends InvalidArgumentException
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }
}
