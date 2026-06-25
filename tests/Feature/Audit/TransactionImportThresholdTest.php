<?php

namespace Tests\Feature\Audit;

use Tests\TestCase;

class TransactionImportThresholdTest extends TestCase
{
    /**
     * Test that TransactionImportService enforces auto-approve threshold.
     */
    public function test_transaction_import_enforces_threshold(): void
    {
        $file = base_path('app/Services/Transaction/TransactionImportService.php');
        $this->assertFileExists($file);

        $content = file_get_contents($file);
        $this->assertStringContainsString(
            '$threshold = $this->thresholdService->getAutoApproveThreshold()',
            $content,
            'Threshold check should be present in TransactionImportService'
        );
        $this->assertStringContainsString(
            'if ($this->mathService->compare($amountLocal, $threshold) >= 0)',
            $content,
            'Threshold comparison should be present'
        );
    }
}
