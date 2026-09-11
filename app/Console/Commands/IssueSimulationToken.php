<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Issue a Sanctum personal access token for a simulation role.
 *
 * Manual debugging tool: `simulation:run` mints its own tokens in-process,
 * so nothing reads the file written here. The plaintext token is printed
 * once and merged into storage/simulation/tokens.json for ad-hoc API
 * exploration against the seeded simulation data.
 */
class IssueSimulationToken extends Command
{
    protected $signature = 'simulation:issue-token
        {--email= : Email of the user to mint a token for}
        {--file=simulation/tokens.json : Where to merge the token (storage-relative)}';

    protected $description = 'Issue a Sanctum API token for the simulation harness (local/testing only)';

    public function handle(): int
    {
        if (! app()->environment('local', 'testing')) {
            $this->error('This command is only available in local/testing environments.');

            return Command::FAILURE;
        }

        $email = $this->option('email');
        if (! is_string($email) || $email === '') {
            $this->error('No --email provided.');

            return Command::FAILURE;
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("No user found with email [{$email}].");

            return Command::FAILURE;
        }

        $user->tokens()->delete();
        $token = $user->createToken('simulation')->plainTextToken;

        $this->storeToken($email, $token);
        $this->line($token);

        return Command::SUCCESS;
    }

    private function storeToken(string $email, string $token): void
    {
        $fileOption = (string) $this->option('file');

        // Treat the default/relative option as a storage-relative path so
        // the output location does not depend on the caller's CWD.
        $path = str_starts_with($fileOption, '/')
            ? $fileOption
            : storage_path($fileOption);
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        $tokens = [];
        if (file_exists($path)) {
            $tokens = json_decode((string) file_get_contents($path), true) ?: [];
        }

        $tokens[$email] = $token;
        file_put_contents($path, json_encode($tokens, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        chmod($path, 0600);
    }
}
