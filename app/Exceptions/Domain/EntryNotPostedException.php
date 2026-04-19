<?php

namespace App\Exceptions\Domain;

use RuntimeException;

class EntryNotPostedException extends RuntimeException
{
    public function __construct(int $entryId)
    {
        parent::__construct("Entry {$entryId} must be Posted to be reversed");
    }
}
