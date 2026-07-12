<?php

namespace Tests\Feature\Audit;

use App\Enums\CddLevel;
use App\Enums\TransactionStatus;
use App\Services\Compliance\ComplianceService;
use App\Services\DTOs\ComplianceCheckResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TransactionImportTestHelpers;

class TransactionImportThresholdTest extends TestCase
{
    use RefreshDatabase;
    use TransactionImportTestHelpers;

    public function test_import_marks_rows_above_auto_approve_threshold_as_pending(): void
    {
        ['customer' => $customer, 'import' => $import] = $this->createFixtures();
        $service = $this->createImportService($import, '5000');
        $csv = $this->createCsv("{$customer->id},Buy,USD,2000,4.0,Business,Salary,MAIN");

        try {
            $service->process($csv);

            $this->assertDatabaseHas('transactions', [
                'customer_id' => $customer->id,
                'status' => TransactionStatus::PendingApproval->value,
                'hold_reason' => 'Transaction amount exceeds auto-approve threshold',
            ]);
        } finally {
            unlink($csv);
        }
    }

    public function test_import_marks_rows_equal_to_auto_approve_threshold_as_pending(): void
    {
        ['customer' => $customer, 'import' => $import] = $this->createFixtures();
        $service = $this->createImportService($import, '4000');
        $csv = $this->createCsv("{$customer->id},Buy,USD,1000,4.0,Business,Salary,MAIN");

        try {
            $service->process($csv);

            $this->assertDatabaseHas('transactions', [
                'customer_id' => $customer->id,
                'status' => TransactionStatus::PendingApproval->value,
                'hold_reason' => 'Transaction amount exceeds auto-approve threshold',
            ]);
        } finally {
            unlink($csv);
        }
    }

    public function test_import_appends_threshold_reason_to_compliance_hold_reason(): void
    {
        ['customer' => $customer, 'import' => $import] = $this->createFixtures();

        $complianceService = $this->createMock(ComplianceService::class);
        $complianceService->method('requiresHold')->willReturn(
            new ComplianceCheckResult(requiresHold: true, reasons: ['Customer risk requires review'])
        );
        $complianceService->method('determineCDDLevel')->willReturn(CddLevel::Standard);

        $service = $this->createImportService($import, '5000', $complianceService);
        $csv = $this->createCsv("{$customer->id},Buy,USD,2000,4.0,Business,Salary,MAIN");

        try {
            $service->process($csv);

            $this->assertDatabaseHas('transactions', [
                'customer_id' => $customer->id,
                'status' => TransactionStatus::PendingApproval->value,
                'hold_reason' => 'Customer risk requires review; Transaction amount exceeds auto-approve threshold',
            ]);
        } finally {
            unlink($csv);
        }
    }
}
