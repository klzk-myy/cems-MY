<?php

namespace App\Exceptions\Domain;

use Exception;

class MonthEndPreCheckFailedException extends Exception
{
    protected array $failures;

    public function __construct(array $failures, string $message = 'Month-end pre-check failed')
    {
        parent::__construct($message);
        $this->failures = $failures;
    }

    public function getFailures(): array
    {
        return $this->failures;
    }
}
