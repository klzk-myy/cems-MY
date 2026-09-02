<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Issue a plaintext Sanctum personal access token for simulation use.
 *
 * Tokens are only minted in local/testing environments. The plaintext token
 * is printed once and never stored — the harness uses it to authenticate the
 * API v1 surface client.
 */
class IssueSimulationToken extends Command
{
    protected $signature = 'simulation:issue-token {email : Email of the user to mint a token for}';

    protected $description = 'Issue a Sanctum API token for the simulation harness (local/testing only)';

    public function handle(): int
    {
        if (! app()->environment('local', 'testing')) {
            $this->error('This command is only available in local/testing environments.');

            return Command::FAILURE;
        }

        $email = $this->argument('email');
        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("No user found with email [{$email}].");

            return Command::FAILURE;
        }

        $user->tokens()->delete();
        $token = $user->createToken('simulation')->plainTextToken;

        $this->line($token);

        return Command::SUCCESS;
    }
}
