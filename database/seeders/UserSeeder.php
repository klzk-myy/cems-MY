<?php

namespace Database\Seeders;

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
                'password' => 'Admin@123456',
                'role' => 'admin',
            ],
            [
                'username' => 'teller1',
                'email' => 'teller1@cems.my',
                'password' => 'Teller@1234',
                'role' => 'teller',
            ],
            [
                'username' => 'manager1',
                'email' => 'manager1@cems.my',
                'password' => 'Manager@1234',
                'role' => 'manager',
            ],
            [
                'username' => 'compliance1',
                'email' => 'compliance1@cems.my',
                'password' => 'Compliance@1234',
                'role' => 'compliance_officer',
            ],
        ];

        foreach ($users as $userData) {
            User::firstOrCreate(
                ['email' => $userData['email']],
                [
                    'username' => $userData['username'],
                    'password_hash' => Hash::make($userData['password']), // Directly set hashed password
                    'role' => $userData['role'],
                    'mfa_enabled' => false,
                    'is_active' => true,
                ]
            );
        }

        $this->command->info('Created users:');
        $this->command->info('  - admin@cems.my (Admin) - Password: Admin@123456');
        $this->command->info('  - teller1@cems.my (Teller) - Password: Teller@1234');
        $this->command->info('  - manager1@cems.my (Manager) - Password: Manager@1234');
        $this->command->info('  - compliance1@cems.my (Compliance Officer) - Password: Compliance@1234');
    }
}
