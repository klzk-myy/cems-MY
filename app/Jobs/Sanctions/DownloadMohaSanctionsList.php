<?php

namespace App\Jobs\Sanctions;

use App\Services\AuditService;
use App\Services\SanctionsDownloadService;
use App\Services\SanctionsImportService;
use Illuminate\Support\Facades\Log;

class DownloadMohaSanctionsList extends BaseSanctionsDownloadJob
{
    protected function getSourceKey(): string
    {
        return 'moha';
    }

    protected function getListType(): string
    {
        return 'MOHA';
    }

    protected function getFormat(): string
    {
        return 'CSV';
    }

    public function handle(
        SanctionsDownloadService $downloadService,
        SanctionsImportService $importService,
        AuditService $auditService,
    ): void {
        // MOHA does not provide automated download
        Log::info('MOHA sanctions list requires manual import - skipping automated download');

        // Still update the last attempted timestamp
        $list = $this->getOrCreateList(config('sanctions.sources.moha'));
        $list->update([
            'last_attempted_at' => now(),
            'update_status' => 'never_run',
            'last_error_message' => 'Automated download not available - manual import required',
        ]);
    }
}
