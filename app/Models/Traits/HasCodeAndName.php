<?php

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Casts\Attribute;

trait HasCodeAndName
{
    /**
     * Get the full display name with code prefix.
     */
    protected function displayName(): Attribute
    {
        return Attribute::get(fn () => "{$this->code} - {$this->name}");
    }

    /**
     * Scope: Find by code.
     */
    public function scopeByCode($query, string $code)
    {
        return $query->where('code', $code);
    }

    /**
     * Check if code matches (case-insensitive).
     */
    public function codeMatches(string $code): bool
    {
        return strcasecmp($this->code, $code) === 0;
    }
}
