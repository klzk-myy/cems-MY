<?php

namespace App\Exceptions\Domain;

use InvalidArgumentException;

class InvalidIpAddressException extends InvalidArgumentException
{
    public function __construct(string $ip = '')
    {
        $message = $ip ? "Invalid IP address format: {$ip}" : 'Invalid IP address format.';
        parent::__construct($message);
    }
}
