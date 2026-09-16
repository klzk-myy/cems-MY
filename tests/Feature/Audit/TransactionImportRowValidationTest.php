<?php

namespace Tests\Feature\Audit;

use App\Enums\CddLevel;
use App\Enums\TransactionImportStatus;
use App\Models\Customer;
use App\Models\TillBalance;
use App\Services\Compliance\ComplianceService;
use App\Services\DTOs\ComplianceCheckResult;
use App\Services\Transaction\RateManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TransactionImportTestHelpers;

/**
 * Per-row validation failures during CSV import land in error_details
 * without halting the file or creating a transaction.
 */
class TransactionImportRowValidationTest extends TestCase
{
    use RefreshDatabase;
    use TransactionImportTestHelpers;

    private function processRow(string $row, ?ComplianceService $complianceService = null, ?RateManagementService $rateManagementService = null): mixed
    {
        ['customer' => $customer, 'import' => $import] = $this->createFixtures();
        $service = $this->createImportService('999999', $complianceService, $rateManagementService);
        $csv = $this->createCsv(str_replace('{customer}', (string) $customer->id, $row));

        try {
            $service->process($import, $csv);
        } finally {
            unlink($csv);
        }

        return $import->refresh();
    }

    private function assertRowError(mixed $import, string $needle): void
    {
        $this->assertSame(TransactionImportStatus::CompletedWithErrors->value, $import->status->value ?? $import->status);
        $this->assertSame(0, $import->success_count);
        $this->assertSame(1, $import->error_count);
        $this->assertStringContainsString($needle, $import->error_details[0]['error']);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_missing_required_fields_are_rejected(): void
    {
        $import = $this->processRow('{customer},Buy,USD,100,4.0,,,MAIN');

        $this->assertRowError($import, 'Missing required fields');
    }

    public function test_unknown_currency_code_is_rejected(): void
    {
        $import = $this->processRow('{customer},Buy,ZZZ,100,4.0,Business,Salary,MAIN');

        $this->assertRowError($import, 'ZZZ');
    }

    public function test_unknown_till_is_rejected(): void
    {
        $import = $this->processRow('{customer},Buy,USD,100,4.0,Business,Salary,NOPE');

        $this->assertRowError($import, 'Till NOPE is not open for USD');
    }

    public function test_aberrant_rate_is_rejected(): void
    {
        $rateManagement = $this->createMock(RateManagementService::class);
        $rateManagement->method('validateTransactionRate')->willReturn([
            'valid' => false,
            'reason' => 'Rate deviation exceeds maximum allowed',
        ]);

        $import = $this->processRow('{customer},Buy,USD,100,99.0,Business,Salary,MAIN', rateManagementService: $rateManagement);

        $this->assertRowError($import, 'Rate deviation exceeds maximum allowed');
    }

    public function test_insufficient_till_balance_is_rejected(): void
    {
        ['import' => $import] = $this->createFixtures();
        TillBalance::where('till_id', 'MAIN')->where('currency_code', 'MYR')
            ->update(['opening_balance' => '10']);

        $service = $this->createImportService('999999');
        $customer = Customer::first();
        $csv = $this->createCsv("{$customer->id},Buy,USD,1000,4.0,Business,Salary,MAIN");

        try {
            $service->process($import, $csv);
        } finally {
            unlink($csv);
        }

        $this->assertRowError($import->refresh(), 'Insufficient MYR balance in till for buy transaction');
    }

    public function test_sanctioned_customer_is_rejected(): void
    {
        $complianceService = $this->createMock(ComplianceService::class);
        $complianceService->method('checkSanctionMatch')->willReturn(true);
        $complianceService->method('determineCDDLevel')->willReturn(CddLevel::Standard);
        $complianceService->method('requiresHold')->willReturn(
            new ComplianceCheckResult(requiresHold: false, reasons: [])
        );

        $import = $this->processRow('{customer},Buy,USD,100,4.0,Business,Salary,MAIN', complianceService: $complianceService);

        $this->assertRowError($import, 'sanctions screening');
    }
}
