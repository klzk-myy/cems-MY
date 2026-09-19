<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The db:rename-amount-columns installer upgrades live databases to the
 * canonical amount/quantity lexicon. Fresh schemas (SchemaSeeder) already
 * carry the new names, so the command is a no-op there; the upgrade path is
 * covered by reversing one column first and letting the installer rename
 * it back with data preserved.
 */
class RenameAmountColumnsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_is_a_no_op_on_the_current_schema(): void
    {
        $this->artisanCommand('db:rename-amount-columns')->assertSuccessful();

        $this->assertTrue(Schema::hasColumn('transactions', 'quantity'));
        $this->assertTrue(Schema::hasColumn('transactions', 'amount_myr'));
        $this->assertTrue(Schema::hasColumn('teller_allocations', 'loaded_quantity'));
    }

    #[Test]
    public function it_renames_a_legacy_column_and_preserves_data(): void
    {
        // Simulate a live database that still carries the pre-rename name.
        Schema::table('expenses', function ($table) {
            $table->renameColumn('amount_myr', 'amount');
        });

        $user = User::factory()->create();
        DB::table('expenses')->insert([
            'branch_id' => null,
            'account_code' => '6299',
            'category' => 'Operations',
            'description' => 'Legacy row',
            'amount' => '250.0000',
            'expense_date' => '2026-09-19',
            'created_by' => $user->id,
        ]);

        $this->artisanCommand('db:rename-amount-columns')->assertSuccessful();

        $this->assertFalse(Schema::hasColumn('expenses', 'amount'));
        $this->assertTrue(Schema::hasColumn('expenses', 'amount_myr'));
        $this->assertSame(
            '250.0000',
            number_format((float) DB::table('expenses')->value('amount_myr'), 4, '.', '')
        );

        // Idempotent: a second run leaves the renamed column untouched.
        $this->artisanCommand('db:rename-amount-columns')->assertSuccessful();
        $this->assertSame(
            '250.0000',
            number_format((float) DB::table('expenses')->value('amount_myr'), 4, '.', '')
        );
    }
}
