<?php

namespace App\Console\Commands;

use App\Models\SanctionList;
use App\Services\SanctionsImportService;
use Illuminate\Console\Command;

class SanctionsImportCommand extends Command
{
    protected $signature = 'sanctions:import
                            {--list= : Import from specific list by ID or name}
                            {--all : Import all active auto-updatable lists}';

    protected $description = 'Import sanctions data from external sources';

    public function __construct(
        protected SanctionsImportService $importService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $listId = $this->option('list');
        $importAll = $this->option('all');

        if ($listId) {
            return $this->importSingleList($listId);
        }

        if ($importAll) {
            return $this->importAllLists();
        }

        return $this->importDefaultList();
    }

    protected function importSingleList(string $listId): int
    {
        $list = is_numeric($listId)
            ? SanctionList::find($listId)
            : SanctionList::where('name', 'like', "%{$listId}%")->first();

        if (! $list) {
            $this->error("Sanctions list not found: {$listId}");

            return Command::FAILURE;
        }

        if (! $list->source_url) {
            $this->error("List '{$list->name}' has no source URL configured.");

            return Command::FAILURE;
        }

        $this->info("Importing from list: {$list->name}");

        return $this->doImport($list);
    }

    protected function importAllLists(): int
    {
        $lists = SanctionList::active()->autoUpdatable()->get();

        if ($lists->isEmpty()) {
            $this->warn('No active auto-updatable sanctions lists found.');

            return Command::SUCCESS;
        }

        $this->info("Importing {$lists->count()} list(s)...");

        $failed = 0;
        foreach ($lists as $list) {
            $this->newLine();
            $this->info("Processing: {$list->name}");

            if ($this->doImport($list) !== Command::SUCCESS) {
                $failed++;
            }
        }

        $this->newLine();
        if ($failed === 0) {
            $this->info('All imports completed successfully.');

            return Command::SUCCESS;
        }

        $this->warn("{$failed} import(s) failed.");

        return Command::FAILURE;
    }

    protected function importDefaultList(): int
    {
        $list = SanctionList::active()->autoUpdatable()->first();

        if (! $list) {
            $this->warn('No active auto-updatable sanctions list found.');
            $this->line('Use --list= to specify a list or --all to import all lists.');

            return Command::FAILURE;
        }

        $this->info("Importing from default list: {$list->name}");

        return $this->doImport($list);
    }

    protected function doImport(SanctionList $list): int
    {
        $this->line("  Source: {$list->source_url}");

        try {
            $startTime = microtime(true);

            $result = $this->importService->import($list, true);

            $duration = round(microtime(true) - $startTime, 2);

            $this->newLine();
            $this->info('Import completed in '.$duration.'s');
            $this->line("  Created: {$result['created']}");
            $this->line("  Updated: {$result['updated']}");
            $this->line("  Deactivated: {$result['deactivated']}");

            if ($result['errors'] > 0) {
                $this->warn("  Errors: {$result['errors']}");
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->newLine();
            $this->error('Import failed: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
