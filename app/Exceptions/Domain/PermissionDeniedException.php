<?php

namespace App\Exceptions\Domain;

use InvalidArgumentException;

class PermissionDeniedException extends InvalidArgumentException
{
    public function __construct(string $action)
    {
        parent::__construct("User does not have permission to {$action}");
    }
}
