<?php

namespace Database\Seeders;

use App\Services\System\PermissionService;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionService::class)->seedDefaults();
    }
}
