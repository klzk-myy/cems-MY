<?php

namespace App\Jobs;

use App\Models\SanctionList;
use App\Services\Compliance\SanctionsImportService;
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
        public ?string $listSlug = null,
    ) {}

    public function handle(SanctionsImportService $service): void
    {
        // Resolve list: explicit slug takes priority, then explicit model, then fallback
        $list = $this->resolveList();

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

    /**
     * Resolve the SanctionList to import.
     * Priority: listSlug > sanctionList > first active auto-updatable.
     * Slug is resolved lazily at job execution time to avoid eager DB queries at app boot.
     */
    protected function resolveList(): ?SanctionList
    {
        if ($this->listSlug !== null) {
            return SanctionList::where('slug', $this->listSlug)->first();
        }

        if ($this->sanctionList !== null) {
            return $this->sanctionList;
        }

        return SanctionList::active()->autoUpdatable()->first();
    }

    public function failed(\Throwable $exception): void
    {
        $listId = $this->sanctionList?->id ?? $this->listSlug;
        Log::critical('ImportSanctionsJob: Import failed permanently', [
            'list_id' => $listId,
            'error' => $exception->getMessage(),
        ]);
    }

    public function tags(): array
    {
        $listId = $this->sanctionList?->id ?? $this->listSlug ?? 'unknown';

        return [
            'sanctions',
            'sanctions-import',
            'list-'.$listId,
        ];
    }
}
