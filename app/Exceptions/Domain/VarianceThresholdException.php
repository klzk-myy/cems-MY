<?php

namespace App\Exceptions\Domain;

class VarianceThresholdException extends DomainException
{
    public function __construct(string $threshold, bool $requiresApproval = false)
    {
        $action = $requiresApproval ? 'requires supervisor approval' : 'requires explanation notes';
        parent::__construct("Variance exceeds {$threshold} threshold, {$action}");
    }
}
