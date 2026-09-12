<?php

namespace App\Services\Compliance;

use App\Models\SanctionList;
use App\Services\Concerns\ValidatesSanctionsUrl;
use Illuminate\Support\Facades\Log;

class SanctionsOrchestrationService
{
    use ValidatesSanctionsUrl;

    public function __construct(
        protected SanctionsDownloadService $downloadService,
        protected SanctionsImportService $importService,
    ) {}

    public function syncSanctionsList(SanctionList $list, bool $manual = false): array
    {
        $this->validateUrl($list->source_url);

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

        if (! is_readable($downloadResult['filepath'])) {
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

        // Stream entries so large lists (OFAC SDN ~80MB JSONL) stay memory-bounded.
        // Archive runs in finally so a failed import still preserves the source
        // artifact for audit; the temp file is removed once consumed.
        try {
            $result = $this->importService->importWithData(
                $list,
                $this->importService->streamSourceFile($downloadResult['filepath']),
                $manual
            );
        } finally {
            if ($downloadResult['filepath'] && file_exists($downloadResult['filepath'])) {
                $this->downloadService->archiveFile($downloadResult['filepath'], $list->list_type->value ?? 'unknown');
                unlink($downloadResult['filepath']);
            }
        }

        return array_merge($result, ['success' => true]);
    }
}
