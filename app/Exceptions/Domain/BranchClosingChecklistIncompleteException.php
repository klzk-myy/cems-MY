<?php

namespace App\Exceptions\Domain;

use Exception;

class BranchClosingChecklistIncompleteException extends Exception
{
    protected $message = 'Branch closing checklist is incomplete. All items must be completed before finalizing.';
}
