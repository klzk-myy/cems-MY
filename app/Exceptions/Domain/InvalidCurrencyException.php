<?php

namespace App\Exceptions\Domain;

class InvalidCurrencyException extends DomainException
{
    public function __construct(string $currencyCode)
    {
        parent::__construct("Invalid or inactive currency code: {$currencyCode}");
    }
}
