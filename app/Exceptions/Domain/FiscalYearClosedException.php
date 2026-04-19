<?php

namespace App\Exceptions\Domain;

use InvalidArgumentException;

class FiscalYearClosedException extends InvalidArgumentException
{
    public function __construct(string $message = 'Fiscal year is already closed')
    {
        parent::__construct($message);
    }
}
