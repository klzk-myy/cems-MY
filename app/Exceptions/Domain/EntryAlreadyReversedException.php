<?php

namespace App\Exceptions\Domain;

class EntryAlreadyReversedException extends DomainException
{
    public function __construct(int $entryId)
    {
        parent::__construct("Entry {$entryId} has already been reversed");
    }
}
