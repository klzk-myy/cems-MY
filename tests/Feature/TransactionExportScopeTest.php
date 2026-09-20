<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Reporting\TransactionExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 5 (X1): the CSV export obeys the same branch isolation as the
 * transaction index — a branch-scoped user can never pull another branch's
 * rows, whether branch_id is omitted or forged.
 */
class TransactionExportScopeTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branchA;

    protected Branch $branchB;

    protected User $managerA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branchA = Branch::factory()->create();
        $this->branchB = Branch::factory()->create();
        $this->managerA = User::factory()->create([
            'role' => UserRole::Manager,
            'branch_id' => $this->branchA->id,
        ]);
    }

    private function seedTransactions(): void
    {
        Transaction::factory()->count(3)->create([
            'branch_id' => $this->branchA->id,
        ]);
        Transaction::factory()->count(2)->create([
            'branch_id' => $this->branchB->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<int, string>> CSV data rows (header skipped)
     */
    private function readExport(array $filters, User $user): array
    {
        $path = app(TransactionExportService::class)->exportTransactions($filters, $user);

        $rows = array_map('str_getcsv', explode("\n", trim(Storage::get($path))));
        array_shift($rows); // header
        Storage::delete($path);

        return $rows;
    }

    #[Test]
    public function manager_export_is_limited_to_own_branch(): void
    {
        $this->seedTransactions();

        $rows = $this->readExport([], $this->managerA);

        $this->assertCount(3, $rows);
        $ids = array_column($rows, 0);
        $this->assertEquals(
            Transaction::where('branch_id', $this->branchA->id)->orderByDesc('id')->pluck('id')->map(fn ($id) => (string) $id)->all(),
            $ids
        );
    }

    #[Test]
    public function export_omitting_branch_id_does_not_leak_other_branches(): void
    {
        $this->seedTransactions();

        $this->actingAs($this->managerA)
            ->withSession($this->passwordConfirmedSession())
            ->post(route('transactions.export.export'), [])
            ->assertOk()
            ->assertHeader('content-disposition');

        // The service-scope assertion is authoritative; the HTTP test above
        // proves the route wires the user through. Direct service check:
        $rows = $this->readExport([], $this->managerA);
        $this->assertCount(3, $rows);
        $this->assertEmpty(
            Transaction::where('branch_id', $this->branchB->id)
                ->whereIn('id', array_column($rows, 0))
                ->get()
        );
    }

    #[Test]
    public function export_with_foreign_branch_id_is_clamped_to_own_branch(): void
    {
        $this->seedTransactions();

        $rows = $this->readExport(['branch_id' => $this->branchB->id], $this->managerA);

        // A forged foreign branch_id intersects with the user's own scope
        // and yields an empty export — it can never widen the result set.
        $this->assertCount(0, $rows);
    }

    #[Test]
    public function admin_export_sees_all_branches(): void
    {
        $this->seedTransactions();

        $admin = User::factory()->create(['role' => UserRole::Admin, 'branch_id' => $this->branchA->id]);

        $rows = $this->readExport([], $admin);

        $this->assertCount(5, $rows);
    }
}
