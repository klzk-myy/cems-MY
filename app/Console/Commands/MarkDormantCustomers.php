<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Services\AuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class MarkDormantCustomers extends Command
{
    protected $signature = 'customers:mark-dormant
                            {--months= : Inactivity window in months (defaults to config cems.dormancy_months)}
                            {--chunk=500}';

    protected $description = 'Mark active customers with no recent transactions as dormant

Stamps dormant_at on active customers whose last transaction is older than
the configured dormancy window (cems.dormancy_months, default 12). Each
stamp is audit-logged. Re-KYC nudges for dormant customers are handled by
the periodic review workflow, not this command.';

    public function handle(AuditService $auditService): int
    {
        $months = (int) ($this->option('months') ?: config('cems.dormancy_months', 12));
        $chunk = max(1, (int) $this->option('chunk'));
        $cutoff = now()->subMonths($months);

        $this->info("Marking customers dormant with no transactions since {$cutoff->toDateString()}...");

        $count = 0;

        Customer::where('is_active', true)
            ->whereNull('dormant_at')
            ->where(function ($query) use ($cutoff) {
                $query->where('last_transaction_at', '<', $cutoff)
                    ->orWhere(function ($query) use ($cutoff) {
                        // Never transacted: judge inactivity from onboarding age.
                        $query->whereNull('last_transaction_at')
                            ->where('created_at', '<', $cutoff);
                    });
            })
            ->select('id')
            ->chunkById($chunk, function ($customers) use (&$count, $auditService) {
                foreach ($customers as $customer) {
                    $customer->forceFill(['dormant_at' => Carbon::now()])->saveQuietly();

                    $auditService->logCustomerEvent('customer_marked_dormant', $customer->id, [
                        'new_values' => [
                            'dormant_at' => now()->toIso8601String(),
                            'last_transaction_at' => optional($customer->last_transaction_at)->toIso8601String(),
                        ],
                    ]);

                    $count++;
                }

                $this->line("Marked {$count} customers dormant...");
            });

        $this->info("Done. {$count} customers marked dormant.");

        return self::SUCCESS;
    }
}
