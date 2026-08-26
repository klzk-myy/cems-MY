<?php

namespace App\Console\Commands;

use App\Services\AdverseMediaImportService;
use Illuminate\Console\Command;

class AdverseMediaImportCommand extends Command
{
    protected $signature = 'adverse-media:import
                            {file? : Path to CSV or JSON file to import}
                            {--stdin : Read the CSV/JSON payload from STDIN}';

    protected $description = 'Import adverse media entries from a CSV/JSON file or STDIN (upsert by name+source+url hash)';

    public function __construct(
        protected AdverseMediaImportService $importService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $file = (string) $this->argument('file');
        $useStdin = (bool) $this->option('stdin');

        if ($file === '' && ! $useStdin) {
            $this->error('Provide a file path argument or pass --stdin to pipe data.');

            return Command::FAILURE;
        }

        try {
            if ($useStdin) {
                $result = $this->importService->importFromContents(
                    (string) file_get_contents('php://stdin')
                );
            } else {
                if (! is_file($file)) {
                    $this->error("File not found: {$file}");

                    return Command::FAILURE;
                }

                $result = $this->importService->importFromFile($file);
            }
        } catch (\Exception $e) {
            $this->error('Import failed: '.$e->getMessage());

            return Command::FAILURE;
        }

        $this->info("Import {$result['status']}.");
        $this->line("  Added: {$result['added']}");
        $this->line("  Updated: {$result['updated']}");
        $this->line("  Skipped: {$result['skipped']}");

        return $result['status'] === 'failed' ? Command::FAILURE : Command::SUCCESS;
    }
}
