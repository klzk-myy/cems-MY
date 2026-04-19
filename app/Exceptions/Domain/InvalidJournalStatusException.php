<?php

namespace App\Exceptions\Domain;

use InvalidArgumentException;

class InvalidJournalStatusException extends InvalidArgumentException
{
    public function __construct(string $requiredStatus, ?string $currentStatus = null)
    {
        $message = $currentStatus
            ? "Only {$requiredStatus} entries can perform this operation. Current status: {$currentStatus}"
            : "Only {$requiredStatus} entries can perform this operation";
        parent::__construct($message);
    }
}
