<?php

namespace App\Services\Compliance;

use App\DTO\PepCessationResult;
use App\Models\Customer;
use Carbon\Carbon;

class PepAssessmentService
{
    public function assessPepCessation(Customer $customer): PepCessationResult
    {
        $factors = [];

        $informalInfluence = $this->assessInformalInfluence($customer);
        $factors['informal_influence'] = $informalInfluence;

        $sameMatters = $this->assessFunctionContinuity($customer);
        $factors['same_substantive_matters'] = $sameMatters;

        $canCessation = $informalInfluence['level'] === 'low' && ! $sameMatters;

        return new PepCessationResult(
            canCessate: $canCessation,
            factors: $factors,
            assessedAt: Carbon::now(),
        );
    }

    public function assessInformalInfluence(Customer $customer): array
    {
        $lastRoleEnded = $customer->pep_role_ended_at;
        $yearsSince = $lastRoleEnded ? $lastRoleEnded->diffInYears(Carbon::now()) : 0;

        if ($yearsSince > 5) {
            return ['level' => 'low', 'reason' => '5+ years since PEP role'];
        }

        if ($yearsSince > 2) {
            return ['level' => 'medium', 'reason' => '2-5 years since PEP role'];
        }

        return ['level' => 'high', 'reason' => 'Recent PEP role (< 2 years)'];
    }

    public function assessFunctionContinuity(Customer $customer): bool
    {
        return $customer->current_role_domain === $customer->former_pep_domain;
    }
}
