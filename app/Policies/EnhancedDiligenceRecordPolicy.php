<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Compliance\EnhancedDiligenceRecord;
use App\Models\User;

class EnhancedDiligenceRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->canPerform(Permission::AccessCompliance);
    }

    public function view(User $user, EnhancedDiligenceRecord $record): bool
    {
        return $user->role->canPerform(Permission::AccessCompliance);
    }

    public function update(User $user, EnhancedDiligenceRecord $record): bool
    {
        return $user->role->canPerform(Permission::AccessCompliance);
    }
}
