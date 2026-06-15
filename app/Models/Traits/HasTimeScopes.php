<?php

namespace App\Models\Traits;

trait HasTimeScopes
{
    protected string $timeScopeColumn = 'created_at';

    public function scopeLatest($query)
    {
        return $query->orderBy($this->timeScopeColumn, 'desc');
    }

    public function scopeToday($query)
    {
        return $query->whereDate($this->timeScopeColumn, today());
    }

    public function scopeBetweenDates($query, string $from, string $to)
    {
        return $query->whereBetween($this->timeScopeColumn, [$from.' 00:00:00', $to.' 23:59:59']);
    }
}
