<?php

namespace App\Exceptions\Domain;

use InvalidArgumentException;

class InvalidCurrencyException extends InvalidArgumentException
{
    public function __construct(string $currencyCode)
    {
        parent::__construct("Invalid or inactive currency code: {$currencyCode}");
    }
}
