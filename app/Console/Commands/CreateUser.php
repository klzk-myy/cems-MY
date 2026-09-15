<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\PasswordHistory;
use App\Models\User;
use App\Rules\PasswordRules;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreateUser extends Command
{
    protected $signature = 'user:create
        {--name= : Username}
        {--email= : Email address}
        {--password= : Password}
        {--role=teller : Role (teller, manager, compliance_officer, admin)}';

    protected $description = 'Create a new user with specified role';

    public function handle(): int
    {
        $name = $this->option('name') ?? $this->ask('Enter username');
        $email = $this->option('email') ?? $this->ask('Enter email address');
        $password = $this->option('password') ?? $this->secret('Enter password');
        $role = $this->option('role') ?? $this->choice(
            'Select role',
            [
                UserRole::Teller->value,
                UserRole::Manager->value,
                UserRole::ComplianceOfficer->value,
                UserRole::Admin->value,
            ],
            UserRole::Teller->value
        );

        // Validate role
        if (! is_string($role)) {
            $this->error('Invalid role provided.');

            return 1;
        }

        try {
            $roleEnum = UserRole::from($role);
        } catch (\ValueError) {
            $this->error("Invalid role: {$role}");

            return 1;
        }

        // Check if email exists
        if (User::where('email', $email)->exists()) {
            $this->error("User with email {$email} already exists!");

            return 1;
        }

        // Enforce the same password policy the web forms apply.
        $validator = Validator::make(
            ['password' => $password],
            ['password' => PasswordRules::forNew(confirmed: false)],
        );

        if ($validator->fails()) {
            $this->error($validator->errors()->first('password'));

            return 1;
        }

        // Pass the plain password so the mutator hashes it once and stamps
        // password_changed_at. Setting password_hash directly is not an
        // option here: it is not fillable, and the model's creating hook
        // rejects any user whose hash is still empty.
        $user = User::create([
            'username' => $name,
            'email' => $email,
            'password' => $password,
            'is_active' => true,
        ]);

        $user->role = $roleEnum;
        $user->mfa_enabled = false;
        $user->save();

        PasswordHistory::record($user->id, $user->password_hash);

        $this->info('User created successfully!');
        $this->info(" ID: {$user->id}");
        $this->info(" Username: {$user->username}");
        $this->info(" Email: {$user->email}");
        $this->info(" Role: {$user->role->label()}");
        $this->info('');
        $this->info('Permissions:');
        $this->info(' - Admin: '.($user->isAdmin() ? 'Yes' : 'No'));
        $this->info(' - Manager: '.($user->isManager() ? 'Yes' : 'No'));
        $this->info(' - Compliance Officer: '.($user->isComplianceOfficer() ? 'Yes' : 'No'));

        return 0;
    }
}
