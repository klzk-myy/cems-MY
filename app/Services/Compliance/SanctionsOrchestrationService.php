<?php

namespace App\Services\Compliance;

use App\Models\SanctionList;
use Illuminate\Support\Facades\Log;

class SanctionsOrchestrationService
{
    public function __construct(
        protected SanctionsDownloadService $downloadService,
        protected SanctionsImportService $importService,
    ) {}

    public function syncSanctionsList(SanctionList $list, bool $manual = false): array
    {
        $downloadResult = $this->downloadService->download(
            $list->source_url,
            $list->slug.'_'.time().'.json',
            $list->source_format ?? 'JSON',
            3
        );

        if (! $downloadResult['success']) {
            Log::error('Sanctions orchestration: download failed', [
                'list_id' => $list->id,
                'error' => $downloadResult['error'],
            ]);

            return [
                'success' => false,
                'error' => $downloadResult['error'] ?? 'Download failed',
                'created' => 0,
                'updated' => 0,
                'deactivated' => 0,
            ];
        }

        $content = file_get_contents($downloadResult['filepath']);

        if ($content === false) {
            Log::error('Sanctions orchestration: failed to read downloaded file', [
                'list_id' => $list->id,
                'filepath' => $downloadResult['filepath'],
            ]);

            return [
                'success' => false,
                'error' => 'Failed to read downloaded file',
                'created' => 0,
                'updated' => 0,
                'deactivated' => 0,
            ];
        }

        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('Sanctions orchestration: invalid JSON in downloaded file', [
                'list_id' => $list->id,
                'json_error' => json_last_error_msg(),
            ]);

            return [
                'success' => false,
                'error' => 'Invalid JSON in downloaded file',
                'created' => 0,
                'updated' => 0,
                'deactivated' => 0,
            ];
        }

        $result = $this->importService->importWithData($list, $data, $manual);

        if ($downloadResult['filepath'] && file_exists($downloadResult['filepath'])) {
            $this->downloadService->archiveFile($downloadResult['filepath'], $list->list_type->value ?? 'unknown');
        }

        return array_merge($result, ['success' => true]);
    }
}
