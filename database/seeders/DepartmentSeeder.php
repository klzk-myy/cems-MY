<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            ['code' => 'EXEC', 'name' => 'Executive Management', 'description' => 'Executive leadership and strategic management'],
            ['code' => 'OPS', 'name' => 'Operations', 'description' => 'Currency exchange operations and teller management'],
            ['code' => 'SALES', 'name' => 'Sales & Marketing', 'description' => 'Business development and customer acquisition'],
            ['code' => 'FIN', 'name' => 'Finance & Accounting', 'description' => 'Financial management, reporting and compliance'],
            ['code' => 'TECH', 'name' => 'Information Technology', 'description' => 'Systems, infrastructure and security'],
            ['code' => 'HR', 'name' => 'Human Resources', 'description' => 'Staff management and recruitment'],
            ['code' => 'COMP', 'name' => 'Compliance', 'description' => 'AML/CFT compliance and regulatory affairs'],
            ['code' => 'RISK', 'name' => 'Risk Management', 'description' => 'Operational and financial risk oversight'],
        ];

        foreach ($departments as $department) {
            Department::firstOrCreate(
                ['code' => $department['code']],
                $department
            );
        }
    }
}
