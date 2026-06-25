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
            'getPositionWithLock',
            $content,
            'Should use getPositionWithLock for stock check'
        );
        $this->assertStringContainsString(
            '$tillBalance->branch_id',
            $content,
            'Should use $tillBalance->branch_id for position lookup'
        );
    }
}
