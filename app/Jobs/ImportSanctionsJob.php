<?php

namespace App\Jobs;

use App\Models\SanctionList;
use App\Services\SanctionsImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ImportSanctionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 600;

    public array $backoff = [30, 60, 120];

    public function __construct(
        public ?SanctionList $sanctionList = null,
    ) {}

    public function handle(SanctionsImportService $service): void
    {
        $list = $this->sanctionList ?? SanctionList::active()->autoUpdatable()->first();

        if (! $list) {
            Log::warning('ImportSanctionsJob: No active auto-updatable sanctions list found');

            return;
        }

        Log::info('ImportSanctionsJob: Starting import', [
            'list_id' => $list->id,
            'list_name' => $list->name,
        ]);

        $service->import($list, false);

        Log::info('ImportSanctionsJob: Import completed', [
            'list_id' => $list->id,
            'list_name' => $list->name,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::critical('ImportSanctionsJob: Import failed permanently', [
            'list_id' => $this->sanctionList?->id,
            'error' => $exception->getMessage(),
        ]);
    }

    public function tags(): array
    {
        return [
            'sanctions',
            'sanctions-import',
            'list-'.$this->sanctionList?->id,
        ];
    }
}
