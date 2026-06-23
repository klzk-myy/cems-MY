<?php

namespace App\Models\Traits;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait HasClosedBy
{
    public function initializeHasClosedBy(): void
    {
        $this->mergeFillable(['closed_by', 'closed_at']);
        $this->mergeCasts(['closed_at' => 'datetime']);
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function close(User $user, ?Carbon $at = null): bool
    {
        return $this->update([
            'closed_by' => $user->id,
            'closed_at' => $at ?? now(),
        ]);
    }
}
