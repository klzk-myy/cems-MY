<?php

namespace App\Exceptions\Domain;

class PermissionDeniedException extends DomainException
{
    public function __construct(string $action)
    {
        parent::__construct("User does not have permission to {$action}");
    }
}
