<?php

namespace App\Models\Traits;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait HasApprover
{
    public function initializeHasApprover(): void
    {
        $this->mergeFillable(['approved_by', 'approved_at']);
        $this->mergeCasts(['approved_at' => 'datetime']);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function approve(User $user, ?Carbon $at = null): bool
    {
        return $this->update([
            'approved_by' => $user->id,
            'approved_at' => $at ?? now(),
        ]);
    }
}
