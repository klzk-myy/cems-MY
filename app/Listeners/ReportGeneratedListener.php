<?php

namespace App\Listeners;

use App\Events\ReportGenerated;
use App\Notifications\ReportEmailNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

class ReportGeneratedListener
{
    public function __invoke(ReportGenerated $event): void
    {
        Log::info('Report generated', [
            'report_run_id' => $event->reportRun->id,
            'report_type' => $event->reportRun->report_type ?? null,
        ]);

        $this->deliverToRecipients($event);
    }

    /**
     * Email the generated file to the schedule's notification_recipients.
     * Ad-hoc runs carry no schedule, so nothing to deliver. Failures are
     * logged, not thrown — delivery must not fail the completed run.
     */
    protected function deliverToRecipients(ReportGenerated $event): void
    {
        $run = $event->reportRun;

        if ($run->schedule_id === null || $run->schedule === null) {
            return;
        }

        $recipients = array_values(array_filter((array) $run->schedule->notification_recipients));

        if ($recipients === [] || $run->file_path === null || ! Storage::exists($run->file_path)) {
            return;
        }

        try {
            $notification = new ReportEmailNotification(
                subject: 'Report ready: '.$run->report_type->value,
                filePath: Storage::path($run->file_path),
            );

            foreach ($recipients as $email) {
                Notification::route('mail', $email)->notify($notification);
            }

            $run->forceFill(['emailed_at' => now()])->save();
        } catch (\Throwable $e) {
            Log::error('Failed to email generated report', [
                'report_run_id' => $run->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
