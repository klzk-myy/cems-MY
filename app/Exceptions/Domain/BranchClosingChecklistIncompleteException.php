<?php

namespace App\Exceptions\Domain;

class BranchClosingChecklistIncompleteException extends DomainException
{
    protected $message = 'Branch closing checklist is incomplete. All items must be completed before finalizing.';
}
