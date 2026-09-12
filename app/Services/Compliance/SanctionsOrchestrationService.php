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

    /**
     * Sync a list through the canonical import path (delta when possible,
     * full download otherwise). URL validation stays here as the first gate
     * so a misconfigured list fails before any network work.
     */
    public function syncSanctionsList(SanctionList $list, bool $manual = false): array
    {
        try {
            $this->validateUrl($list->source_url);

            $result = $this->importService->import($list, $manual);

            return array_merge($result, ['success' => true]);
        } catch (\Exception $e) {
            Log::error('Sanctions orchestration: sync failed', [
                'list_id' => $list->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'created' => 0,
                'updated' => 0,
                'deactivated' => 0,
            ];
        }
    }
}
