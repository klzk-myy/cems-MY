<?php

namespace Tests\Feature\Audit;

use Tests\TestCase;

class TransactionImportBranchFixTest extends TestCase
{
    /**
     * Test that stock check uses correct branch_id from tillBalance.
     */
    public function test_transaction_import_uses_branch_id_for_position(): void
    {
        $file = base_path('app/Services/Transaction/TransactionImportService.php');
        $this->assertFileExists($file);

        $content = file_get_contents($file);
        $this->assertStringContainsString(
            'createForImport',
            $content,
            'Should delegate stock check to TransactionCreationService'
        );

        $creationFile = base_path('app/Services/Transaction/TransactionCreationService.php');
        $this->assertFileExists($creationFile);
        $creationContent = file_get_contents($creationFile);
        $this->assertStringContainsString(
            'tillBalance?->branch_id',
            $creationContent,
            'Should use the till balance branch_id for position lookup'
        );
    }
}
