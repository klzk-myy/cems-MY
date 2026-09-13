<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'username' => 'admin',
                'email' => 'admin@cems.my',
                'password' => 'Password123!',
                'role' => 'admin',
            ],
            [
                'username' => 'teller1',
                'email' => 'teller1@cems.my',
                'password' => 'Password123!',
                'role' => 'teller',
            ],
            [
                'username' => 'manager1',
                'email' => 'manager1@cems.my',
                'password' => 'Password123!',
                'role' => 'manager',
            ],
            [
                'username' => 'compliance1',
                'email' => 'compliance1@cems.my',
                'password' => 'Password123!',
                'role' => 'compliance_officer',
            ],
        ];

        foreach ($users as $userData) {
            $user = User::firstOrNew(['email' => $userData['email']]);

            if (! $user->exists) {
                $user->fill([
                    'username' => $userData['username'],
                    'mfa_enabled' => false,
                    'is_active' => true,
                ]);
                $user->role = UserRole::from($userData['role']);
                $user->password_hash = Hash::make($userData['password']); // Directly set hashed password
                $user->save();
            }
        }

        $this->command->info('Created users:');
        $this->command->info('  - admin@cems.my (Admin) - Password: Password123!');
        $this->command->info('  - teller1@cems.my (Teller) - Password: Password123!');
        $this->command->info('  - manager1@cems.my (Manager) - Password: Password123!');
        $this->command->info('  - compliance1@cems.my (Compliance Officer) - Password: Password123!');
    }
}
