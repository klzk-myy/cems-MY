<?php

namespace Tests\Feature\Jobs;

use App\Enums\CddLevel;
use App\Enums\ReportRunStatus;
use App\Enums\TransactionStatus;
use App\Events\ReportGenerated;
use App\Events\TransactionCancelled;
use App\Jobs\Accounting\ReconcileDeferredAccountingJob;
use App\Jobs\ImportSanctionsJob;
use App\Listeners\ReportGeneratedListener;
use App\Listeners\TransactionCancelledListener;
use App\Models\Compliance\SanctionList;
use App\Models\ReportRun;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\TransactionAccountingService;
use App\Services\AuditService;
use App\Services\Compliance\SanctionsImportService;
use App\Services\System\MathService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReconcileAndImportJobsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function reconcile_job_is_quiet_when_no_eligible_transactions(): void
    {
        Transaction::factory()->create([
            'status' => TransactionStatus::Completed->value,
            'cdd_level' => CddLevel::Standard->value,
            'journal_entry_id' => null,
        ]);

        $accounting = Mockery::mock(TransactionAccountingService::class);
        $accounting->shouldNotReceive('createDeferredAccountingEntries');

        $job = new ReconcileDeferredAccountingJob(
            app(MathService::class),
            $accounting,
            app(AuditService::class),
        );

        $job->handle();

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function reconcile_job_only_targets_enhanced_completed_transactions(): void
    {
        $enhanced = Transaction::factory()->create([
            'status' => TransactionStatus::Completed->value,
            'cdd_level' => CddLevel::Enhanced->value,
            'journal_entry_id' => null,
        ]);
        $standard = Transaction::factory()->create([
            'status' => TransactionStatus::Completed->value,
            'cdd_level' => CddLevel::Standard->value,
            'journal_entry_id' => null,
        ]);

        $accounting = Mockery::mock(TransactionAccountingService::class);
        $accounting->shouldReceive('createDeferredAccountingEntries')
            ->once()
            ->with($enhanced->id);
        $accounting->shouldNotReceive('createDeferredAccountingEntries')
            ->with($standard->id);

        $job = new ReconcileDeferredAccountingJob(
            app(MathService::class),
            $accounting,
            app(AuditService::class),
        );

        $job->handle();
    }

    #[Test]
    public function import_sanctions_job_resolves_list_by_slug(): void
    {
        $user = User::factory()->create();
        $list = SanctionList::create([
            'name' => 'OFAC SDN',
            'slug' => 'ofac-sdn',
            'list_type' => 'UNSCR',
            'source_url' => 'https://example.test/sdn.csv',
            'uploaded_by' => $user->id,
            'is_active' => true,
        ]);

        $service = Mockery::mock(SanctionsImportService::class);
        $service->shouldReceive('import')
            ->once()
            ->with(Mockery::on(fn ($arg) => $arg->id === $list->id), false);

        (new ImportSanctionsJob(listSlug: 'ofac-sdn'))->handle($service);
    }

    #[Test]
    public function import_sanctions_job_falls_back_to_active_auto_updatable_list(): void
    {
        $user = User::factory()->create();
        SanctionList::create([
            'name' => 'Manual List',
            'slug' => 'manual-list',
            'list_type' => 'Internal',
            'source_url' => null,
            'uploaded_by' => $user->id,
            'is_active' => true,
        ]);
        $autoList = SanctionList::create([
            'name' => 'UN Consolidated',
            'slug' => 'un-consolidated',
            'list_type' => 'UNSCR',
            'source_url' => 'https://example.test/un.xml',
            'uploaded_by' => $user->id,
            'is_active' => true,
        ]);

        $service = Mockery::mock(SanctionsImportService::class);
        $service->shouldReceive('import')
            ->once()
            ->with(Mockery::on(fn ($arg) => $arg->id === $autoList->id), false);

        (new ImportSanctionsJob)->handle($service);
    }

    #[Test]
    public function import_sanctions_job_noops_when_no_list_available(): void
    {
        $service = Mockery::mock(SanctionsImportService::class);
        $service->shouldNotReceive('import');

        (new ImportSanctionsJob)->handle($service);

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function transaction_cancelled_listener_writes_audit_entry(): void
    {
        $user = User::factory()->create();
        $transaction = Transaction::factory()->create([
            'status' => TransactionStatus::Cancelled->value,
        ]);

        $audit = Mockery::mock(AuditService::class);
        $audit->shouldReceive('logWithSeverity')
            ->once()
            ->with('transaction_cancelled', Mockery::type('array'), Mockery::any());

        (new TransactionCancelledListener($audit))(
            new TransactionCancelled($transaction, 'duplicate entry', $user->id)
        );
    }

    #[Test]
    public function report_generated_listener_logs_report_run(): void
    {
        $reportRun = ReportRun::factory()->create([
            'status' => ReportRunStatus::Completed,
            'row_count' => 10,
        ]);
        $spy = Log::spy();

        (new ReportGeneratedListener)(new ReportGenerated($reportRun));

        $spy->shouldHaveReceived('info')->once();
    }
}
