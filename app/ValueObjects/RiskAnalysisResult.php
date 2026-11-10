<?php

namespace App\ValueObjects;

class RiskAnalysisResult
{
    private array $flags = [];

    public function addFlag(array $flag): void
    {
        $this->flags[] = $flag;
    }

    public function getFlags(): array
    {
        return $this->flags;
    }

    public function hasCriticalFlags(): bool
    {
        foreach ($this->flags as $flag) {
            if ($flag['severity'] === 'critical') {
                return true;
            }
        }

        return false;
    }
}
