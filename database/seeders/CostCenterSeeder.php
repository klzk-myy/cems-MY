<?php

namespace Database\Seeders;

use App\Models\CostCenter;
use App\Models\Department;
use Illuminate\Database\Seeder;

class CostCenterSeeder extends Seeder
{
    public function run(): void
    {
        // Get department IDs
        $exec = Department::where('code', 'EXEC')->first();
        $ops = Department::where('code', 'OPS')->first();
        $sales = Department::where('code', 'SALES')->first();
        $fin = Department::where('code', 'FIN')->first();
        $tech = Department::where('code', 'TECH')->first();
        $hr = Department::where('code', 'HR')->first();
        $comp = Department::where('code', 'COMP')->first();
        $risk = Department::where('code', 'RISK')->first();

        $costCenters = [
            // Executive cost centers
            ['code' => 'EXEC-001', 'name' => 'Board & Management', 'department_id' => $exec?->id],
            ['code' => 'EXEC-002', 'name' => 'Strategic Planning', 'department_id' => $exec?->id],

            // Operations cost centers
            ['code' => 'OPS-001', 'name' => 'KL Main Branch', 'department_id' => $ops?->id],
            ['code' => 'OPS-002', 'name' => 'KL Counter Operations', 'department_id' => $ops?->id],
            ['code' => 'OPS-003', 'name' => 'Penang Branch', 'department_id' => $ops?->id],
            ['code' => 'OPS-004', 'name' => 'Johor Branch', 'department_id' => $ops?->id],

            // Sales cost centers
            ['code' => 'SALES-001', 'name' => 'Corporate Sales', 'department_id' => $sales?->id],
            ['code' => 'SALES-002', 'name' => 'Retail Sales', 'department_id' => $sales?->id],
            ['code' => 'SALES-003', 'name' => 'Marketing', 'department_id' => $sales?->id],

            // Finance cost centers
            ['code' => 'FIN-001', 'name' => 'Accounting', 'department_id' => $fin?->id],
            ['code' => 'FIN-002', 'name' => 'Treasury', 'department_id' => $fin?->id],
            ['code' => 'FIN-003', 'name' => 'Internal Audit', 'department_id' => $fin?->id],

            // IT cost centers
            ['code' => 'TECH-001', 'name' => 'Systems Administration', 'department_id' => $tech?->id],
            ['code' => 'TECH-002', 'name' => 'Software Development', 'department_id' => $tech?->id],
            ['code' => 'TECH-003', 'name' => 'Infrastructure', 'department_id' => $tech?->id],

            // HR cost centers
            ['code' => 'HR-001', 'name' => 'Recruitment', 'department_id' => $hr?->id],
            ['code' => 'HR-002', 'name' => 'Training', 'department_id' => $hr?->id],

            // Compliance cost centers
            ['code' => 'COMP-001', 'name' => 'AML Monitoring', 'department_id' => $comp?->id],
            ['code' => 'COMP-002', 'name' => 'Regulatory Reporting', 'department_id' => $comp?->id],

            // Risk cost centers
            ['code' => 'RISK-001', 'name' => 'Operational Risk', 'department_id' => $risk?->id],
            ['code' => 'RISK-002', 'name' => 'Market Risk', 'department_id' => $risk?->id],
        ];

        foreach ($costCenters as $center) {
            CostCenter::firstOrCreate(
                ['code' => $center['code']],
                $center
            );
        }
    }
}
