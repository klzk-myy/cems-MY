<?php

namespace App\Models\Traits;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait HasApprover
{
    public function initializeHasApprover(): void
    {
        $this->mergeCasts(['approved_at' => 'datetime']);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function approve(User $user, ?Carbon $at = null): bool
    {
        $this->approved_by = $user->id;
        $this->approved_at = $at ?? now();

        return $this->save();
    }
}
