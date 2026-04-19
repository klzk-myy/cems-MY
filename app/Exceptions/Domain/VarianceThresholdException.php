<?php

namespace App\Exceptions\Domain;

use RuntimeException;

class VarianceThresholdException extends RuntimeException
{
    public function __construct(string $threshold, bool $requiresApproval = false)
    {
        $action = $requiresApproval ? 'requires supervisor approval' : 'requires explanation notes';
        parent::__construct("Variance exceeds {$threshold} threshold, {$action}");
    }
}
