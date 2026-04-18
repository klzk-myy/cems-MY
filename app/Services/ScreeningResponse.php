<?php

namespace App\Services;

use App\Models\ScreeningResult;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ScreeningResponse
{
    public function __construct(
        public readonly string $action,
        public readonly float $confidenceScore,
        public readonly Collection $matches,
        public readonly Carbon $screenedAt,
        public readonly ?int $resultId = null,
    ) {}

    public static function fromResult(ScreeningResult $result): self
    {
        return new self(
            action: $result->result,
            confidenceScore: (float) ($result->match_score * 100),
            matches: new Collection,
            screenedAt: $result->created_at ?? Carbon::now(),
            resultId: $result->id,
        );
    }

    public function isClear(): bool
    {
        return $this->action === 'clear';
    }

    public function isFlagged(): bool
    {
        return $this->action === 'flag';
    }

    public function isBlocked(): bool
    {
        return $this->action === 'block';
    }

    public function toArray(): array
    {
        return [
            'action' => $this->action,
            'confidence_score' => $this->confidenceScore,
            'matches' => $this->matches->map(fn (ScreeningMatch $m) => $m->toArray())->toArray(),
            'screened_at' => $this->screenedAt->toIso8601String(),
            'result_id' => $this->resultId,
        ];
    }
}
