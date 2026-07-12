<?php

namespace Tests\Feature\Audit;

use App\Enums\TransactionImportStatus;
use App\Enums\TransactionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TransactionImportTestHelpers;

class TransactionImportProcessTest extends TestCase
{
    use RefreshDatabase;
    use TransactionImportTestHelpers;

    public function test_import_completes_rows_below_auto_approve_threshold(): void
    {
        ['customer' => $customer, 'import' => $import] = $this->createFixtures();
        $service = $this->createImportService($import, '10000');
        $csv = $this->createCsv("{$customer->id},Buy,USD,1000,4.0,Business,Salary,MAIN");

        try {
            $service->process($csv);

            $this->assertDatabaseHas('transactions', [
                'customer_id' => $customer->id,
                'status' => TransactionStatus::Completed->value,
            ]);

            $this->assertDatabaseMissing('transactions', [
                'customer_id' => $customer->id,
                'hold_reason' => 'Transaction amount exceeds auto-approve threshold',
            ]);

            $this->assertDatabaseHas('transaction_imports', [
                'id' => $import->id,
                'status' => TransactionImportStatus::Completed->value,
                'success_count' => 1,
                'error_count' => 0,
            ]);

            $this->assertNotNull($import->fresh()->completed_at);
        } finally {
            unlink($csv);
        }
    }
}
