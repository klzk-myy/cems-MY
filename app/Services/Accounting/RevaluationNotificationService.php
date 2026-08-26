<?php

namespace App\Services\Accounting;

use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\RevaluationCompleteNotification;
use App\Services\System\NotificationDispatcher;

class RevaluationNotificationService
{
    public function sendRevaluationNotification(array $results): void
    {
        $recipients = $this->getNotificationRecipients();

        NotificationDispatcher::dispatchToAll(
            collect($recipients),
            new RevaluationCompleteNotification($results),
            ['mail']
        );
    }

    protected function getNotificationRecipients(): array
    {
        return User::where('is_active', true)
            ->whereIn('role', [
                UserRole::Manager->value,
                UserRole::ComplianceOfficer->value,
                UserRole::Admin->value,
            ])
            ->select(['id', 'name', 'email', 'role'])
            ->get()
            ->toArray();
    }
}
