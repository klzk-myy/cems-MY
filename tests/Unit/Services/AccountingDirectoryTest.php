<?php

namespace Tests\Unit\Services;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_accounting_services_are_in_accounting_directory(): void
    {
        $expectedFiles = [
            'AccountingService.php',
            'LedgerService.php',
            'FiscalYearService.php',
            'PeriodCloseService.php',
            'MonthEndCloseService.php',
            'BankReconciliationService.php',
            'RevaluationService.php',
            'BudgetService.php',
            'TransactionAccountingService.php',
            'CurrencyPositionService.php',
        ];

        foreach ($expectedFiles as $file) {
            $this->assertFileExists(
                app_path("Services/Accounting/{$file}"),
                "{$file} should be in Services/Accounting/"
            );
        }
    }
}
