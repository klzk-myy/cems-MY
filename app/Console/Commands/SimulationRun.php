<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Run the CEMS-MY simulation harness.
 *
 * The harness is a PHPUnit Feature suite, so it drives the application HTTP
 * kernel in-process rather than against a separate `php artisan serve`
 * instance. Every workflow step is issued through the application's HTTP
 * kernel (web routes via session + CSRF, API v1 routes via Sanctum + JSON).
 * No service, model, DB::, or tinker call is made from the harness — state is
 * read only from HTTP responses or a separate read-only PDO connection used
 * purely as an oracle.
 *
 * The harness builds and seeds its own in-memory database (RefreshDatabase)
 * and mints its own Sanctum tokens, so this command only validates the
 * options, spawns phpunit, and writes a JSON report to storage/simulation/.
 */
class SimulationRun extends Command
{
    protected $signature = 'simulation:run
        {--wave=A : Which wave to run (A, B, or C)}
        {--surface=web : Which surface to drive (web, api, or both)}
        {--report= : Path for the JSON report (default storage/app/simulation/run.json)}';

    protected $description = 'Run the CEMS-MY simulation harness (HTTP consumer)';

    public function handle(): int
    {
        if (! app()->environment('local', 'testing')) {
            $this->error('simulation:run is only available in local/testing environments.');

            return Command::FAILURE;
        }

        $wave = strtoupper((string) $this->option('wave'));
        $surface = strtolower((string) $this->option('surface'));
        $report = $this->option('report') ?? storage_path('simulation/run.json');

        if (! in_array($wave, ['A', 'B', 'C'], true)) {
            $this->error("Invalid wave [{$wave}]. Use A, B, or C.");

            return Command::FAILURE;
        }
        if (! in_array($surface, ['web', 'api', 'both'], true)) {
            $this->error("Invalid surface [{$surface}]. Use web, api, or both.");

            return Command::FAILURE;
        }

        $this->info('=== CEMS-MY Simulation Harness ===');
        $this->info("Wave: {$wave} | Surface: {$surface}");

        // The surface option reaches the in-process harness through the
        // SIM_SURFACE environment variable (read by SimulationTestCase).
        // putenv() is inherited by the phpunit child process; the previous
        // value is restored afterwards so the caller's shell is untouched.
        $previousSurface = getenv('SIM_SURFACE');
        putenv("SIM_SURFACE={$surface}");

        $this->info("Dispatching wave {$wave} over surface {$surface}...");
        try {
            $exit = $this->runPhpunit($wave);
        } finally {
            if ($previousSurface === false) {
                putenv('SIM_SURFACE');
            } else {
                putenv("SIM_SURFACE={$previousSurface}");
            }
        }

        $reportDir = dirname($report);
        if (! is_dir($reportDir)) {
            mkdir($reportDir, 0755, true);
        }
        file_put_contents($report, json_encode([
            'wave' => $wave,
            'surface' => $surface,
            'report_path' => $report,
            'exit_code' => $exit,
            'generated_at' => now()->toIso8601String(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->info("Report written to {$report}");

        return $exit === 0 ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * Run the simulation test suite via phpunit directly.
     *
     * The `artisan test` command (nunomaduro/collision) forwards raw argv to
     * phpunit, so options like --filter cannot be passed through `$this->call`.
     * Exec vendor/bin/phpunit with --filter=Simulation instead. The harness
     * mints its own tokens in-process, so no SIM_TOKEN_* env plumbing is
     * needed here.
     *
     * The wave option maps to a PHPUnit group (wave-a/wave-b/wave-c) carried
     * by `#[Group]` attributes on the wave test classes, so each wave runs
     * exactly its own suite instead of the whole Simulation filter.
     */
    private function runPhpunit(string $wave): int
    {
        $phpunit = base_path('vendor/bin/phpunit');
        $group = 'wave-'.strtolower($wave);
        $cmd = escapeshellarg($phpunit).' --filter=Simulation --group='.escapeshellarg($group).' --testdox 2>&1';
        $descriptorspec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open($cmd, $descriptorspec, $pipes, base_path());
        if (! is_resource($process)) {
            $this->error('Failed to spawn phpunit.');

            return 1;
        }
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        $this->output->write($stdout.$stderr);

        return $exitCode;
    }
}
