<?php

namespace App\Services\Customer;

use App\Models\Customer;

final class CustomerActionResult
{
    public function __construct(
        public readonly Customer $customer,
        public readonly ?string $message = null,
    ) {}
}
