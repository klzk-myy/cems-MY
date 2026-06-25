<?php

namespace App\Exceptions\Domain;

class InvalidIpAddressException extends DomainException
{
    public function __construct(string $ip = '')
    {
        $message = $ip ? "Invalid IP address format: {$ip}" : 'Invalid IP address format.';
        parent::__construct($message);
    }
}
