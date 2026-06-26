<?php

namespace Tests\Feature;

use App\Enums\ComplianceFlagType;
use App\Enums\ReportType;
use App\Enums\TestResultStatus;
use App\Enums\TransactionType;
use App\Models\Currency;
use App\Models\CurrencyPosition;
use App\Models\Customer;
use App\Models\ExchangeRate;
use App\Models\FlaggedTransaction;
use App\Models\ReportGenerated;
use App\Models\TestResult;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminReportSmokeTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function msb2_report_page_loads_for_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $date = now()->subDay()->toDateString();

        $currency = Currency::factory()->create(['code' => 'USD']);
        ReportGenerated::factory()->create([
            'report_type' => ReportType::Msb2->value,
            'period_start' => $date,
            'period_end' => $date,
            'generated_by' => $admin->id,
        ]);
        Transaction::factory()->completed()->create([
            'currency_code' => $currency->code,
            'type' => TransactionType::Buy->value,
            'amount_local' => 1000.00,
            'amount_foreign' => 250.00,
            'created_at' => Carbon::parse($date),
        ]);

        $response = $this->actingAs($admin)->get(route('reports.msb2', ['date' => $date]));

        $response->assertOk();
        $response->assertSee('MSB2 Daily Transaction Summary');
        $response->assertSee($currency->code);
    }

    #[Test]
    public function msb2_status_update_updates_generated_report(): void
    {
        $admin = User::factory()->admin()->create();
        $date = now()->subDay()->toDateString();

        ReportGenerated::factory()->create([
            'report_type' => ReportType::Msb2->value,
            'period_start' => $date,
            'period_end' => $date,
            'generated_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->postJson('/api/v1/reports/msb2/status', [
            'date' => $date,
            'status' => 'Submitted',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('reports_generated', [
            'report_type' => ReportType::Msb2->value,
            'status' => 'Submitted',
        ]);
    }

    #[Test]
    public function lmca_report_page_loads_for_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $month = now()->subMonth()->format('Y-m');

        ReportGenerated::factory()->create([
            'report_type' => ReportType::Lmca->value,
            'period_start' => Carbon::parse($month)->startOfMonth(),
            'period_end' => Carbon::parse($month)->endOfMonth(),
            'generated_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(route('reports.lmca', ['month' => $month]));

        $response->assertOk();
        $response->assertSee('Form LMCA');
    }

    #[Test]
    public function quarterly_lvr_report_page_loads_for_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $quarter = now()->format('Y').'-Q'.(int) ceil((int) now()->format('n') / 3);

        ReportGenerated::factory()->create([
            'report_type' => ReportType::Qlvr->value,
            'period_start' => Carbon::create(now()->year, 1, 1)->startOfMonth(),
            'period_end' => Carbon::create(now()->year, 3, 31)->endOfDay(),
            'generated_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(route('reports.quarterly-lvr', ['quarter' => $quarter]));

        $response->assertOk();
        $response->assertSee('Quarterly Large Value Report');
    }

    #[Test]
    public function position_limit_report_page_loads_for_admin(): void
    {
        $admin = User::factory()->admin()->create();

        ReportGenerated::factory()->create([
            'report_type' => ReportType::Plr->value,
            'period_start' => now()->startOfDay(),
            'period_end' => now()->endOfDay(),
            'generated_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(route('reports.position-limit'));

        $response->assertOk();
        $response->assertSee('Position Limit Report');
    }

    #[Test]
    public function lmca_status_update_updates_generated_report(): void
    {
        $admin = User::factory()->admin()->create();
        $month = now()->subMonth()->format('Y-m');

        ReportGenerated::factory()->create([
            'report_type' => ReportType::Lmca->value,
            'period_start' => Carbon::parse($month)->startOfMonth(),
            'period_end' => Carbon::parse($month)->endOfMonth(),
            'generated_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->postJson('/api/v1/reports/lmca/status', [
            'month' => $month,
            'status' => 'Submitted',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('reports_generated', [
            'report_type' => ReportType::Lmca->value,
            'status' => 'Submitted',
        ]);
    }

    #[Test]
    public function test_results_page_loads_for_admin(): void
    {
        $admin = User::factory()->admin()->create();
        TestResult::factory()->create([
            'status' => TestResultStatus::Passed->value,
            'total_tests' => 10,
            'passed' => 10,
            'failed' => 0,
        ]);

        $response = $this->actingAs($admin)->get(route('test-results.index'));

        $response->assertOk();
        $response->assertSee('Test Results');
        $response->assertSee('Avg Pass Rate');
        $response->assertSee('100%');
        $response->assertSee('bg-success-subtle');
        $response->assertSee('10');
        $response->assertSee('0');
    }

    #[Test]
    public function test_results_show_page_loads_for_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $run = TestResult::factory()->create([
            'status' => TestResultStatus::Passed->value,
            'total_tests' => 10,
            'passed' => 8,
            'failed' => 2,
            'git_branch' => 'main',
            'git_commit' => 'abc1234',
            'executed_by' => 'ci',
        ]);

        $response = $this->actingAs($admin)->get(route('test-results.show', $run));

        $response->assertOk();
        $response->assertSee('Run #'.$run->id);
        $response->assertSee('10');
        $response->assertSee('8');
        $response->assertSee('2');
        $response->assertSee('main');
        $response->assertSee('abc1234');
        $response->assertSee('ci');
    }

    #[Test]
    public function test_results_statistics_page_loads_for_admin(): void
    {
        $admin = User::factory()->admin()->create();
        TestResult::factory()->create([
            'test_suite' => 'full',
            'status' => TestResultStatus::Passed->value,
            'total_tests' => 10,
            'passed' => 10,
            'failed' => 0,
        ]);

        $response = $this->actingAs($admin)->get(route('test-results.statistics'));

        $response->assertOk();
        $response->assertSee('Test Statistics');
        $response->assertSee('full');
        $response->assertSee('10');
    }

    #[Test]
    public function test_results_compare_page_loads_for_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $run1 = TestResult::factory()->create([
            'status' => TestResultStatus::Passed->value,
            'total_tests' => 10,
            'passed' => 10,
            'failed' => 0,
        ]);
        $run2 = TestResult::factory()->create([
            'status' => TestResultStatus::Failed->value,
            'total_tests' => 10,
            'passed' => 8,
            'failed' => 2,
        ]);

        $response = $this->actingAs($admin)->get(route('test-results.compare', [
            'run1' => $run1->id,
            'run2' => $run2->id,
        ]));

        $response->assertOk();
        $response->assertSee("Run #{$run1->id} vs Run #{$run2->id}");
        $response->assertSee('Passed');
        $response->assertSee('Failed');
    }

    #[Test]
    public function monthly_trends_report_loads_for_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $year = now()->year;

        Transaction::factory()->completed()->create([
            'type' => TransactionType::Buy->value,
            'amount_local' => 1000.00,
            'created_at' => Carbon::create($year, 2, 15),
        ]);
        Transaction::factory()->completed()->create([
            'type' => TransactionType::Buy->value,
            'amount_local' => 2000.00,
            'created_at' => Carbon::create($year, 3, 10),
        ]);

        $response = $this->actingAs($admin)->get(route('reports.monthly-trends', ['year' => $year]));

        $response->assertOk();
        $response->assertSee('Monthly Transaction Trends');
        $response->assertSee('1,000.00');
        $response->assertSee('2,000.00');
    }

    #[Test]
    public function profitability_report_loads_for_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $currency = Currency::factory()->create(['code' => 'USD']);

        CurrencyPosition::factory()->create([
            'currency_code' => $currency->code,
            'branch_id' => $admin->branch_id,
            'balance' => '1000',
            'avg_cost_rate' => '4.0000',
            'last_valuation_rate' => '4.0000',
        ]);

        ExchangeRate::factory()->create([
            'currency_code' => $currency->code,
            'rate_sell' => '4.5000',
            'fetched_at' => Carbon::parse('2024-01-15 10:00:00'),
        ]);
        ExchangeRate::factory()->create([
            'currency_code' => $currency->code,
            'rate_sell' => '4.3000',
            'fetched_at' => Carbon::parse('2024-01-10 10:00:00'),
        ]);

        Transaction::factory()->completed()->create([
            'currency_code' => $currency->code,
            'type' => TransactionType::Sell->value,
            'rate' => '4.6000',
            'amount_foreign' => '500',
            'amount_local' => '2300',
            'created_at' => Carbon::parse('2024-01-15 12:00:00'),
        ]);

        $response = $this->actingAs($admin)->get(route('reports.profitability', [
            'start_date' => '2024-01-01',
            'end_date' => '2024-01-31',
        ]));

        $response->assertOk();
        $response->assertSee('Currency Profitability Analysis');
        $response->assertSee('+800.00');
    }

    #[Test]
    public function customer_analysis_report_loads_for_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->hasTransactions(2)->create();

        $response = $this->actingAs($admin)->get(route('reports.customer-analysis'));

        $response->assertOk();
        $response->assertSee('Top Customer Analysis');
        $response->assertSee($customer->full_name);
    }

    #[Test]
    public function compliance_summary_report_loads_for_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $transaction = Transaction::factory()->completed()->create();

        FlaggedTransaction::factory()->create([
            'transaction_id' => $transaction->id,
            'customer_id' => $transaction->customer_id,
            'flag_type' => ComplianceFlagType::Structuring->value,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('reports.compliance-summary'));

        $response->assertOk();
        $response->assertSee('Compliance Summary Report');
        $response->assertSee('Structuring');
    }

    #[Test]
    public function user_show_page_loads_for_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $response = $this->actingAs($admin)->get(route('users.show', $user));

        $response->assertOk();
        $response->assertSee($user->username);
    }

    #[Test]
    public function transactions_index_loads_for_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $transaction = Transaction::factory()->completed()->create([
            'branch_id' => $admin->branch_id,
        ]);

        $response = $this->actingAs($admin)->get(route('transactions.index'));

        $response->assertOk();
        $response->assertSee($transaction->type->label());
        $response->assertSee($transaction->status->label());
    }

    #[Test]
    public function customer_edit_page_loads_for_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create();

        $response = $this->actingAs($admin)->get(route('customers.edit', $customer));

        $response->assertOk();
        $response->assertSee($customer->full_name);
        $response->assertSee('Edit Customer');
    }
}
