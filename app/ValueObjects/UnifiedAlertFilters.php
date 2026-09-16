<?php

namespace App\ValueObjects;

use App\Http\Requests\UnifiedAlertIndexRequest;

final class UnifiedAlertFilters
{
    public function __construct(
        public readonly string $source,
        public readonly ?string $priority,
        public readonly ?string $status,
        public readonly ?string $type,
        public readonly ?string $customerSearch,
        public readonly ?string $fromDate,
        public readonly ?string $toDate,
        public readonly int $page = 1,
        public readonly int $perPage = 25,
    ) {}

    public static function fromRequest(UnifiedAlertIndexRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            source: $validated['source'] ?? 'all',
            priority: $validated['priority'] ?? null,
            status: $validated['status'] ?? null,
            type: $validated['type'] ?? null,
            customerSearch: $validated['customer'] ?? null,
            fromDate: $validated['from_date'] ?? null,
            toDate: $validated['to_date'] ?? null,
            page: max(1, (int) ($validated['page'] ?? 1)),
        );
    }

    public function includesAlerts(): bool
    {
        return $this->source === 'all' || $this->source === 'alert';
    }

    public function includesFindings(): bool
    {
        return $this->source === 'all' || $this->source === 'finding';
    }
}
