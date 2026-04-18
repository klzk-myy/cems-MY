<?php

namespace App\Models;

use App\Enums\CtosStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CtosReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'ctos_number',
        'transaction_id',
        'branch_id',
        'customer_id',
        'customer_name',
        'id_type',
        'id_number_masked',
        'date_of_birth',
        'nationality',
        'amount_local',
        'amount_foreign',
        'currency_code',
        'transaction_type',
        'report_date',
        'status',
        'submitted_at',
        'submitted_by',
        'bnm_reference',
        'created_by',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'amount_local' => 'decimal:2',
        'amount_foreign' => 'decimal:4',
        'submitted_at' => 'datetime',
        'status' => CtosStatus::class,
    ];

    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id', 'code');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isDraft(): bool
    {
        return $this->status === CtosStatus::Draft;
    }

    public function isSubmitted(): bool
    {
        return $this->status === CtosStatus::Submitted;
    }

    public function markAsSubmitted(int $submittedBy, ?string $bnmReference = null): void
    {
        $this->update([
            'status' => CtosStatus::Submitted,
            'submitted_at' => now(),
            'submitted_by' => $submittedBy,
            'bnm_reference' => $bnmReference,
        ]);
    }
}
