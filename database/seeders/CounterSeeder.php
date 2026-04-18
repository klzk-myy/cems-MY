<?php

namespace Database\Seeders;

use App\Models\Counter;
use Illuminate\Database\Seeder;

class CounterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $counters = [
            ['code' => 'C01', 'name' => 'Counter 1 - Main', 'status' => 'active'],
            ['code' => 'C02', 'name' => 'Counter 2', 'status' => 'active'],
            ['code' => 'C03', 'name' => 'Counter 3', 'status' => 'active'],
            ['code' => 'C04', 'name' => 'Counter 4', 'status' => 'active'],
            ['code' => 'C05', 'name' => 'Counter 5 - Express', 'status' => 'active'],
        ];

        foreach ($counters as $counter) {
            Counter::firstOrCreate(['code' => $counter['code']], $counter);
        }
    }
}
