<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Currency;
use App\Models\User;
use Database\Seeders\SchemaSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * Guards against the destructive-schema footguns introduced when the
 * migrations directory was retired in favour of SchemaSeeder.
 */
class BusinessSetupGuardTest extends TestCase
{
    use DatabaseTransactions;

    public function test_business_setup_without_fresh_preserves_existing_data(): void
    {
        $branch = Branch::create([
            'code' => 'GRD-'.uniqid(),
            'name' => 'Schema Guard Test Branch',
        ]);

        $this->artisan('business:setup')
            ->expectsConfirmation('Do you want to use default exchange rates?', 'yes')
            ->expectsConfirmation('Do you want to initialize branch currency pools with default amounts?', 'yes')
            ->expectsConfirmation('Do you want to pre-allocate currency to tellers?', 'yes')
            ->expectsConfirmation('Do you want to create opening balance journal entries?', 'no')
            ->assertExitCode(0);

        $this->assertDatabaseHas('branches', ['id' => $branch->id]);
    }

    public function test_business_setup_seed_only_leaves_schema_and_data_intact(): void
    {
        $branch = Branch::create([
            'code' => 'GRD-'.uniqid(),
            'name' => 'Seed Only Guard Test Branch',
        ]);

        $this->artisan('business:setup', ['--seed-only' => true])
            ->expectsConfirmation('Do you want to use default exchange rates?', 'yes')
            ->expectsConfirmation('Do you want to initialize branch currency pools with default amounts?', 'yes')
            ->expectsConfirmation('Do you want to pre-allocate currency to tellers?', 'yes')
            ->expectsConfirmation('Do you want to create opening balance journal entries?', 'no')
            ->assertExitCode(0);

        $this->assertDatabaseHas('branches', ['id' => $branch->id]);
    }

    public function test_schema_seeder_drops_legacy_migrations_table(): void
    {
        Schema::dropIfExists('migrations');
        Schema::create('migrations', function ($table) {
            $table->increments('id');
            $table->string('migration');
            $table->integer('batch');
        });

        $this->assertTrue(Schema::hasTable('migrations'));

        SchemaSeeder::seedNow($this->app);

        $this->assertFalse(Schema::hasTable('migrations'));
        $this->assertTrue(Schema::hasTable('users'));
    }

    public function test_database_seeder_refuses_to_run_against_populated_database(): void
    {
        User::factory()->create();

        try {
            Artisan::call('db:seed');
            $this->fail('DatabaseSeeder should refuse to run against a populated database.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Database is not empty', $e->getMessage());
        }

        $this->assertDatabaseHas('users', ['id' => User::first()->id]);
    }

    public function test_database_seeder_runs_on_empty_database(): void
    {
        $this->assertSame(0, User::count());

        $exitCode = Artisan::call('db:seed');

        $this->assertSame(0, $exitCode);
        $this->assertTrue(User::count() > 0);
        $this->assertTrue(Currency::count() > 0);
    }
}
