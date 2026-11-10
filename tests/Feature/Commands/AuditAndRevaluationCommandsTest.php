<?php

namespace Tests\Feature\Commands;

use App\Services\Accounting\RevaluationNotificationService;
use App\Services\Accounting\RevaluationService;
use App\Services\Audit\AuditChainService;
use App\Services\Reporting\ExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuditAndRevaluationCommandsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function audit_verify_passes_on_empty_chain(): void
    {
        $this->assertSame(0, Artisan::call('audit:verify'));
    }

    #[Test]
    public function audit_verify_reports_broken_chain_with_exit_1(): void
    {
        $service = $this->mock(AuditChainService::class);
        $service->shouldReceive('verifyChainIntegrity')
            ->once()
            ->with(null)
            ->andReturn(['valid' => false, 'message' => 'Chain broken at entry 42']);

        $this->assertSame(1, Artisan::call('audit:verify'));
    }

    #[Test]
    public function audit_verify_forwards_limit_to_service(): void
    {
        $service = $this->mock(AuditChainService::class);
        $service->shouldReceive('verifyChainIntegrity')
            ->once()
            ->with(50)
            ->andReturn(['valid' => true, 'message' => 'OK']);

        $this->assertSame(0, Artisan::call('audit:verify', ['--limit' => 50]));
    }

    #[Test]
    public function revaluation_run_skips_when_not_month_end(): void
    {
        $service = $this->mock(RevaluationService::class);
        $service->shouldNotReceive('runRevaluationWithJournal');

        $this->assertSame(0, Artisan::call('revaluation:run'));
    }

    #[Test]
    public function revaluation_run_force_executes_and_reports(): void
    {
        $service = $this->mock(RevaluationService::class);
        $service->shouldReceive('runRevaluationWithJournal')
            ->once()
            ->andReturn([
                'results' => [['currency' => 'USD', 'pnl' => '10.00']],
                'positions_updated' => 1,
                'net_pnl' => '10.00',
            ]);

        $export = $this->mock(ExportService::class);
        $export->shouldReceive('toCSV')->once()->andReturn('storage/app/exports/Revaluation_test.csv');

        $notify = $this->mock(RevaluationNotificationService::class);
        $notify->shouldReceive('sendRevaluationNotification')->once();

        $this->assertSame(0, Artisan::call('revaluation:run', ['--force' => true]));
    }

    #[Test]
    public function revaluation_run_returns_1_when_service_throws(): void
    {
        $service = $this->mock(RevaluationService::class);
        $service->shouldReceive('runRevaluationWithJournal')
            ->once()
            ->andThrow(new \RuntimeException('ledger unavailable'));

        $this->assertSame(1, Artisan::call('revaluation:run', ['--force' => true]));
    }
}
