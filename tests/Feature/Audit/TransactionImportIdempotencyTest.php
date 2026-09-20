<?php

namespace Tests\Feature\Audit;

use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TransactionImportTestHelpers;

class TransactionImportIdempotencyTest extends TestCase
{
    use RefreshDatabase;
    use TransactionImportTestHelpers;

    /**
     * Re-processing the same import file must not create duplicate
     * transactions — rows dedupe on their per-import, per-row
     * idempotency key.
     */
    public function test_transaction_import_has_idempotency_check(): void
    {
        ['customer' => $customer, 'import' => $import] = $this->createFixtures();
        $service = $this->createImportService('10000');
        $csv = $this->createCsv("{$customer->id},Buy,USD,1000,4.0,Business,Salary,MAIN");

        try {
            $service->process($import, $csv);
            $service->process($import->fresh(), $csv);

            $this->assertSame(
                1,
                Transaction::where('customer_id', $customer->id)->count(),
                'Re-running the same import must not duplicate transactions'
            );
        } finally {
            unlink($csv);
        }
    }
}
