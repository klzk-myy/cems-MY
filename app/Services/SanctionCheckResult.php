<?php

namespace App\Services;

class SanctionCheckResult
{
    public function __construct(
        public bool $isBlocked,
        public ?string $message = null,
        public ?float $confidenceScore = null,
        public ?string $matchedEntity = null
    ) {}

    public static function blocked(string $message, float $score, string $entity = 'Unknown'): self
    {
        return new self(true, $message, $score, $entity);
    }

    public static function passed(): self
    {
        return new self(false);
    }

    public function isBlocked(): bool
    {
        return $this->isBlocked;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }
}
