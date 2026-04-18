<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerRelation extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'related_customer_id',
        'relation_type',
        'related_name',
        'id_type',
        'id_number_encrypted',
        'date_of_birth',
        'nationality',
        'address',
        'is_pep',
        'additional_info',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'is_pep' => 'boolean',
        'additional_info' => 'array',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function relatedCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'related_customer_id');
    }

    public function isPepRelation(): bool
    {
        return $this->is_pep;
    }
}
