<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FlaggedTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'flag_type',
        'flag_reason',
        'status',
        'assigned_to',
        'reviewed_by',
        'notes',
        'resolved_at',
        'customer_id',
    ];

    protected $casts = [
        'flag_type' => \App\Enums\ComplianceFlagType::class,
        'status' => \App\Enums\FlagStatus::class,
        'resolved_at' => 'datetime',
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
