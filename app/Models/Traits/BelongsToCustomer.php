<?php

namespace App\Models\Traits;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToCustomer
{
    public function initializeBelongsToCustomer(): void
    {
        $this->mergeFillable(['customer_id']);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function scopeForCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }
}
