<?php

namespace App\Exceptions\Domain;

class EntryNotPostedException extends DomainException
{
    public function __construct(int $entryId)
    {
        parent::__construct("Entry {$entryId} must be Posted to be reversed");
    }
}
