<?php

namespace App\Services\Accounting;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class RevaluationNotificationService
{
    /**
     * Send revaluation completion notification to recipients.
     *
     * Sends email notifications to all active users with revaluation
     * summary information. Attachments are included if a report path
     * is provided in the results.
     *
     * @param  array  $results  Revaluation results array containing:
     *                          - date: string Revaluation date
     *                          - positions_updated: int Number of positions updated
     *                          - net_pnl: string Net profit/loss
     *                          - report_path: string|null Path to report file
     */
    public function sendRevaluationNotification(array $results): void
    {
        $recipients = $this->getNotificationRecipients();

        foreach ($recipients as $recipient) {
            try {
                Mail::raw("Revaluation Complete\n\nDate: {$results['date']}\nPositions Updated: {$results['positions_updated']}\nNet P&L: {$results['net_pnl']}", function ($message) use ($recipient, $results) {
                    $message->to($recipient['email'])
                        ->subject('Monthly Revaluation Complete - '.now()->format('F Y'));

                    if (! empty($results['report_path'])) {
                        $message->attach($results['report_path']);
                    }
                });
            } catch (\Exception $e) {
                Log::error('Failed to send revaluation notification', [
                    'recipient' => $recipient['email'],
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Get list of users to notify about revaluation completion.
     *
     * Retrieves only authorized users (managers, accountants, compliance officers)
     * who should receive sensitive financial P&L data. Regular tellers are excluded.
     *
     * @return array Array of user data arrays with email addresses
     */
    protected function getNotificationRecipients(): array
    {
        // Only managers, accountants, and compliance officers should receive P&L data
        return User::where('is_active', true)
            ->whereIn('role', [
                UserRole::Manager->value,
                UserRole::ComplianceOfficer->value,
                UserRole::Admin->value,
            ])
            ->get()
            ->toArray();
    }
}
