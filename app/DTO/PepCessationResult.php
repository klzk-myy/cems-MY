<?php

namespace App\DTO;

use Carbon\Carbon;

class PepCessationResult
{
    public function __construct(
        public readonly bool $canCessate,
        public readonly array $factors,
        public readonly Carbon $assessedAt,
    ) {}

    public function toArray(): array
    {
        return [
            'can_cessate' => $this->canCessate,
            'factors' => $this->factors,
            'assessed_at' => $this->assessedAt->toIso8601String(),
        ];
    }
}
