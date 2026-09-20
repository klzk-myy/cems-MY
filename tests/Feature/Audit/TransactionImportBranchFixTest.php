<?php

namespace Tests\Feature\Audit;

use App\Enums\TransactionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TransactionImportTestHelpers;

class TransactionImportBranchFixTest extends TestCase
{
    use RefreshDatabase;
    use TransactionImportTestHelpers;

    /**
     * Stock/position effects of an imported transaction must land on the
     * till's branch, not wherever the importer happens to be scoped.
     */
    public function test_transaction_import_uses_branch_id_for_position(): void
    {
        ['customer' => $customer, 'import' => $import, 'counter' => $counter] = $this->createFixtures();
        $service = $this->createImportService('10000');
        $csv = $this->createCsv("{$customer->id},Buy,USD,1000,4.0,Business,Salary,MAIN");

        try {
            $service->process($import, $csv);

            $this->assertDatabaseHas('transactions', [
                'customer_id' => $customer->id,
                'branch_id' => $counter->branch_id,
                'status' => TransactionStatus::Completed->value,
            ]);

            $this->assertDatabaseHas('currency_positions', [
                'currency_code' => 'USD',
                'branch_id' => $counter->branch_id,
            ]);
        } finally {
            unlink($csv);
        }
    }
}
