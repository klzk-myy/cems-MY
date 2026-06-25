<?php

namespace App\Exceptions\Domain;

class FiscalYearClosedException extends DomainException
{
    public function __construct(string $message = 'Fiscal year is already closed')
    {
        parent::__construct($message);
    }
}
