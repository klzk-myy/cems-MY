<?php

namespace App\Services;

use App\Enums\CddLevel;

class PreValidationResult
{
    private array $blocks = [];

    private ?CddLevel $cddLevel = null;

    private array $riskFlags = [];

    private bool $holdRequired = false;

    public function addBlock(string $type, string $message): void
    {
        $this->blocks[] = ['type' => $type, 'message' => $message];
    }

    public function isBlocked(): bool
    {
        return count($this->blocks) > 0;
    }

    public function getBlocks(): array
    {
        return $this->blocks;
    }

    public function setCDDLevel(CddLevel $cddLevel): void
    {
        $this->cddLevel = $cddLevel;
    }

    public function getCDDLevel(): ?CddLevel
    {
        return $this->cddLevel;
    }

    public function setRiskFlags(array $flags): void
    {
        $this->riskFlags = $flags;
    }

    public function getRiskFlags(): array
    {
        return $this->riskFlags;
    }

    public function setHoldRequired(bool $required): void
    {
        $this->holdRequired = $required;
    }

    public function isHoldRequired(): bool
    {
        return $this->holdRequired;
    }
}
