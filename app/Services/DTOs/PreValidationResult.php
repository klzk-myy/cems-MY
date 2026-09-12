<?php

namespace App\Services\DTOs;

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

    public function setCDDLevel(CddLevel $level): void
    {
        $this->cddLevel = $level;
    }

    public function setRiskFlags(array $flags): void
    {
        $this->riskFlags = $flags;
    }

    /**
     * @param  array<string, mixed>  $flag
     */
    public function addRiskFlag(array $flag): void
    {
        $this->riskFlags[] = $flag;
    }

    public function setHoldRequired(bool $required): void
    {
        $this->holdRequired = $required;
    }

    public function isBlocked(): bool
    {
        return count($this->blocks) > 0;
    }

    public function isHoldRequired(): bool
    {
        return $this->holdRequired;
    }

    public function getCDDLevel(): ?CddLevel
    {
        return $this->cddLevel;
    }

    public function getRiskFlags(): array
    {
        return $this->riskFlags;
    }

    public function getBlocks(): array
    {
        return $this->blocks;
    }
}
