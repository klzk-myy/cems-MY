<?php

namespace Tests\Feature;

use App\Enums\TransactionImportStatus;
use App\Jobs\ProcessTransactionImportJob;
use App\Models\TransactionImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\TransactionImportTestHelpers;

class TransactionImportQueueTest extends TestCase
{
    use RefreshDatabase;
    use TransactionImportTestHelpers;

    #[Test]
    public function batch_upload_dispatches_queued_job_and_stays_pending(): void
    {
        Queue::fake();

        $user = User::factory()->manager()->create();
        ['customer' => $customer] = $this->createFixtures(createImport: false);

        $csv = UploadedFile::fake()->createWithContent(
            'transactions.csv',
            "customer_id,type,currency_code,quantity,rate,purpose,source_of_funds,till_id\n{$customer->id},Buy,USD,100,4.0,Business,Salary,MAIN\n"
        );

        $this->actingAs($user)
            ->post(route('transactions.batch-upload.store'), ['csv_file' => $csv])
            ->assertRedirect();

        Queue::assertPushed(ProcessTransactionImportJob::class);

        // The heavy work must not run inside the request when queued.
        $this->assertDatabaseHas('transaction_imports', [
            'imported_by' => $user->id,
            'status' => TransactionImportStatus::Pending->value,
            'total_rows' => 1,
        ]);
    }

    #[Test]
    public function queued_job_processes_all_valid_rows(): void
    {
        ['customer' => $customer] = $this->createFixtures(createImport: false);
        $csv = $this->createMultiRowCsv([
            "{$customer->id},Buy,USD,100,4.0,Business,Salary,MAIN",
            "{$customer->id},Sell,USD,50,4.0,Business,Salary,MAIN",
        ]);

        $import = TransactionImport::create([
            'imported_by' => User::factory()->create()->id,
            'filename' => $csv,
            'original_filename' => 'transactions.csv',
            'total_rows' => 2,
            'status' => TransactionImportStatus::Pending->value,
        ]);

        try {
            (new ProcessTransactionImportJob($import))->handle($this->createImportService('10000'));

            $fresh = $import->fresh();

            $this->assertEquals(TransactionImportStatus::Completed, $fresh->status);
            $this->assertEquals(2, $fresh->success_count);
            $this->assertEquals(0, $fresh->error_count);
            $this->assertEquals(2, $fresh->processed_rows);
            $this->assertNotNull($fresh->completed_at);
        } finally {
            @unlink($csv);
        }
    }

    #[Test]
    public function queued_job_failed_row_does_not_halt_remaining_rows(): void
    {
        ['customer' => $customer] = $this->createFixtures(createImport: false);

        $csv = $this->createMultiRowCsv([
            "{$customer->id},Buy,USD,100,4.0,Business,Salary,MAIN",
            "{$customer->id},Buy,XXX,100,4.0,Business,Salary,MAIN", // invalid currency
            "{$customer->id},Sell,USD,25,4.0,Business,Salary,MAIN",
        ]);

        $import = TransactionImport::create([
            'imported_by' => User::factory()->create()->id,
            'filename' => $csv,
            'original_filename' => 'transactions.csv',
            'total_rows' => 3,
            'status' => TransactionImportStatus::Pending->value,
        ]);

        try {
            (new ProcessTransactionImportJob($import))->handle($this->createImportService('10000'));

            $fresh = $import->fresh();

            $this->assertEquals(TransactionImportStatus::CompletedWithErrors, $fresh->status);
            $this->assertEquals(2, $fresh->success_count);
            $this->assertEquals(1, $fresh->error_count);
            $this->assertEquals(3, $fresh->processed_rows);

            // The row after the failed one was still imported.
            $this->assertDatabaseHas('transaction_imports', ['id' => $import->id]);
        } finally {
            @unlink($csv);
        }
    }

    /**
     * Build a temp CSV with multiple data rows.
     *
     * @param  array<int, string>  $rows
     */
    private function createMultiRowCsv(array $rows): string
    {
        $csv = tempnam(sys_get_temp_dir(), 'import');
        file_put_contents($csv, "customer_id,type,currency_code,quantity,rate,purpose,source_of_funds,till_id\n");
        foreach ($rows as $row) {
            file_put_contents($csv, "{$row}\n", FILE_APPEND);
        }

        return $csv;
    }
}
