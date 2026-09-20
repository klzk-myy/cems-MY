<?php

namespace Database\Seeders;

use App\Enums\TellerAllocationStatus;
use App\Models\Branch;
use App\Models\BranchPool;
use App\Models\Currency;
use App\Models\TellerAllocation;
use App\Models\User;
use Illuminate\Database\Seeder;

class TellerAllocationSeeder extends Seeder
{
    public function run(): void
    {
        $tellers = User::where('role', 'teller')->get();

        // Skip if no tellers exist (e.g., during initial setup)
        if ($tellers->isEmpty()) {
            $this->command->info('No tellers found. Skipping teller allocation.');

            return;
        }

        $branches = Branch::all();
        // Tellers need a trading branch — head office cannot process
        // transactions, and a teller without a home branch is rejected by
        // EnsureBranchScope outright.
        $fallbackBranch = $branches->firstWhere('type', '!=', Branch::TYPE_HEAD_OFFICE)
            ?? $branches->first();
        $currencies = Currency::where('code', '!=', 'MYR')->where('is_active', true)->get();

        $allocationAmounts = [
            'USD' => 10000.00,
            'EUR' => 8000.00,
            'GBP' => 6000.00,
            'SGD' => 7000.00,
            'AUD' => 5000.00,
            'JPY' => 400000.00,
            'CHF' => 4000.00,
            'CAD' => 5000.00,
            'HKD' => 16000.00,
            'CNY' => 20000.00,
        ];

        foreach ($tellers as $teller) {
            // Prefer the teller's home branch; seeded users start unassigned,
            // so give them the first trading branch (never head office).
            $branch = $teller->branch_id
                ? $branches->firstWhere('id', $teller->branch_id)
                : $fallbackBranch;

            if (! $teller->branch_id && $branch) {
                $teller->update(['branch_id' => $branch->id]);
            }

            if (! $branch) {
                continue;
            }

            foreach ($currencies as $currency) {
                $amount = $allocationAmounts[$currency->code] ?? 5000.00;

                $allocation = TellerAllocation::updateOrCreate(
                    [
                        'user_id' => $teller->id,
                        'branch_id' => $branch->id,
                        'currency_code' => $currency->code,
                        'session_date' => today(),
                    ],
                    [
                        'requested_quantity' => (string) $amount,
                        'allocated_quantity' => (string) $amount,
                        'current_quantity' => (string) $amount,
                        'daily_limit_myr' => '100000',
                        'status' => TellerAllocationStatus::Active,
                        'approved_by' => User::where('role', 'manager')->first()?->id,
                        'approved_at' => now(),
                    ]
                );

                // Keep pool(avail+alloc) == currency_positions.quantity: the
                // float is teller custody of branch stock, so earmark it.
                // Only on first create — a re-run must not double-debit.
                if ($allocation->wasRecentlyCreated) {
                    $pool = BranchPool::where('branch_id', $branch->id)
                        ->where('currency_code', $currency->code)
                        ->first();

                    if (! $pool || ! $pool->allocate((string) $amount)) {
                        $this->command->warn("Pool cannot earmark {$amount} {$currency->code} at {$branch->code} — float untracked");
                    }
                }

                $this->command->info("Allocated {$amount} {$currency->code} to teller {$teller->username}");
            }
        }

        $this->command->info('Teller allocation seeding completed');
    }
}
