<?php

namespace Tests\Feature;

use App\Enums\BiAlertComparison;
use App\Enums\Permission;
use App\Enums\ReportRunStatus;
use App\Enums\ReportType;
use App\Enums\SystemAlertLevel;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Bi\BiAlertRule;
use App\Models\Branch;
use App\Models\ReportGenerated;
use App\Models\ReportRun;
use App\Models\ReportSchedule;
use App\Models\SystemAlert;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\ReportEmailNotification;
use App\Services\Bi\AnalyticsSnapshotBuilder;
use App\Services\Bi\BiAlertEvaluator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 4 coverage: BI CSV exports, scheduled BI delivery through the
 * existing report_schedules/report_runs pipeline, and alert-rule
 * evaluation over the rollups.
 */
class BiDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeTransaction(array $overrides = []): Transaction
    {
        $createdAt = $overrides['created_at'] ?? null;
        unset($overrides['created_at']);

        $transaction = Transaction::factory()->create(array_merge([
            'status' => TransactionStatus::Completed->value,
            'type' => TransactionType::Buy->value,
            'currency_code' => 'USD',
            'amount_myr' => '100.0000',
            'quantity' => '25.0000',
            'rate' => '4.00000000',
        ], $overrides));

        if ($createdAt !== null) {
            $transaction->forceFill(['created_at' => Carbon::parse($createdAt)])->saveQuietly();
        }

        return $transaction;
    }

    protected function rebuild(string $from, string $to): void
    {
        app(AnalyticsSnapshotBuilder::class)->rebuildRange(Carbon::parse($from), Carbon::parse($to));
    }

    #[Test]
    public function export_requires_authentication_and_permission(): void
    {
        $this->post(route('bi.export'), ['type' => 'currencies'])->assertRedirect();

        $teller = User::factory()->teller()->create();
        $this->actingAs($teller)
            ->post(route('bi.export'), ['type' => 'currencies'])
            ->assertForbidden();
    }

    #[Test]
    public function export_rejects_unknown_type(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('bi.export'), ['type' => 'bogus'])
            ->assertSessionHasErrors('type');
    }

    #[Test]
    public function currencies_export_downloads_csv_with_breakdown_rows(): void
    {
        $admin = User::factory()->admin()->create();
        $branch = Branch::factory()->create();
        $this->makeTransaction(['branch_id' => $branch->id, 'currency_code' => 'USD', 'amount_myr' => '600.0000', 'created_at' => today()]);
        $this->makeTransaction(['branch_id' => $branch->id, 'currency_code' => 'EUR', 'amount_myr' => '400.0000', 'created_at' => today()]);
        $this->rebuild(today()->toDateString(), today()->toDateString());

        $this->actingAs($admin)
            ->post(route('bi.export'), [
                'type' => 'currencies',
                'start_date' => today()->toDateString(),
                'end_date' => today()->toDateString(),
            ])
            ->assertOk();

        $file = 'reports/BI_Currencies_'.today()->toDateString().'_'.today()->toDateString().'.csv';
        $this->assertTrue(Storage::exists($file), "Expected {$file} in reports/");
        $csv = Storage::get($file);
        $this->assertStringContainsString('USD', $csv);
        $this->assertStringContainsString('EUR', $csv);
        $this->assertStringContainsString('600', $csv);
        $this->assertStringContainsString('400', $csv);
    }

    #[Test]
    public function branches_export_is_scoped_for_branch_pinned_viewers(): void
    {
        $manager = User::factory()->manager()->create();
        $manager->branch->update(['name' => 'Own Branch']);
        $other = Branch::factory()->create(['name' => 'Foreign Branch']);
        $this->makeTransaction(['branch_id' => $manager->branch_id, 'amount_myr' => '400.0000', 'created_at' => today()]);
        $this->makeTransaction(['branch_id' => $other->id, 'amount_myr' => '800.0000', 'created_at' => today()]);
        $this->rebuild(today()->toDateString(), today()->toDateString());

        $this->actingAs($manager)
            ->post(route('bi.export'), [
                'type' => 'branches',
                'start_date' => today()->toDateString(),
                'end_date' => today()->toDateString(),
            ])
            ->assertOk();

        $file = 'reports/BI_Branches_'.today()->toDateString().'_'.today()->toDateString().'.csv';
        $this->assertTrue(Storage::exists($file), "Expected {$file} in reports/");
        $csv = Storage::get($file);
        $this->assertStringContainsString('Own Branch', $csv);
        $this->assertStringNotContainsString('Foreign Branch', $csv);
        $this->assertStringNotContainsString('800', $csv);
    }

    #[Test]
    public function alert_rules_page_lists_rules_and_create_form_by_permission(): void
    {
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->manager()->create();

        BiAlertRule::create([
            'metric' => 'cancel_rate_pct',
            'comparison' => 'gte',
            'threshold' => '25.0',
            'level' => 'warning',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('bi.alerts'))
            ->assertOk()
            ->assertSee('Alert Rules')
            ->assertSee('New Rule')
            ->assertSee('Cancel rate %');

        // Manager holds view_bi_dashboard but not manage_report_schedules.
        $this->actingAs($manager)
            ->get(route('bi.alerts'))
            ->assertOk()
            ->assertSee('Cancel rate %')
            ->assertDontSee('New Rule');
    }

    #[Test]
    public function alert_rule_store_and_destroy_enforce_manage_permission(): void
    {
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)
            ->post(route('bi.alerts.store'), [
                'metric' => 'volume_myr',
                'comparison' => 'lt',
                'threshold' => '1000',
                'level' => 'warning',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('bi_alert_rules', ['metric' => 'volume_myr']);

        $this->actingAs($admin)
            ->post(route('bi.alerts.store'), [
                'metric' => 'volume_myr',
                'comparison' => 'lt',
                'threshold' => '1000',
                'level' => 'critical',
                'notification_recipients' => "boss@example.com\nops@example.com",
            ])
            ->assertRedirect(route('bi.alerts'));

        $rule = BiAlertRule::first();
        $this->assertNotNull($rule);
        $this->assertSame(SystemAlertLevel::Critical, $rule->level);
        $this->assertSame(BiAlertComparison::Lt, $rule->comparison);
        $this->assertSame(['boss@example.com', 'ops@example.com'], $rule->notification_recipients);

        $this->actingAs($admin)
            ->delete(route('bi.alerts.destroy', $rule))
            ->assertRedirect(route('bi.alerts'));

        $this->assertDatabaseMissing('bi_alert_rules', ['id' => $rule->id]);
    }

    #[Test]
    public function alert_rule_store_validates_metric_and_comparison(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('bi.alerts.store'), [
                'metric' => 'nonexistent_metric',
                'comparison' => 'gte',
                'threshold' => '1',
                'level' => 'warning',
            ])
            ->assertSessionHasErrors('metric');
    }

    #[Test]
    public function evaluator_raises_alert_on_breach_and_respects_cooldown(): void
    {
        $admin = User::factory()->admin()->create();
        $branch = Branch::factory()->create();

        // Yesterday: 1 completed + 1 cancelled => 50% cancel rate.
        $this->makeTransaction(['branch_id' => $branch->id, 'status' => TransactionStatus::Completed->value, 'created_at' => Carbon::yesterday()]);
        $this->makeTransaction(['branch_id' => $branch->id, 'status' => TransactionStatus::Cancelled->value, 'created_at' => Carbon::yesterday()]);
        $this->rebuild(Carbon::yesterday()->toDateString(), Carbon::yesterday()->toDateString());

        $rule = BiAlertRule::create([
            'metric' => 'cancel_rate_pct',
            'comparison' => 'gte',
            'threshold' => '25.0',
            'level' => 'warning',
            'created_by' => $admin->id,
        ]);

        $evaluator = app(BiAlertEvaluator::class);
        $counts = $evaluator->evaluateDay(Carbon::yesterday());

        $this->assertSame(1, $counts['breached']);
        $this->assertSame(1, $counts['alerted']);
        $this->assertDatabaseHas('system_alerts', ['source' => 'bi_alert_rule', 'level' => 'warning']);

        $alert = SystemAlert::where('source', 'bi_alert_rule')->latest('id')->first();
        $this->assertNotNull($alert);
        $this->assertSame($rule->id, $alert->metadata['rule_id'] ?? null);

        $rule->refresh();
        $this->assertNotNull($rule->last_triggered_at);
        $this->assertNotNull($rule->last_evaluated_at);

        // Second evaluation inside the cooldown window: still breaches, no new alert.
        $before = SystemAlert::count();
        $counts = $evaluator->evaluateDay(Carbon::yesterday());
        $this->assertSame(1, $counts['breached']);
        $this->assertSame(0, $counts['alerted']);
        $this->assertSame($before, SystemAlert::count());
    }

    #[Test]
    public function evaluator_does_not_alert_when_rule_is_satisfied(): void
    {
        $admin = User::factory()->admin()->create();
        $branch = Branch::factory()->create();

        $this->makeTransaction(['branch_id' => $branch->id, 'status' => TransactionStatus::Completed->value, 'created_at' => Carbon::yesterday()]);
        $this->rebuild(Carbon::yesterday()->toDateString(), Carbon::yesterday()->toDateString());

        BiAlertRule::create([
            'metric' => 'cancel_rate_pct',
            'comparison' => 'gte',
            'threshold' => '25.0',
            'level' => 'warning',
            'created_by' => $admin->id,
        ]);

        $counts = app(BiAlertEvaluator::class)->evaluateDay(Carbon::yesterday());

        $this->assertSame(0, $counts['breached']);
        $this->assertSame(0, $counts['alerted']);
        $this->assertDatabaseMissing('system_alerts', ['source' => 'bi_alert_rule']);
    }

    #[Test]
    public function evaluate_alerts_command_runs_and_reports_counts(): void
    {
        $admin = User::factory()->admin()->create();

        BiAlertRule::create([
            'metric' => 'tx_count',
            'comparison' => 'gt',
            'threshold' => '0',
            'level' => 'warning',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);
        BiAlertRule::create([
            'metric' => 'tx_count',
            'comparison' => 'gt',
            'threshold' => '0',
            'level' => 'warning',
            'is_active' => false,
            'created_by' => $admin->id,
        ]);

        $this->artisanCommand('bi:evaluate-alerts')
            ->expectsOutputToContain('Evaluated 1 rule(s)')
            ->assertSuccessful();
    }

    #[Test]
    public function due_bi_schedule_generates_completed_run_artifact_and_file(): void
    {
        $user = User::factory()->create();
        config(['cems.system_user_id' => $user->id]);

        $branch = Branch::factory()->create();
        $this->makeTransaction(['branch_id' => $branch->id, 'amount_myr' => '500.0000', 'created_at' => Carbon::yesterday()]);
        $this->rebuild(Carbon::yesterday()->toDateString(), Carbon::yesterday()->toDateString());

        $schedule = ReportSchedule::create([
            'report_type' => ReportType::BiDailyDigest,
            'cron_expression' => '0 6 * * *',
            'parameters' => null,
            'is_active' => true,
            'created_by' => $user->id,
        ]);
        $schedule->forceFill(['next_run_at' => now()->subHour()])->save();

        $this->artisanCommand('reports:process-schedules')->assertSuccessful();

        $run = ReportRun::where('schedule_id', $schedule->id)->first();
        $this->assertNotNull($run);
        $this->assertSame(ReportRunStatus::Completed, $run->status, 'Digest run failed: '.$run->error_message);
        $this->assertSame(ReportType::BiDailyDigest, $run->report_type);
        $this->assertNotNull($run->file_path);
        $this->assertTrue(Storage::exists($run->file_path));

        $csv = Storage::get($run->file_path);
        $this->assertStringContainsString('volume_myr', $csv);
        $this->assertStringContainsString('500', $csv);

        $artifact = ReportGenerated::where('file_path', $run->file_path)->first();
        $this->assertNotNull($artifact);
        $this->assertEquals(Carbon::yesterday()->toDateString(), $artifact->period_start->toDateString());

        $schedule->refresh();
        $this->assertTrue($schedule->next_run_at->isFuture());
    }

    #[Test]
    public function due_monthly_bi_schedule_covers_previous_month(): void
    {
        $user = User::factory()->create();
        config(['cems.system_user_id' => $user->id]);

        $schedule = ReportSchedule::create([
            'report_type' => ReportType::BiCurrencyBreakdown,
            'cron_expression' => '0 6 1 * *',
            'parameters' => null,
            'is_active' => true,
            'created_by' => $user->id,
        ]);
        $schedule->forceFill(['next_run_at' => now()->subHour()])->save();

        $this->artisanCommand('reports:process-schedules')->assertSuccessful();

        $run = ReportRun::where('schedule_id', $schedule->id)->first();
        $this->assertNotNull($run);
        $this->assertSame(ReportRunStatus::Completed, $run->status, 'Currency run failed: '.$run->error_message);
        $this->assertTrue(Storage::exists($run->file_path));

        $artifact = ReportGenerated::where('file_path', $run->file_path)->first();
        $this->assertNotNull($artifact);
        $this->assertEquals(now()->subMonthNoOverflow()->startOfMonth()->toDateString(), $artifact->period_start->toDateString());
        $this->assertEquals(now()->subMonthNoOverflow()->endOfMonth()->toDateString(), $artifact->period_end->toDateString());
    }

    #[Test]
    public function branch_scoped_manager_cannot_delete_consolidated_or_other_branch_rules(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();
        $manager = User::factory()->manager()->create(['branch_id' => $branchA->id]);

        // Grant manage_report_schedules to the manager role via the
        // authoritative matrix so the route middleware lets the request in.
        DB::table('role_permissions')->updateOrInsert(
            ['role' => $manager->role->value, 'permission' => Permission::ManageReportSchedules->value],
            ['granted' => true]
        );
        Cache::flush();

        $foreignRule = BiAlertRule::create([
            'metric' => 'volume_myr',
            'comparison' => 'lt',
            'threshold' => '1',
            'branch_id' => $branchB->id,
            'level' => 'warning',
            'is_active' => true,
        ]);
        $globalRule = BiAlertRule::create([
            'metric' => 'volume_myr',
            'comparison' => 'lt',
            'threshold' => '1',
            'branch_id' => null,
            'level' => 'warning',
            'is_active' => true,
        ]);

        $this->actingAs($manager)
            ->delete(route('bi.alerts.destroy', $foreignRule))
            ->assertForbidden();
        $this->actingAs($manager)
            ->delete(route('bi.alerts.destroy', $globalRule))
            ->assertForbidden();

        $this->assertDatabaseHas('bi_alert_rules', ['id' => $foreignRule->id]);
        $this->assertDatabaseHas('bi_alert_rules', ['id' => $globalRule->id]);
    }

    #[Test]
    public function completed_scheduled_run_emails_file_to_notification_recipients(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        config(['cems.system_user_id' => $user->id]);

        $this->makeTransaction(['amount_myr' => '500.0000', 'created_at' => Carbon::yesterday()]);
        $this->rebuild(Carbon::yesterday()->toDateString(), Carbon::yesterday()->toDateString());

        $schedule = ReportSchedule::create([
            'report_type' => ReportType::BiDailyDigest,
            'cron_expression' => '0 6 * * *',
            'parameters' => null,
            'notification_recipients' => ['ops@example.com'],
            'is_active' => true,
            'created_by' => $user->id,
        ]);
        $schedule->forceFill(['next_run_at' => now()->subHour()])->save();

        $this->artisanCommand('reports:process-schedules')->assertSuccessful();

        $run = ReportRun::where('schedule_id', $schedule->id)->first();
        $this->assertNotNull($run);
        $this->assertSame(ReportRunStatus::Completed, $run->status, 'Run failed: '.$run->error_message);
        $this->assertNotNull($run->emailed_at);

        Notification::assertSentOnDemand(
            ReportEmailNotification::class,
            fn ($notification, $channels, $notifiable) => in_array('ops@example.com', (array) ($notifiable->routes['mail'] ?? []), true)
        );
    }
}
