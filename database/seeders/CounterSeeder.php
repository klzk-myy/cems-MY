<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Counter;
use Illuminate\Database\Seeder;

class CounterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Counters live at trading branches — a NULL branch_id leaves them
        // unusable for sessions and transactions.
        $branchIds = Branch::where('type', '!=', Branch::TYPE_HEAD_OFFICE)
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $counters = [
            ['code' => 'C01', 'name' => 'Counter 1 - Main', 'status' => 'active'],
            ['code' => 'C02', 'name' => 'Counter 2', 'status' => 'active'],
            ['code' => 'C03', 'name' => 'Counter 3', 'status' => 'active'],
            ['code' => 'C04', 'name' => 'Counter 4', 'status' => 'active'],
            ['code' => 'C05', 'name' => 'Counter 5 - Express', 'status' => 'active'],
        ];

        foreach ($counters as $i => $counter) {
            $counter['branch_id'] = $branchIds[$i % max(count($branchIds), 1)] ?? null;
            Counter::firstOrCreate(['code' => $counter['code']], $counter);
        }
    }
}
