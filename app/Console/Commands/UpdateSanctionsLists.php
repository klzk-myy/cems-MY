<?php

namespace App\Console\Commands;

use App\Jobs\Sanctions\DownloadEuSanctionsList;
use App\Jobs\Sanctions\DownloadMohaSanctionsList;
use App\Jobs\Sanctions\DownloadOfacSanctionsList;
use App\Jobs\Sanctions\DownloadUnSanctionsList;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;

class UpdateSanctionsLists extends Command
{
    protected $signature = 'sanctions:update
                            {--source= : Update specific source (un, ofac, moha, eu)}
                            {--sync : Run synchronously instead of dispatching to queue}';

    protected $description = 'Download and update sanctions lists from configured sources';

    protected array $sourceJobs = [
        'un' => DownloadUnSanctionsList::class,
        'ofac' => DownloadOfacSanctionsList::class,
        'moha' => DownloadMohaSanctionsList::class,
        'eu' => DownloadEuSanctionsList::class,
    ];

    public function handle(): int
    {
        $source = $this->option('source');
        $sync = $this->option('sync');

        if ($source) {
            return $this->updateSingleSource($source, $sync);
        }

        return $this->updateAllSources($sync);
    }

    protected function updateSingleSource(string $source, bool $sync): int
    {
        if (! isset($this->sourceJobs[$source])) {
            $this->error("Invalid source: {$source}");
            $this->line('Valid sources: '.implode(', ', array_keys($this->sourceJobs)));

            return Command::FAILURE;
        }

        $jobClass = $this->sourceJobs[$source];

        $this->info("Dispatching {$source} sanctions download job...");

        if ($sync) {
            $this->info('Running synchronously...');
            Bus::dispatchSync(new $jobClass);
        } else {
            Bus::dispatch(new $jobClass);
        }

        $this->info("Job dispatched for {$source}.");
        $this->line('Run "php artisan sanctions:status" to check status.');

        return Command::SUCCESS;
    }

    protected function updateAllSources(bool $sync): int
    {
        $this->info('Dispatching sanctions list update jobs...');
        $this->newLine();

        foreach ($this->sourceJobs as $key => $jobClass) {
            $config = config("sanctions.sources.{$key}");

            if (! $config || ! ($config['enabled'] ?? false)) {
                $this->warn("  [SKIP] {$key}: Disabled in configuration");

                continue;
            }

            if (empty($config['url'])) {
                $this->warn("  [SKIP] {$key}: No URL configured");

                continue;
            }

            if ($sync) {
                $this->line("  [SYNC] {$key}: Running...");
                try {
                    Bus::dispatchSync(new $jobClass);
                    $this->info("  [DONE] {$key}: Completed");
                } catch (\Exception $e) {
                    $this->error("  [FAIL] {$key}: {$e->getMessage()}");
                }
            } else {
                Bus::dispatch(new $jobClass);
                $this->info("  [QUEUE] {$key}: Dispatched");
            }
        }

        $this->newLine();
        $this->info('All enabled sanctions update jobs dispatched.');
        $this->line('Run "php artisan sanctions:status" to check status.');
        $this->line('Check "storage/logs/laravel.log" for detailed progress.');

        return Command::SUCCESS;
    }
}
