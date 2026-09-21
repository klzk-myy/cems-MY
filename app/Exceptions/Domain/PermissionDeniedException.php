<?php

namespace App\Exceptions\Domain;

class PermissionDeniedException extends DomainException
{
    public function __construct(string $action, ?string $message = null)
    {
        parent::__construct($message ?? "User does not have permission to {$action}");
    }

    public function getStatusCode(): int
    {
        return 403;
    }
}
