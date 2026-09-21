<?php

namespace Tests\Feature\Audit;

use App\Enums\RiskRating;
use App\Enums\TransactionStatus;
use App\Models\Customer;
use App\Services\Transaction\TransactionHoldService;
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
        $service = $this->createImportService('5000');
        $csv = $this->createCsv("{$customer->id},Buy,USD,2000,4.0,Business,Salary,MAIN");

        try {
            $service->process($import, $csv);

            $this->assertDatabaseHas('transactions', [
                'customer_id' => $customer->id,
                'status' => TransactionStatus::PendingApproval->value,
                'hold_reason' => null, // threshold pendings don't carry a compliance-clear gate
            ]);
        } finally {
            unlink($csv);
        }
    }

    public function test_import_marks_rows_equal_to_auto_approve_threshold_as_pending(): void
    {
        ['customer' => $customer, 'import' => $import] = $this->createFixtures();
        $service = $this->createImportService('4000');
        $csv = $this->createCsv("{$customer->id},Buy,USD,1000,4.0,Business,Salary,MAIN");

        try {
            $service->process($import, $csv);

            $this->assertDatabaseHas('transactions', [
                'customer_id' => $customer->id,
                'status' => TransactionStatus::PendingApproval->value,
                'hold_reason' => null, // threshold pendings don't carry a compliance-clear gate
            ]);
        } finally {
            unlink($csv);
        }
    }

    public function test_import_auto_completes_medium_risk_rows_below_threshold(): void
    {
        // D1: Medium risk alone does not force approval — documented policy.
        ['customer' => $customer, 'import' => $import] = $this->createFixtures();
        // risk_rating is a workflow field (not fillable) — set it directly.
        Customer::whereKey($customer->id)
            ->update(['risk_rating' => RiskRating::Medium->value]);

        $service = $this->createImportService('5000');
        $csv = $this->createCsv("{$customer->id},Buy,USD,500,4.0,Business,Salary,MAIN");

        try {
            $service->process($import, $csv);

            $this->assertDatabaseHas('transactions', [
                'customer_id' => $customer->id,
                'status' => TransactionStatus::Completed->value,
                'hold_reason' => null,
            ]);
        } finally {
            unlink($csv);
        }
    }

    public function test_import_appends_threshold_reason_to_compliance_hold_reason(): void
    {
        ['customer' => $customer, 'import' => $import] = $this->createFixtures();

        // A compliance hold now comes from the shared preValidate gate — bind
        // the hold service mock so TransactionCreationService picks it up.
        $holdService = $this->createMock(TransactionHoldService::class);
        $holdService->method('requiresHold')->willReturn(true);
        $this->app->instance(TransactionHoldService::class, $holdService);

        $service = $this->createImportService('5000');
        $csv = $this->createCsv("{$customer->id},Buy,USD,2000,4.0,Business,Salary,MAIN");

        try {
            $service->process($import, $csv);

            $this->assertDatabaseHas('transactions', [
                'customer_id' => $customer->id,
                'status' => TransactionStatus::PendingApproval->value,
                'hold_reason' => 'Compliance hold; Transaction amount exceeds auto-approve threshold',
            ]);
        } finally {
            unlink($csv);
        }
    }
}
