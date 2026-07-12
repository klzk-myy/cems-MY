<?php

namespace Tests\Feature\Audit;

use App\Enums\TransactionImportStatus;
use App\Enums\TransactionStatus;
use App\Models\Counter;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\TillBalance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use Tests\Traits\TransactionImportTestHelpers;

class TransactionImportProcessTest extends TestCase
{
    use RefreshDatabase;
    use TransactionImportTestHelpers;

    public function test_import_completes_rows_below_auto_approve_threshold(): void
    {
        ['customer' => $customer, 'import' => $import] = $this->createFixtures();
        $service = $this->createImportService('10000');
        $csv = $this->createCsv("{$customer->id},Buy,USD,1000,4.0,Business,Salary,MAIN");

        try {
            $service->process($import, $csv);

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

    public function test_batch_upload_updates_the_created_import_record(): void
    {
        $user = User::factory()->manager()->create();
        $currency = Currency::factory()->create(['code' => 'USD']);
        $customer = Customer::factory()->create();
        $counter = Counter::factory()->create(['code' => 'MAIN']);
        TillBalance::factory()->create([
            'till_id' => $counter->code,
            'currency_code' => $currency->code,
            'date' => today(),
            'opening_balance' => '10000',
        ]);
        TillBalance::factory()->create([
            'till_id' => $counter->code,
            'currency_code' => 'MYR',
            'date' => today(),
            'opening_balance' => '10000',
        ]);

        $this->actingAs($user);

        $csv = UploadedFile::fake()->createWithContent('transactions.csv', "customer_id,type,currency_code,amount_foreign,rate,purpose,source_of_funds,till_id\n{$customer->id},Buy,USD,100,4.0,Business,Salary,MAIN\n");

        $response = $this->postJson(route('transactions.batch-upload.store'), [
            'csv_file' => $csv,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('transaction_imports', [
            'imported_by' => $user->id,
            'status' => TransactionImportStatus::Completed->value,
            'total_rows' => 1,
            'success_count' => 1,
        ]);
    }
}
