<?php

namespace Tests\Feature\Commands;

use App\Jobs\ImportSanctionsJob;
use App\Models\Compliance\SanctionList;
use App\Models\Customer;
use App\Models\User;
use App\Services\Accounting\MonthEndCloseService;
use App\Services\Compliance\SanctionsImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OpsCommandsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeSanctionList(array $overrides = []): SanctionList
    {
        return SanctionList::create(array_merge([
            'name' => 'UN Consolidated',
            'slug' => 'un-consolidated',
            'list_type' => 'UNSCR',
            'source_url' => 'https://example.test/un.xml',
            'uploaded_by' => User::factory()->create()->id,
            'is_active' => true,
        ], $overrides));
    }

    #[Test]
    public function rescreen_screens_stale_customers_and_stamps_timestamp(): void
    {
        $customer = Customer::factory()->create([
            'is_active' => true,
            'sanctions_screened_at' => null,
        ]);

        $exit = Artisan::call('compliance:rescreen', ['--days' => 30]);

        $this->assertSame(0, $exit);
        $this->assertNotNull($customer->fresh()->sanctions_screened_at);
    }

    #[Test]
    public function rescreen_dry_run_does_not_stamp_customers(): void
    {
        $customer = Customer::factory()->create([
            'is_active' => true,
            'sanctions_screened_at' => null,
        ]);

        $exit = Artisan::call('compliance:rescreen', ['--days' => 30, '--dry-run' => true]);

        $this->assertSame(0, $exit);
        $this->assertNull($customer->fresh()->sanctions_screened_at);
    }

    #[Test]
    public function month_end_fails_when_preflight_checks_fail(): void
    {
        $service = Mockery::mock(MonthEndCloseService::class);
        $service->shouldReceive('preFlightChecks')
            ->once()
            ->andReturn(['passed' => false, 'failures' => ['open period missing']]);
        $this->app->instance(MonthEndCloseService::class, $service);

        $exit = Artisan::call('accounting:month-end', ['--date' => '2025-10-31']);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('Pre-flight checks failed', Artisan::output());
    }

    #[Test]
    public function month_end_force_runs_closing_as_system_user(): void
    {
        $systemUser = User::factory()->create(['is_active' => true]);
        config(['cems.system_user_id' => $systemUser->id]);

        $service = Mockery::mock(MonthEndCloseService::class);
        $service->shouldReceive('runMonthEndClosing')
            ->once()
            ->andReturn([
                'revaluation' => ['positions_updated' => 0, 'net_pnl' => '0.00'],
                'reports' => [],
                'period' => ['period_code' => '2025-10'],
            ]);
        $this->app->instance(MonthEndCloseService::class, $service);

        $exit = Artisan::call('accounting:month-end', [
            '--date' => '2025-10-31',
            '--force' => true,
        ]);

        $this->assertSame(0, $exit);
    }

    #[Test]
    public function sanctions_update_rejects_invalid_source(): void
    {
        $exit = Artisan::call('sanctions:update', ['--source' => 'bogus']);

        $this->assertSame(1, $exit);
    }

    #[Test]
    public function sanctions_update_dispatches_import_job_for_valid_source(): void
    {
        Queue::fake();

        $exit = Artisan::call('sanctions:update', ['--source' => 'un']);

        $this->assertSame(0, $exit);
        Queue::assertPushed(ImportSanctionsJob::class);
    }

    #[Test]
    public function sanctions_import_single_list_invokes_service(): void
    {
        $list = $this->makeSanctionList();

        $service = Mockery::mock(SanctionsImportService::class);
        $service->shouldReceive('import')
            ->once()
            ->with(Mockery::on(fn ($arg) => $arg->id === $list->id), Mockery::any())
            ->andReturn(['created' => 0, 'updated' => 0, 'deactivated' => 0, 'errors' => 0]);
        $this->app->instance(SanctionsImportService::class, $service);

        $exit = Artisan::call('sanctions:import', ['--list' => (string) $list->id]);

        $this->assertSame(0, $exit);
    }

    #[Test]
    public function sanctions_import_unknown_list_fails(): void
    {
        $exit = Artisan::call('sanctions:import', ['--list' => '99999']);

        $this->assertSame(1, $exit);
    }

    #[Test]
    public function display_and_housekeeping_commands_run_clean(): void
    {
        $this->assertSame(0, Artisan::call('sanctions:status'));
        $this->assertSame(0, Artisan::call('backup:list'));
        $this->assertSame(0, Artisan::call('sanctions:prune'));
        $this->assertSame(0, Artisan::call('backup:clean'));
    }

    #[Test]
    public function monitor_status_exits_with_health_code(): void
    {
        // Exit code is the health signal itself: 0 ok / 1 warning / 2 critical.
        $exit = Artisan::call('monitor:status');

        $this->assertContains($exit, [0, 1, 2]);
        $this->assertNotEmpty(Artisan::output());
    }
}
