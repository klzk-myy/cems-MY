<?php

namespace App\Services;

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

    public function getFlagSummary(): string
    {
        if (empty($this->flags)) {
            return 'No risk flags detected';
        }

        $summary = [];
        foreach ($this->flags as $flag) {
            $summary[] = "[{$flag['severity']}] {$flag['type']}";
        }

        return implode(', ', $summary);
    }
}
