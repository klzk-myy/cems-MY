<?php

namespace App\Exceptions\Domain;

class NegativeTillCloseException extends DomainException
{
    public function __construct(public readonly string $currency, public readonly string $tillId)
    {
        parent::__construct(
            "Cannot close till {$tillId} with a negative {$currency} balance. Reconcile the cash count before closing."
        );
    }
}
