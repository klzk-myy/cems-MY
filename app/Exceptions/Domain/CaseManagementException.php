<?php

namespace App\Exceptions\Domain;

class CaseManagementException extends DomainException
{
    public function __construct(
        string $message,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return 422;
    }
}
