<?php

namespace App\Exceptions\Domain;

use RuntimeException;

class TillBalanceMissingException extends RuntimeException
{
    public function __construct(public readonly string $currency, public readonly string $tillId)
    {
        parent::__construct("Till balance not found for {$currency} at till {$tillId}");
    }
}
