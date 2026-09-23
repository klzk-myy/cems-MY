<?php

namespace Tests\Feature;

use App\Actions\Transaction\RequestCancellationAction;
use App\Enums\AlertPriority;
use App\Enums\ComplianceCasePriority;
use App\Enums\ReportRunStatus;
use App\Enums\ReportType;
use App\Enums\StockReservationStatus;
use App\Enums\SystemHealthCheckStatus;
use App\Enums\TellerAllocationStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Exceptions\Domain\PermissionDeniedException;
use App\Http\Controllers\Compliance\PepApprovalController;
use App\Http\Middleware\QueryLogging;
use App\Http\Requests\RejectPepApprovalRequest;
use App\Models\Branch;
use App\Models\Compliance\Alert;
use App\Models\Compliance\FlaggedTransaction;
use App\Models\Counter;
use App\Models\CurrencyPosition;
use App\Models\Customer;
use App\Models\ExchangeRate;
use App\Models\PepApprovalRequest;
use App\Models\ReportSchedule;
use App\Models\StockReservation;
use App\Models\SystemHealthCheck;
use App\Models\SystemLog;
use App\Models\TellerAllocation;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Audit\AuditChainService;
use App\Services\Compliance\AlertTriageService;
use App\Services\Compliance\Parsing\OpenSanctionsJsonParser;
use App\Services\Customer\CustomerService;
use App\Services\Reporting\ReportSchedulingService;
use App\Services\System\LogRotationService;
use App\Services\System\QueryLoggingService;
use App\Services\System\SystemHealthService;
use App\Services\Transaction\TransactionCancellationService;
use App\Services\Transaction\TransactionReversalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Regression tests for the functional-cluster architecture review fixes.
 * Each test pins one reviewed defect so the fix cannot silently regress.
 */
class ReviewRegressionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function system_health_status_summary_counts_backed_enum_statuses(): void
    {
        // Pre-fix this threw TypeError: Cannot access offset of type
        // SystemHealthCheckStatus on array (PHP 8.3 backed enum as key).
        SystemHealthCheck::factory()->ok()->forCheck('database')->create();
        SystemHealthCheck::factory()->warning()->forCheck('cache')->create();
        SystemHealthCheck::factory()->critical()->forCheck('queue')->create();

        $summary = app(SystemHealthService::class)->getStatusSummary();

        $this->assertSame(1, $summary['summary']['ok']);
        $this->assertSame(1, $summary['summary']['warning']);
        $this->assertSame(1, $summary['summary']['critical']);
        $this->assertSame('critical', $summary['overall_status']);
    }

    #[Test]
    public function report_schedule_actions_require_manage_permission(): void
    {
        // Pre-fix the controller had zero authorization on every action.
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $schedule = ReportSchedule::factory()->create();

        $this->actingAs($manager);

        $this->get(route('reports.schedules.index'))->assertForbidden();
        $this->post(route('reports.schedules.store'), ['report_type' => ReportType::Msb2->value, 'frequency' => 'daily'])->assertForbidden();
        $this->delete(route('reports.schedules.destroy', $schedule))->assertForbidden();
        $this->post(route('reports.schedules.pause', $schedule))->assertForbidden();
    }

    #[Test]
    public function report_schedule_actions_allow_admin(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->get(route('reports.schedules.index'))
            ->assertOk();
    }

    #[Test]
    public function query_logging_reraises_instead_of_redispatching_pipeline(): void
    {
        // Pre-fix the catch block called $next($request) again, re-running the
        // downstream pipeline (and its side effects) a second time.
        $middleware = new QueryLogging($this->createMock(QueryLoggingService::class));
        $request = Request::create('/test', 'GET');
        $calls = 0;

        try {
            $middleware->handle($request, function () use (&$calls) {
                $calls++;

                throw new \RuntimeException('boom');
            });

            $this->fail('Middleware must rethrow');
        } catch (\RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        $this->assertSame(1, $calls, 'Pipeline must run exactly once');
    }

    #[Test]
    public function my_stock_valuation_uses_decimal_math(): void
    {
        $teller = User::factory()->create(['role' => UserRole::Teller]);
        $branch = $teller->branch;

        TellerAllocation::factory()->create([
            'user_id' => $teller->id,
            'branch_id' => $branch->id,
            'currency_code' => 'USD',
            'allocated_quantity' => '10.0000',
            'status' => TellerAllocationStatus::Active->value,
            'session_date' => now()->toDateString(),
        ]);

        // Branch rate card: 4.5000 per USD. 10 USD × 4.5 = exactly 45.0000 —
        // a value float math can mangle on repeated operations.
        ExchangeRate::factory()->create([
            'currency_code' => 'USD',
            'branch_id' => $branch->id,
            'rate_buy' => '4.5000',
            'rate_sell' => '4.5000',
            'fetched_at' => now(),
        ]);

        $response = $this->actingAs($teller)->get(route('my-stock.index'));

        $response->assertOk();
        $valuation = $response->viewData('valuation');

        $this->assertEqualsWithDelta(45.0, $valuation['stock_myr'], 0.0001);
        $this->assertEqualsWithDelta(45.0, $valuation['total_myr'], 0.0001);
    }

    #[Test]
    public function analytics_monthly_trends_are_branch_scoped(): void
    {
        // Pre-fix AnalyticsController queried company-wide with no branch
        // filter — a branch manager saw every branch's volume.
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();
        $manager = User::factory()->create(['role' => UserRole::Manager, 'branch_id' => $branchA->id]);
        $customer = Customer::factory()->create();

        Transaction::factory()->create([
            'customer_id' => $customer->id,
            'branch_id' => $branchA->id,
            'type' => TransactionType::Buy->value,
            'currency_code' => 'USD',
            'quantity' => '100.0000',
            'amount_myr' => '500.0000',
            'status' => TransactionStatus::Completed->value,
            'created_at' => now(),
        ]);

        Transaction::factory()->create([
            'customer_id' => $customer->id,
            'branch_id' => $branchB->id,
            'type' => TransactionType::Buy->value,
            'currency_code' => 'USD',
            'quantity' => '1000.0000',
            'amount_myr' => '5000.0000',
            'status' => TransactionStatus::Completed->value,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($manager)->get('/reports/monthly-trends');

        $response->assertOk();
        $monthlyData = collect($response->viewData('monthlyData'));

        $totalVolume = (float) $monthlyData->sum('volume');

        $this->assertEqualsWithDelta(500.0, $totalVolume, 0.0001, 'Manager must only see own-branch volume');
    }

    #[Test]
    public function analytics_reversed_date_range_is_swapped(): void
    {
        // Pre-fix start > end silently produced an empty whereBetween.
        $customer = Customer::factory()->create();
        $transaction = Transaction::factory()->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::Buy->value,
            'currency_code' => 'USD',
            'quantity' => '100.0000',
            'amount_myr' => '500.0000',
            'status' => TransactionStatus::Completed->value,
            'created_at' => now()->subDays(5),
        ]);
        FlaggedTransaction::factory()->create([
            'customer_id' => $customer->id,
            'transaction_id' => $transaction->id,
            'flag_type' => 'Large_Amount',
            'created_at' => now()->subDays(5),
        ]);

        $response = $this->actingAs(User::factory()->create(['role' => UserRole::Admin]))
            ->get('/reports/compliance-summary?'.http_build_query([
                'start_date' => now()->toDateString(),
                'end_date' => now()->subMonth()->toDateString(),
            ]));

        $response->assertOk();

        $this->assertGreaterThan(0, $response->viewData('flaggedStats')['total'] ?? 0);
    }

    #[Test]
    public function report_scheduling_executes_every_report_type(): void
    {
        // Pre-fix executeReport() threw UnmatchError for the four ledger-backed
        // report types — the match only covered Msb2/Lmca/Qlvr/Plr.
        $service = app(ReportSchedulingService::class);
        $user = User::factory()->create(['role' => UserRole::Admin]);

        foreach (ReportType::cases() as $type) {
            $run = $service->generateReport($type, [], $user->id);

            $this->assertSame(
                ReportRunStatus::Completed->value,
                $run->status->value,
                "Report type {$type->value} must complete without UnmatchError"
            );
        }
    }

    #[Test]
    public function confirmation_reject_releases_stock_reservation(): void
    {
        // Pre-fix the reject path transitioned to Cancelled without the
        // compensating stock-release leg, stranding large Sell reservations.
        $teller = User::factory()->create(['role' => UserRole::Teller]);
        $compliance = User::factory()->create(['role' => UserRole::ComplianceOfficer, 'branch_id' => $teller->branch_id]);
        $customer = Customer::factory()->create([
            'sanction_hit' => false,
            'pep_status' => false,
            'risk_rating' => 'low',
        ]);
        $counter = Counter::factory()->create(['branch_id' => $teller->branch_id]);

        CurrencyPosition::create([
            'currency_code' => 'USD',
            'branch_id' => $teller->branch_id,
            'quantity' => '100000.0000',
            'average_cost' => '0.0000',
            'total_cost' => '0.0000',
            'current_rate' => '0.0000',
            'current_value' => '0.0000',
            'unrealized_gain_loss' => '0.0000',
        ]);

        // Large pending Sell (above the cdd.large_transaction threshold) so
        // the confirmation gate applies.
        $transaction = Transaction::factory()->create([
            'user_id' => $teller->id,
            'customer_id' => $customer->id,
            'branch_id' => $teller->branch_id,
            'till_id' => (string) $counter->code,
            'counter_id' => $counter->id,
            'type' => TransactionType::Sell->value,
            'currency_code' => 'USD',
            'quantity' => '1000.0000',
            'amount_myr' => '200000.0000',
            'status' => TransactionStatus::PendingApproval->value,
        ]);

        StockReservation::create([
            'transaction_id' => $transaction->id,
            'currency_code' => 'USD',
            'quantity' => '1000.0000',
            'status' => StockReservationStatus::Pending->value,
            'expires_at' => now()->addHours(24),
            'branch_id' => $teller->branch_id,
            'till_id' => (string) $counter->code,
            'created_by' => $compliance->id,
        ]);

        $this->actingAs($compliance);

        // Request the confirmation (GET confirm.show creates it), then reject.
        $this->get(route('transactions.confirm.show', $transaction))->assertOk();

        $this->post(route('transactions.confirm.store', $transaction), [
            'confirmation_action' => 'reject',
            'notes' => 'Rejected in review test',
        ])->assertRedirect();

        $transaction->refresh();
        $reservation = StockReservation::where('transaction_id', $transaction->id)->first();

        $this->assertSame(TransactionStatus::Cancelled->value, $transaction->status->value);
        $this->assertSame(StockReservationStatus::Released->value, $reservation->status->value);
    }

    #[Test]
    public function reversal_writes_sealed_audit_record(): void
    {
        // Pre-fix the state machine had no AuditService and no cache
        // invalidation on reversal.
        $customer = Customer::factory()->create();
        $reverser = User::factory()->create(['role' => UserRole::ComplianceOfficer]);

        $branch = $reverser->branch ?? Branch::factory()->create();
        $reverser->branch_id = $branch->id;
        $reverser->save();

        CurrencyPosition::create([
            'currency_code' => 'USD',
            'branch_id' => $branch->id,
            'quantity' => '100000.0000',
            'average_cost' => '4.0000',
            'total_cost' => '40000.0000',
            'current_rate' => '4.5000',
            'current_value' => '45000.0000',
            'unrealized_gain_loss' => '5000.0000',
        ]);

        $transaction = Transaction::factory()->create([
            'user_id' => User::factory()->create(['role' => UserRole::Teller])->id,
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'type' => TransactionType::Buy->value,
            'currency_code' => 'USD',
            'quantity' => '10.0000',
            'amount_myr' => '50.0000',
            'status' => TransactionStatus::Completed->value,
            'created_at' => now()->subHour(),
        ]);

        app(TransactionReversalService::class)
            ->reverse($transaction, $reverser, 'Review regression test');

        $audit = SystemLog::where('action', 'transaction_reversed')
            ->where('entity_type', 'Transaction')
            ->where('entity_id', $transaction->id)
            ->first();

        $this->assertNotNull($audit, 'Reversal must leave a sealed audit record');
        $this->assertNotNull($audit->entry_hash, 'Reversal audit must be hash-sealed');
    }

    #[Test]
    public function customer_search_finds_customer_by_id_number(): void
    {
        // Pre-fix the id_number_hash branch matched a plaintext LIKE against
        // an HMAC — the ID search could never hit.
        $teller = User::factory()->create(['role' => UserRole::Teller]);
        $customer = app(CustomerService::class)->createCustomer([
            'full_name' => 'Ahmad bin Abdullah',
            'id_type' => 'MyKad',
            'id_number' => '850101015678',
            'date_of_birth' => '1985-01-01',
            'nationality' => 'MY',
        ], $teller->id);

        $results = $this->actingAs($teller)
            ->getJson(route('customers.search', ['query' => '850101015678']))
            ->assertOk()
            ->json('data.results');

        $this->assertNotEmpty($results);
        $this->assertSame($customer->id, $results[0]['id']);
    }

    #[Test]
    public function cancellation_action_preserves_domain_exception_message(): void
    {
        // Pre-fix the action flattened every failure — including domain
        // exceptions with operator-facing messages — into a generic string.
        $teller = User::factory()->create(['role' => UserRole::Teller]);

        $cancellationService = $this->createMock(TransactionCancellationService::class);
        $cancellationService->method('canCancel')->willReturn(true);
        $cancellationService->method('requestCancellation')
            ->willThrowException(new PermissionDeniedException('Segregation of duties violation'));

        $action = new RequestCancellationAction($cancellationService);
        $transaction = Transaction::factory()->create();

        $result = $action->execute($transaction, $teller, 'reason');

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Segregation of duties', $result->message);
    }

    #[Test]
    public function bulk_assign_rejects_assignee_without_compliance_permission(): void
    {
        // Pre-fix the endpoint trusted any existing user id as assignee.
        $teller = User::factory()->create(['role' => UserRole::Teller]);
        $alert = Alert::factory()->create();

        $results = app(AlertTriageService::class)->bulkAssign([$alert->id], $teller->id);

        $this->assertSame(0, $results['success']);
        $this->assertSame(1, $results['failed']);
        $this->assertStringContainsString('not authorized', $results['errors'][0]);
    }

    #[Test]
    public function alert_priority_maps_to_case_priority(): void
    {
        $this->assertSame(ComplianceCasePriority::Critical, AlertPriority::Critical->toCasePriority());
        $this->assertSame(ComplianceCasePriority::High, AlertPriority::High->toCasePriority());
        $this->assertSame(ComplianceCasePriority::Medium, AlertPriority::Medium->toCasePriority());
        $this->assertSame(ComplianceCasePriority::Low, AlertPriority::Low->toCasePriority());
    }

    #[Test]
    public function alert_risk_score_is_anchored_on_the_scoring_engine(): void
    {
        // Pre-fix the alert score was an amount-only heuristic unrelated to
        // the customer's multi-factor engine score.
        $customer = Customer::factory()->create(['risk_rating' => 'high']);
        $transaction = Transaction::factory()->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::Buy->value,
            'currency_code' => 'USD',
            'quantity' => '100.0000',
            'amount_myr' => '100000.0000',
            'status' => TransactionStatus::Completed->value,
        ]);
        $flag = FlaggedTransaction::factory()->create([
            'customer_id' => $customer->id,
            'transaction_id' => $transaction->id,
            'flag_type' => 'Velocity',
        ]);

        $score = app(AlertTriageService::class)->calculateRiskScore($flag, $customer, $transaction);

        // Engine BASE_SCORE is 20 — the alert score can never drop below it
        // for a scored customer, and the large amount adds a delta on top.
        $this->assertGreaterThanOrEqual(50, $score);
    }

    #[Test]
    public function log_rotation_reseals_chain_boundary_so_verify_passes(): void
    {
        // Pre-fix rotation deleted by created_at, so a backdated entry left a
        // mid-chain hole the verifier read as a permanent tamper failure.
        $chain = app(AuditChainService::class);

        // Three sealed entries; B is backdated so rotation archives it while
        // A and C survive with a hole between them. Hashes are nulled so
        // sealLogEntry() actually seals them (the factory sets random
        // hashes, which sealLogEntry skips and the verifier rejects).
        $a = SystemLog::factory()->create([
            'action' => 'test_a',
            'entity_type' => 'Transaction',
            'entity_id' => 1,
            'created_at' => now()->subDays(10),
            'previous_hash' => null,
            'entry_hash' => null,
        ]);
        $b = SystemLog::factory()->create([
            'action' => 'test_b',
            'entity_type' => 'Transaction',
            'entity_id' => 2,
            'created_at' => now()->subMonths(6),
            'previous_hash' => null,
            'entry_hash' => null,
        ]);
        $c = SystemLog::factory()->create([
            'action' => 'test_c',
            'entity_type' => 'Transaction',
            'entity_id' => 3,
            'created_at' => now()->subDays(9),
            'previous_hash' => null,
            'entry_hash' => null,
        ]);

        $chain->sealLogEntry($a->id);
        $chain->sealLogEntry($b->id);
        $chain->sealLogEntry($c->id);

        $this->app->make(LogRotationService::class)->archiveOldLogs(30);

        // B was archived and deleted; C survived after the hole and must have
        // been resealed with the GAP boundary.
        $this->assertDatabaseMissing('system_logs', ['id' => $b->id]);

        $c->refresh();
        $this->assertStringStartsWith('GAP:', (string) $c->previous_hash);

        $result = $chain->verifyChainIntegrity();

        $this->assertTrue($result['valid'], 'Chain must verify across an audited rotation boundary');
    }

    #[Test]
    public function open_sanctions_parser_skips_malformed_jsonl_lines(): void
    {
        // Pre-fix one bad line aborted the whole feed parse.
        $path = tempnam(sys_get_temp_dir(), 'sanctions').'.jsonl';
        file_put_contents($path, implode("\n", [
            json_encode(['id' => 'valid-1', 'caption' => 'Valid One', 'schema' => 'LegalEntity', 'properties' => ['name' => ['Valid One']]]),
            'this-is-not-json',
            json_encode(['id' => 'valid-2', 'caption' => 'Valid Two', 'schema' => 'LegalEntity', 'properties' => ['name' => ['Valid Two']]]),
        ]));

        $parser = new OpenSanctionsJsonParser;
        $entries = iterator_to_array($parser->parse($path));

        unlink($path);

        $this->assertCount(2, $entries);
        $this->assertSame('valid-1', $entries[0]['id']);
        $this->assertSame('valid-2', $entries[1]['id']);
    }

    #[Test]
    public function pep_approval_actions_require_compliance_permission(): void
    {
        // Pre-fix the controller had no in-controller authorization.
        $teller = User::factory()->create(['role' => UserRole::Teller]);
        $pepApproval = PepApprovalRequest::factory()->create();

        $this->actingAs($teller);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Access Compliance');

        app(PepApprovalController::class)
            ->reject(new RejectPepApprovalRequest, $pepApproval);
    }
}
