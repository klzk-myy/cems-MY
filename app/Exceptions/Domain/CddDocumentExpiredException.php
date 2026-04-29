<?php

namespace App\Exceptions\Domain;

use App\Models\Customer;
use InvalidArgumentException;

class CddDocumentExpiredException extends InvalidArgumentException
{
    public function __construct(Customer $customer)
    {
        parent::__construct(
            "Customer {$customer->full_name} has expired KYC documents"
        );
    }
}
