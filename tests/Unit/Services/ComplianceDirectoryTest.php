<?php

namespace Tests\Unit\Services;

use Tests\TestCase;

class ComplianceDirectoryTest extends TestCase
{
    public function test_compliance_services_are_in_compliance_directory(): void
    {
        $expectedFiles = [
            'ComplianceService.php',
            'CddLevelDeterminationService.php',
            'CustomerRiskScoringService.php',
            'CustomerRiskReviewService.php',
            'HistoricalRiskAnalysisService.php',
            'AlertTriageService.php',
            'EddService.php',
            'EddTemplateService.php',
            'KycDocumentExpiryService.php',
            'PepApprovalService.php',
            'RiskCalculationService.php',
            'SanctionsDownloadService.php',
            'SanctionsImportService.php',
            'SanctionsOrchestrationService.php',
            'NarrativeGenerator.php',
        ];

        foreach ($expectedFiles as $file) {
            $this->assertFileExists(
                app_path("Services/Compliance/{$file}"),
                "{$file} should be in Services/Compliance/"
            );
        }
    }
}
