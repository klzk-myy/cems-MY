<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\StockTransfer;
use App\Models\User;
use App\Policies\StockTransferPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * S4: legacy stock_transfers rows carry only name snapshots; the installer
 * backfills the real branch FKs, and new writes populate them directly.
 */
class StockTransferBranchFksTest extends TestCase
{
    use RefreshDatabase;

    private function legacyRow(Branch $source, Branch $destination): int
    {
        // Simulate a pre-FK row: names only, no branch ids.
        return (int) DB::table('stock_transfers')->insertGetId([
            'transfer_number' => 'ST-LEGACY-'.fake()->unique()->numberBetween(1000, 9999),
            'type' => StockTransfer::TYPE_STANDARD,
            'status' => 'requested',
            'source_branch_id' => null,
            'destination_branch_id' => null,
            'source_branch_name' => $source->name,
            'destination_branch_name' => $destination->name,
            'requested_by' => User::factory()->create()->id,
            'requested_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    #[Test]
    public function install_command_backfills_branch_fks_from_name_snapshots(): void
    {
        $source = Branch::factory()->create(['name' => 'KL Sentral']);
        $destination = Branch::factory()->create(['name' => 'Penang']);

        $rowId = $this->legacyRow($source, $destination);

        $this->assertSame(0, Artisan::call('stock-transfers:install-branch-fks'));

        $row = DB::table('stock_transfers')->where('id', $rowId)->first();
        $this->assertSame($source->id, (int) $row->source_branch_id);
        $this->assertSame($destination->id, (int) $row->destination_branch_id);
    }

    #[Test]
    public function install_command_leaves_unresolvable_names_null(): void
    {
        $ghost = Branch::factory()->create();
        $real = Branch::factory()->create();

        $rowId = $this->legacyRow($ghost, $real);
        DB::table('stock_transfers')->where('id', $rowId)->update([
            'source_branch_name' => 'No Such Branch',
        ]);

        $this->assertSame(0, Artisan::call('stock-transfers:install-branch-fks'));

        $row = DB::table('stock_transfers')->where('id', $rowId)->first();
        $this->assertNull($row->source_branch_id);
        $this->assertSame($real->id, (int) $row->destination_branch_id);
    }

    #[Test]
    public function install_command_is_idempotent(): void
    {
        $source = Branch::factory()->create();
        $destination = Branch::factory()->create();
        $this->legacyRow($source, $destination);

        $this->assertSame(0, Artisan::call('stock-transfers:install-branch-fks'));
        $this->assertSame(0, Artisan::call('stock-transfers:install-branch-fks'));
    }

    #[Test]
    public function policy_uses_fk_identity_for_legacy_and_new_rows(): void
    {
        $source = Branch::factory()->create();
        $destination = Branch::factory()->create();
        $other = Branch::factory()->create();

        $manager = User::factory()->create(['branch_id' => $source->id]);
        $outsider = User::factory()->create(['branch_id' => $other->id]);

        // New row: FKs populated.
        $new = StockTransfer::factory()->create([
            'source_branch_id' => $source->id,
            'destination_branch_id' => $destination->id,
            'source_branch_name' => 'Stale Snapshot Name',
            'destination_branch_name' => $destination->name,
        ]);

        // Legacy row: names only.
        $legacyId = $this->legacyRow($source, $destination);
        $legacy = StockTransfer::findOrFail($legacyId);

        $policy = app(StockTransferPolicy::class);

        // FK wins over the stale name snapshot.
        $this->assertTrue($policy->view($manager, $new));
        $this->assertTrue($policy->view($manager, $legacy));
        $this->assertFalse($policy->view($outsider, $new));
        $this->assertFalse($policy->view($outsider, $legacy));
    }
}
