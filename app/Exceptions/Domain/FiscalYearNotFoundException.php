<?php

namespace App\Exceptions\Domain;

use Exception;

class FiscalYearNotFoundException extends Exception
{
    public function __construct(string $yearCode)
    {
        parent::__construct("Fiscal year not found: {$yearCode}");
    }
}
