<?php

namespace App\Console\Commands;

use App\Models\Compliance\CustomerRiskProfile;
use App\Models\Customer;
use App\Services\Compliance\CustomerRiskScoringService;
use App\Services\Compliance\PepAssessmentService;
use App\Services\Compliance\RiskScoreWriteBackService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PepCessationReviewCommand extends Command
{
    protected $signature = 'customers:pep-cessation-review
                            {--dry-run : Show what would be done without making changes}';

    protected $description = 'Sweep active former PEPs (pep flag set with pep_role_ended_at filled), assess PEP cessation, and downgrade risk via the shared risk write-back path';

    public function handle(
        PepAssessmentService $pepAssessmentService,
        RiskScoreWriteBackService $writeBack,
        CustomerRiskScoringService $scoring,
    ): int {
        $dryRun = (bool) $this->option('dry-run');

        $customers = Customer::where('is_active', true)
            ->where('pep_status', true)
            ->whereNotNull('pep_role_ended_at')
            ->get();

        if ($customers->isEmpty()) {
            $this->info('No active customers flagged as PEP with an ended role.');

            return Command::SUCCESS;
        }

        $this->info("Found {$customers->count()} candidate(s) for PEP cessation review".($dryRun ? ' (DRY RUN)' : ''));

        $cessated = 0;
        $downgraded = 0;
        $locked = 0;
        $errors = 0;

        foreach ($customers as $customer) {
            try {
                $result = $pepAssessmentService->assessPepCessation($customer);

                if (! $result->canCessate) {
                    continue;
                }

                $lockedProfile = CustomerRiskProfile::where('customer_id', $customer->id)->first();
                if ($lockedProfile && $lockedProfile->isLocked()) {
                    $locked++;
                    $this->line("  Skipped (risk profile locked): customer {$customer->id}");

                    continue;
                }

                if ($dryRun) {
                    $this->line("  Would cessate PEP status: customer {$customer->id}");
                    $cessated++;

                    continue;
                }

                $changed = DB::transaction(function () use ($customer, $writeBack, $scoring) {
                    // Clear the PEP flag first so the recomputed snapshot no
                    // longer carries PEP-driven holds, then write back the
                    // recalculated score through the shared hook so a
                    // CustomerRiskHistory row is recorded when it changes.
                    $customer->forceFill(['pep_status' => false])->saveQuietly();

                    $scores = $scoring->calculateRiskScores($customer);

                    return $writeBack->apply(
                        $customer,
                        $scores['overall'],
                        'pep_cessation_review'
                    );
                });

                $cessated++;
                if ($changed) {
                    $downgraded++;
                }

                Log::info('PEP cessation applied', [
                    'customer_id' => $customer->id,
                    'score_downgraded' => $changed,
                    'factors' => $result->factors,
                ]);
            } catch (\Throwable $e) {
                $errors++;
                $this->error("  Failed for customer {$customer->id}: {$e->getMessage()}");
                Log::error('PEP cessation review failed for customer', [
                    'customer_id' => $customer->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Done. Cessated: {$cessated}, score/rating downgraded: {$downgraded}, locked profiles skipped: {$locked}, errors: {$errors}.");

        return Command::SUCCESS;
    }
}
