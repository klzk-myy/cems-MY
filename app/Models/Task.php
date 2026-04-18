<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'category',
        'priority',
        'status',
        'assigned_to',
        'assigned_role',
        'created_by',
        'completed_by',
        'related_transaction_id',
        'related_customer_id',
        'due_at',
        'acknowledged_at',
        'completed_at',
        'notes',
        'completion_notes',
        'is_recurring',
        'recurring_pattern',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'completed_at' => 'datetime',
        'is_recurring' => 'boolean',
    ];

    public const CATEGORY_COMPLIANCE = 'Compliance';

    public const CATEGORY_CUSTOMER = 'Customer';

    public const CATEGORY_OPERATIONS = 'Operations';

    public const CATEGORY_ADMIN = 'Admin';

    public const CATEGORY_APPROVAL = 'Approval';

    public const PRIORITY_URGENT = 'Urgent';

    public const PRIORITY_HIGH = 'High';

    public const PRIORITY_MEDIUM = 'Medium';

    public const PRIORITY_LOW = 'Low';

    public const STATUS_PENDING = 'Pending';

    public const STATUS_IN_PROGRESS = 'InProgress';

    public const STATUS_COMPLETED = 'Completed';

    public const STATUS_CANCELLED = 'Cancelled';

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function relatedTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'related_transaction_id');
    }

    public function relatedCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'related_customer_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', self::STATUS_IN_PROGRESS);
    }

    public function scopeOverdue($query)
    {
        return $query->whereNotIn('status', [self::STATUS_COMPLETED, self::STATUS_CANCELLED])
            ->where('due_at', '<', now());
    }

    public function scopeAssignedTo($query, $userId)
    {
        return $query->where('assigned_to', $userId);
    }

    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    public function isOverdue(): bool
    {
        return ! in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_CANCELLED])
            && $this->due_at
            && $this->due_at->isPast();
    }

    public function acknowledge(): void
    {
        $this->update([
            'acknowledged_at' => now(),
            'status' => self::STATUS_IN_PROGRESS,
        ]);
    }

    public function complete(?string $notes = null): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now(),
            'completed_by' => auth()->id(),
            'completion_notes' => $notes,
        ]);
    }

    public function escalate(): void
    {
        $escalationMap = [
            self::PRIORITY_URGENT => self::PRIORITY_HIGH,
            self::PRIORITY_HIGH => self::PRIORITY_MEDIUM,
            self::PRIORITY_MEDIUM => self::PRIORITY_LOW,
            self::PRIORITY_LOW => self::PRIORITY_LOW,
        ];

        $this->update([
            'priority' => $escalationMap[$this->priority] ?? $this->priority,
        ]);
    }
}
