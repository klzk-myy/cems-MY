<?php

namespace App\Models\Traits;

use App\Models\Customer;
/**
 * @property int $customer_id
 */
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToCustomer
{
    public function initializeBelongsToCustomer(): void
    {
        $this->mergeFillable(['customer_id']);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function scopeForCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }
}
