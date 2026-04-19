<?php

namespace App\Jobs\Sanctions;

use App\Jobs\Compliance\SanctionsRescreeningJob;
use App\Models\Alert;
use App\Models\SanctionList;
use App\Services\AuditService;
use App\Services\SanctionsDownloadService;
use App\Services\SanctionsImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

abstract class BaseSanctionsDownloadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 600; // 10 minutes

    public array $backoff = [60, 300, 600]; // 1min, 5min, 10min

    protected string $sourceKey;

    protected string $listType;

    protected string $format;

    abstract protected function getSourceKey(): string;

    abstract protected function getListType(): string;

    abstract protected function getFormat(): string;

    public function __construct()
    {
        $this->sourceKey = $this->getSourceKey();
        $this->listType = $this->getListType();
        $this->format = $this->getFormat();
    }

    public function handle(
        SanctionsDownloadService $downloadService,
        SanctionsImportService $importService,
        AuditService $auditService,
    ): void {
        $config = config("sanctions.sources.{$this->sourceKey}");

        if (! $config || ! ($config['enabled'] ?? false)) {
            Log::info("Sanctions source {$this->sourceKey} is disabled");

            return;
        }

        $url = $config['url'];
        if (empty($url)) {
            Log::warning("Sanctions source {$this->sourceKey} has no URL configured");

            return;
        }

        $list = $this->getOrCreateList($config);

        // Mark as pending
        $list->update([
            'last_attempted_at' => now(),
            'update_status' => 'pending',
        ]);

        $filename = "{$this->sourceKey}_".date('Y-m-d_His').'.'.strtolower($this->format);

        try {
            // Download file
            $result = $downloadService->download(
                $url,
                $filename,
                $this->format,
                $config['retry_attempts'] ?? 3
            );

            if (! $result['success']) {
                throw new \RuntimeException($result['error'] ?? 'Download failed');
            }

            // Check if content changed
            if ($list->last_checksum && $list->last_checksum === $result['checksum']) {
                Log::info("Sanctions list {$this->sourceKey} unchanged (same checksum)");

                $list->update([
                    'last_updated_at' => now(),
                    'update_status' => 'success',
                    'last_error_message' => null,
                ]);

                // Archive the file even if unchanged
                $downloadService->archiveFile($result['filepath'], $this->sourceKey);
                @unlink($result['filepath']);

                return;
            }

            // Import the data
            $importResult = match ($this->format) {
                'XML' => $importService->importFromXml($result['filepath'], $list->id, $this->listType),
                'JSON' => $importService->importFromJson($result['filepath'], $list->id),
                default => $importService->importFromCsv($result['filepath'], $list->id, true),
            };

            // Update list status
            $list->update([
                'last_updated_at' => now(),
                'update_status' => 'success',
                'last_error_message' => null,
                'last_checksum' => $result['checksum'],
                'auto_updated_by' => config('sanctions.system_user_id', 1),
            ]);

            // Archive the file
            $downloadService->archiveFile($result['filepath'], $this->sourceKey);
            @unlink($result['filepath']);

            // Trigger rescreening if new entries found
            if ($importResult['new_entries_detected'] > 0 && config('sanctions.rescreening.enabled', true)) {
                $this->dispatchRescreeningJob($importResult['new_entries_detected']);
            }

            Log::info("Sanctions list {$this->sourceKey} updated successfully", [
                'imported' => $importResult['imported'],
                'removed' => $importResult['removed'],
                'is_significant' => $importResult['is_significant_change'],
            ]);

        } catch (\Exception $e) {
            Log::error("Sanctions download job failed for {$this->sourceKey}", [
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            $list->update([
                'update_status' => 'failed',
                'last_error_message' => $e->getMessage(),
            ]);

            // Notify compliance if this was the final attempt
            if ($this->attempts() >= $this->tries) {
                $this->notifyFailure($e->getMessage());
            }

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error(static::class.' permanently failed', [
            'source' => $this->sourceKey,
            'exception' => $exception->getMessage(),
        ]);

        $this->notifyFailure($exception->getMessage());
    }

    protected function getOrCreateList(array $config): SanctionList
    {
        $list = SanctionList::where('list_type', $this->listType)
            ->where('name', $config['name'])
            ->first();

        if (! $list) {
            $list = SanctionList::create([
                'name' => $config['name'],
                'list_type' => $this->listType,
                'source_url' => $config['url'],
                'source_format' => $this->format,
                'uploaded_by' => config('sanctions.system_user_id', 1),
                'is_active' => true,
            ]);
        }

        return $list;
    }

    protected function dispatchRescreeningJob(int $newEntriesCount): void
    {
        // Dispatch rescreening job
        SanctionsRescreeningJob::dispatch();

        Log::info("Triggered customer rescreening due to {$newEntriesCount} new sanctions entries");
    }

    protected function notifyFailure(string $error): void
    {
        // Create system alert for compliance
        Alert::create([
            'type' => 'sanctions_update_failed',
            'severity' => 'high',
            'message' => "Sanctions list {$this->sourceKey} update failed: {$error}",
            'status' => 'open',
        ]);

        Log::alert('Sanctions update failure notification sent', [
            'source' => $this->sourceKey,
            'error' => $error,
        ]);
    }
}
