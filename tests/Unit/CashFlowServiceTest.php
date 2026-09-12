<?php

namespace Tests\Unit;

use App\Enums\JournalEntryStatus;
use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Services\Accounting\CashFlowService;
use App\Services\System\MathService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CashFlowServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CashFlowService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CashFlowService(new MathService);

        // account_class drives the working-capital and financing sections.
        $accounts = [
            ['account_code' => '1000', 'account_name' => 'Cash', 'account_type' => 'Asset', 'account_class' => 'Cash'],
            ['account_code' => '2000', 'account_name' => 'Inventory', 'account_type' => 'Asset', 'account_class' => 'Inventory'],
            ['account_code' => '4000', 'account_name' => 'Capital', 'account_type' => 'Equity', 'account_class' => 'Capital'],
            ['account_code' => '5000', 'account_name' => 'Revenue', 'account_type' => 'Revenue', 'account_class' => null],
        ];

        foreach ($accounts as $account) {
            ChartOfAccount::updateOrCreate(
                ['account_code' => $account['account_code']],
                array_merge($account, ['is_active' => true])
            );
        }
    }

    /** @param array<int, array<string, string>> $lines */
    protected function postEntry(string $date, array $lines): void
    {
        $period = AccountingPeriod::firstOrCreate(
            ['period_code' => substr($date, 0, 7)],
            [
                'start_date' => $date,
                'end_date' => $date,
                'status' => 'Open',
            ]
        );

        $entry = JournalEntry::factory()->create([
            'entry_date' => $date,
            'period_id' => $period->id,
            'status' => JournalEntryStatus::Posted,
        ]);

        foreach ($lines as $line) {
            JournalLine::create(array_merge($line, ['journal_entry_id' => $entry->id]));
        }
    }

    #[Test]
    public function get_cash_flow_classifies_revenue_inventory_and_capital(): void
    {
        Cache::tags(['reports', 'cash-flow'])->flush();

        $from = now()->subDays(10)->toDateString();
        $to = now()->toDateString();

        // Sale: Dr cash 1924 / Cr inventory 1888 / Cr revenue 36.
        $this->postEntry($to, [
            ['account_code' => '1000', 'debit' => '1924.00', 'credit' => '0.00'],
            ['account_code' => '2000', 'debit' => '0.00', 'credit' => '1888.00'],
            ['account_code' => '5000', 'debit' => '0.00', 'credit' => '36.00'],
        ]);

        // Capital injection: Dr cash 96000 / Cr capital 96000.
        $this->postEntry($to, [
            ['account_code' => '1000', 'debit' => '96000.00', 'credit' => '0.00'],
            ['account_code' => '4000', 'debit' => '0.00', 'credit' => '96000.00'],
        ]);

        $result = $this->service->getCashFlow($from, $to);

        // Enum-cast account_type must classify revenue (was string-compared
        // before and produced zero net income).
        $this->assertEquals('36.0000', $result['operating_activities']['net_income']);

        // Inventory decrease of 1888 adds cash in the indirect method. The
        // underlying query joins journal_entries for dates — journal_lines
        // has no entry_date column (previous code threw a QueryException).
        $this->assertEquals('1888.0000', $result['operating_activities']['inventory_change']);
        $this->assertEquals('1924.0000', $result['operating_activities']['total']);

        // Credit-normal capital issuance is a financing inflow.
        $this->assertEquals('96000.0000', $result['financing_activities']['equity_issued']);

        // Net change reconciles to the actual cash movement.
        $this->assertEquals('97924.0000', $result['net_change_in_cash']);
    }

    #[Test]
    public function get_cash_flow_excludes_draft_entries(): void
    {
        Cache::tags(['reports', 'cash-flow'])->flush();

        $from = now()->subDays(10)->toDateString();
        $to = now()->toDateString();

        $period = AccountingPeriod::firstOrCreate(
            ['period_code' => substr($to, 0, 7)],
            [
                'start_date' => $to,
                'end_date' => $to,
                'status' => 'Open',
            ]
        );

        $draft = JournalEntry::factory()->create([
            'entry_date' => $to,
            'period_id' => $period->id,
            'status' => JournalEntryStatus::Draft,
        ]);

        JournalLine::create([
            'journal_entry_id' => $draft->id,
            'account_code' => '5000',
            'debit' => '0.00',
            'credit' => '5000.00',
        ]);

        $result = $this->service->getCashFlow($from, $to);

        $this->assertEquals('0.0000', $result['operating_activities']['net_income']);
        $this->assertEquals('0.0000', $result['net_change_in_cash']);
    }
}
