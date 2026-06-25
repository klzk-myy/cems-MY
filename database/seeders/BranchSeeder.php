<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BranchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create default head office branch
        DB::table('branches')->updateOrInsert(
            ['code' => 'HQ'],
            [
                'name' => 'Head Office',
                'type' => 'head_office',
                'address' => 'Level 10, Menara Multi-Purpose',
                'city' => 'Kuala Lumpur',
                'state' => 'Wilayah Persekutuan',
                'postal_code' => '50250',
                'country' => 'Malaysia',
                'phone' => '+60 3-1234 5678',
                'email' => 'hq@cems.my',
                'is_active' => true,
                'is_main' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Create default branches
        $branches = [
            [
                'code' => 'BR001',
                'name' => 'Kuala Lumpur Branch',
                'city' => 'Kuala Lumpur',
                'state' => 'Wilayah Persekutuan',
                'postal_code' => '50100',
                'address' => 'Ground Floor, Wisma Conlay',
                'phone' => '+60 3-2345 6789',
                'email' => 'kl@cems.my',
                'type' => 'branch',
            ],
            [
                'code' => 'BR002',
                'name' => 'Petaling Jaya Branch',
                'city' => 'Petaling Jaya',
                'state' => 'Selangor',
                'postal_code' => '46200',
                'address' => 'Lot 5, Jalan SS21/1',
                'phone' => '+60 3-3456 7890',
                'email' => 'pj@cems.my',
                'type' => 'branch',
            ],
            [
                'code' => 'BR003',
                'name' => 'Penang Branch',
                'city' => 'George Town',
                'state' => 'Pulau Pinang',
                'postal_code' => '10200',
                'address' => '56 Jalan Masjid Kapitan Keling',
                'phone' => '+60 4-4567 8901',
                'email' => 'penang@cems.my',
                'type' => 'branch',
            ],
        ];

        foreach ($branches as $branch) {
            DB::table('branches')->updateOrInsert(
                ['code' => $branch['code']],
                [
                    'name' => $branch['name'],
                    'type' => $branch['type'],
                    'address' => $branch['address'] ?? null,
                    'city' => $branch['city'],
                    'state' => $branch['state'],
                    'postal_code' => $branch['postal_code'] ?? null,
                    'country' => 'Malaysia',
                    'phone' => $branch['phone'] ?? null,
                    'email' => $branch['email'] ?? null,
                    'is_active' => true,
                    'is_main' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
