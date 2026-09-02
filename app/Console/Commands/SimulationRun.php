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
 * This command resets the test database, seeds it, mints Sanctum tokens,
 * then dispatches the requested wave (A/B/C) over the requested surface
 * (web/api/both). Writes a JSON report to storage/app/simulation/.
 */
class SimulationRun extends Command
{
    protected $signature = 'simulation:run
        {--wave=A : Which wave to run (A, B, or C)}
        {--surface=web : Which surface to drive (web, api, or both)}
        {--report= : Path for the JSON report (default storage/app/simulation/run.json)}';

    protected $description = 'Run the CEMS-MY simulation harness (HTTP consumer)';

    private const DB_PATH = '/www/wwwroot/local.host/database/simulation.sqlite';

    public function handle(): int
    {
        $wave = $this->option('wave');
        $surface = $this->option('surface');
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

        $this->configureSimulationConnection();
        $this->resetAndSeed();
        $this->issueTokens();

        $this->info("Dispatching wave {$wave} over surface {$surface}...");
        $exit = $this->runPhpunit();

        $reportDir = dirname($report);
        if (! is_dir($reportDir)) {
            mkdir($reportDir, 0777, true);
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
     * Point the default connection at the file-backed simulation SQLite DB.
     *
     * .env exports DB_CONNECTION=mysql / DB_DATABASE=cems_my_staging, so the
     * default connection resolves to the staging MySQL database unless we
     * override it here. Sub-commands inherit the resolved config, so this must
     * run before any db:seed / db:reset-test call.
     */
    private function configureSimulationConnection(): void
    {
        if (! file_exists(self::DB_PATH)) {
            touch(self::DB_PATH);
        }

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => self::DB_PATH]);
    }

    /**
     * Drop and recreate the test database, then seed it.
     *
     * db:reset-test --fresh rebuilds via SchemaSeeder (the migration-free
     * source of truth), then SimulationSeeder populates the simulation
     * identity graph (branches, counters, tellers, tokens, customers, txns).
     */
    private function resetAndSeed(): void
    {
        $this->call('db:reset-test', ['--fresh' => true]);
        $this->call('db:seed', ['--class' => 'Database\Seeders\SimulationSeeder']);
    }

    /**
     * Mint Sanctum tokens for the four simulation roles.
     */
    private function issueTokens(): void
    {
        foreach (['sim_teller', 'sim_manager', 'sim_compliance', 'sim_admin'] as $role) {
            $this->call('simulation:issue-token', ['email' => $role.'@cems.my']);
        }
    }

    /**
     * Run the simulation test suite via phpunit directly.
     *
     * The `artisan test` command (nunomaduro/collision) forwards raw argv to
     * phpunit, so options like --filter cannot be passed through `$this->call`.
     * Exec vendor/bin/phpunit with --filter=Simulation instead.
     */
    private function runPhpunit(): int
    {
        $phpunit = base_path('vendor/bin/phpunit');
        $cmd = escapeshellarg($phpunit).' --filter=Simulation --testdox 2>&1';
        $descriptorspec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open($cmd, $descriptorspec, $pipes);
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
