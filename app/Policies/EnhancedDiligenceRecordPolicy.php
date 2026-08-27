<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\EnhancedDiligenceRecord;
use App\Models\User;

class EnhancedDiligenceRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Admin || $user->role === UserRole::ComplianceOfficer || $user->role === UserRole::Manager;
    }

    public function view(User $user, EnhancedDiligenceRecord $record): bool
    {
        return $user->role === UserRole::Admin || $user->role === UserRole::ComplianceOfficer || $user->role === UserRole::Manager;
    }

    public function update(User $user, EnhancedDiligenceRecord $record): bool
    {
        return $user->role === UserRole::Admin || $user->role === UserRole::ComplianceOfficer;
    }
}
