<?php

namespace App\Exceptions\Domain;

class FiscalYearNotFoundException extends DomainException
{
    public function __construct(string $yearCode)
    {
        parent::__construct("Fiscal year not found: {$yearCode}");
    }
}
