<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ThresholdAudit extends Model
{
    use HasFactory;

    protected $fillable = [
        'category',
        'key',
        'old_value',
        'new_value',
        'changed_by',
        'change_reason',
        'changed_at',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeKey($query, string $key)
    {
        return $query->where('key', $key);
    }
}
