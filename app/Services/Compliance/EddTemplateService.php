<?php

namespace App\Services\Compliance;

use App\Enums\EddTemplateType;
use App\Models\EddTemplate;
use App\Services\System\MathService;
use App\Services\ThresholdService;

class EddTemplateService
{
    public function __construct(
        protected ThresholdService $thresholdService,
        protected MathService $mathService,
    ) {}

    /**
     * Get recommended template for a customer/context.
     */
    public function getRecommendedTemplate(array $context): ?EddTemplate
    {
        if ($context['is_sanctioned'] ?? false) {
            return EddTemplate::active()->byType(EddTemplateType::SanctionMatch)->first();
        }

        if ($context['is_pep'] ?? false) {
            return EddTemplate::active()->byType(EddTemplateType::Pep)->first();
        }

        if ($context['high_risk_country'] ?? false) {
            return EddTemplate::active()->byType(EddTemplateType::HighRiskCountry)->first();
        }

        if ($this->mathService->compare((string) ($context['transaction_amount'] ?? 0), $this->thresholdService->getEddThreshold()) >= 0) {
            return EddTemplate::active()->byType(EddTemplateType::LargeTransaction)->first();
        }

        if ($context['unusual_pattern'] ?? false) {
            return EddTemplate::active()->byType(EddTemplateType::UnusualPattern)->first();
        }

        return EddTemplate::active()->first();
    }
}
