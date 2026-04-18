<?php

namespace App\Services;

class SanctionCheckResult
{
    private bool $blocked;

    private string $message;

    private float $matchScore;

    private ?string $matchedEntity;

    public function __construct(
        bool $blocked,
        string $message,
        float $matchScore = 0.0,
        ?string $matchedEntity = null
    ) {
        $this->blocked = $blocked;
        $this->message = $message;
        $this->matchScore = $matchScore;
        $this->matchedEntity = $matchedEntity;
    }

    public function isBlocked(): bool
    {
        return $this->blocked;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getMatchScore(): float
    {
        return $this->matchScore;
    }

    public function getMatchedEntity(): ?string
    {
        return $this->matchedEntity;
    }

    public static function passed(): self
    {
        return new self(false, 'Sanctions screening passed');
    }

    public static function blocked(string $message, float $score, string $entity): self
    {
        return new self(true, $message, $score, $entity);
    }
}
