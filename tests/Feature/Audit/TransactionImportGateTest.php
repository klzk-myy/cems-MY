<?php

namespace Tests\Feature\Audit;

use App\Enums\TransactionImportStatus;
use App\Models\Counter;
use App\Models\Customer;
use App\Models\TillBalance;
use App\Models\TransactionImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TransactionImportTestHelpers;

/**
 * Phase 3 (T6/T7/T8): the CSV import path must funnel through the same
 * booking gate as the web wizard and API — customer-state, branch scope and
 * liquidity checks can no longer be bypassed, and per-row idempotency keys
 * must not collide across files.
 */
class TransactionImportGateTest extends TestCase
{
    use RefreshDatabase;
    use TransactionImportTestHelpers;

    /**
     * @param  array<int, string>  $rows
     */
    private function createCsvRows(array $rows): string
    {
        $csv = tempnam(sys_get_temp_dir(), 'import');
        file_put_contents($csv, "customer_id,type,currency_code,quantity,rate,purpose,source_of_funds,till_id\n");
        foreach ($rows as $row) {
            file_put_contents($csv, "{$row}\n", FILE_APPEND);
        }

        return $csv;
    }

    /** T6: a frozen customer must not transact via CSV. */
    public function test_import_rejects_frozen_customer(): void
    {
        ['customer' => $customer, 'import' => $import] = $this->createFixtures();
        // is_frozen/freeze_reason are workflow fields (not fillable) — update
        // through the query builder so the flag actually persists.
        Customer::whereKey($customer->id)
            ->update(['is_frozen' => true, 'freeze_reason' => 'BNM freeze order']);

        $service = $this->createImportService('999999');
        $csv = $this->createCsv("{$customer->id},Buy,USD,100,4.0,Business,Salary,MAIN");

        try {
            $service->process($import, $csv);
        } finally {
            unlink($csv);
        }

        $import->refresh();
        $this->assertSame(TransactionImportStatus::CompletedWithErrors->value, $import->status->value ?? $import->status);
        $this->assertSame(1, $import->error_count);
        $this->assertStringContainsString('cannot transact', $import->error_details[0]['error']);
        $this->assertStringContainsString('BNM freeze order', $import->error_details[0]['error']);
        $this->assertDatabaseCount('transactions', 0);
    }

    /** T6: PEP rows without source_of_wealth must fail. */
    public function test_import_rejects_pep_customer_without_source_of_wealth(): void
    {
        ['customer' => $customer, 'import' => $import] = $this->createFixtures();
        $customer->update(['pep_status' => true]);

        $service = $this->createImportService('999999');
        $csv = $this->createCsv("{$customer->id},Buy,USD,100,4.0,Business,Salary,MAIN");

        try {
            $service->process($import, $csv);
        } finally {
            unlink($csv);
        }

        $import->refresh();
        $this->assertSame(1, $import->error_count);
        $this->assertStringContainsString('PEP', $import->error_details[0]['error']);
        $this->assertDatabaseCount('transactions', 0);
    }

    /** T6: a branch-scoped importer cannot book against another branch's till. */
    public function test_import_rejects_foreign_branch_till(): void
    {
        ['customer' => $customer, 'import' => $import] = $this->createFixtures();

        $foreignCounter = Counter::factory()->create(['code' => 'BR02']);
        TillBalance::factory()->create([
            'till_id' => 'BR02',
            'currency_code' => 'USD',
            'branch_id' => $foreignCounter->branch_id,
            'date' => today(),
            'opening_balance' => '10000',
        ]);

        $service = $this->createImportService('999999');
        $csv = $this->createCsv("{$customer->id},Buy,USD,100,4.0,Business,Salary,BR02");

        try {
            $service->process($import, $csv);
        } finally {
            unlink($csv);
        }

        $import->refresh();
        $this->assertSame(1, $import->error_count);
        $this->assertStringContainsString('BR02', $import->error_details[0]['error']);
        $this->assertDatabaseCount('transactions', 0);
    }

    /** T8: identical rows in two different files are separate bookings. */
    public function test_identical_rows_in_two_files_both_import(): void
    {
        ['customer' => $customer, 'user' => $user, 'import' => $import1] = $this->createFixtures();
        $import2 = TransactionImport::factory()->create([
            'imported_by' => $user->id,
            'status' => TransactionImportStatus::Pending->value,
        ]);

        $service = $this->createImportService('999999');
        $row = "{$customer->id},Buy,USD,100,4.0,Business,Salary,MAIN";

        $csv1 = $this->createCsv($row);
        $csv2 = $this->createCsv($row);

        try {
            $service->process($import1, $csv1);
            $service->process($import2, $csv2);
        } finally {
            unlink($csv1);
            unlink($csv2);
        }

        $this->assertSame(1, $import1->refresh()->success_count);
        $this->assertSame(1, $import2->refresh()->success_count);
        $this->assertDatabaseCount('transactions', 2);
    }

    /** T7: later rows must see earlier rows' MYR deductions. */
    public function test_import_buy_fails_when_live_myr_insufficient(): void
    {
        ['customer' => $customer, 'import' => $import] = $this->createFixtures();

        $service = $this->createImportService('999999');
        // MYR drawer holds 100,000: row 1 books 80,000 out, leaving 20,000 —
        // row 2's 40,000 must fail even though opening_balance is 100,000.
        $csv = $this->createCsvRows([
            "{$customer->id},Buy,USD,20000,4.0,Business,Salary,MAIN",
            "{$customer->id},Buy,USD,10000,4.0,Business,Salary,MAIN",
        ]);

        try {
            $service->process($import, $csv);
        } finally {
            unlink($csv);
        }

        $import->refresh();
        $this->assertSame(1, $import->success_count);
        $this->assertSame(1, $import->error_count);
        $this->assertStringContainsString(
            'Insufficient MYR balance in till for buy transaction',
            $import->error_details[0]['error']
        );
        $this->assertDatabaseCount('transactions', 1);
    }
}
