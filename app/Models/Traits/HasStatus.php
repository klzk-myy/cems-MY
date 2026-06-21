<?php

namespace App\Models\Traits;

use BackedEnum;

trait HasStatus
{
    protected string $statusColumn = 'status';

    /** @return array<int|string|BackedEnum> */
    protected function activeStatusValues(): array
    {
        return [];
    }

    /** @return array<int|string|BackedEnum> */
    protected function openStatusValues(): array
    {
        return [];
    }

    public function scopeActive($query)
    {
        return $query->whereIn($this->statusColumn, $this->normalizedValues($this->activeStatusValues()));
    }

    public function scopeOpen($query)
    {
        return $query->whereIn($this->statusColumn, $this->normalizedValues($this->openStatusValues()));
    }

    public function isActive(): bool
    {
        return in_array($this->normalizeStatus($this->getAttribute($this->statusColumn)), $this->activeStatusValues(), true);
    }

    public function isOpen(): bool
    {
        return in_array($this->normalizeStatus($this->getAttribute($this->statusColumn)), $this->openStatusValues(), true);
    }

    public function statusLabel(): string
    {
        $status = $this->getAttribute($this->statusColumn);

        if (is_object($status) && method_exists($status, 'label')) {
            return $status->label();
        }

        return (string) $status;
    }

    public function statusColor(): string
    {
        $status = $this->getAttribute($this->statusColumn);

        if (is_object($status) && method_exists($status, 'color')) {
            return $status->color();
        }

        return 'gray';
    }

    /** @param array<int|string|BackedEnum> $values */
    protected function normalizedValues(array $values): array
    {
        return array_map(fn ($value) => $this->normalizeStatus($value), $values);
    }

    protected function normalizeStatus(mixed $status): int|string|null
    {
        return $status instanceof BackedEnum ? $status->value : $status;
    }
}
