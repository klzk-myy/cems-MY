<?php

namespace Tests\Unit\Models\Relationships;

use App\Models\AccountLedger;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\ExchangeRateHistory;
use App\Models\JournalEntry;
use App\Models\RevaluationEntry;
use App\Models\StockTransferItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchCurrencyJournalInverseTest extends TestCase
{
    use RefreshDatabase;

    public function test_branch_currency_and_journal_inverses(): void
    {
        $branch = Branch::factory()->create();
        $currency = Currency::factory()->create();
        $journal = JournalEntry::factory()->create();

        ExchangeRate::factory()->create(['branch_id' => $branch->id, 'currency_code' => $currency->code]);
        ExchangeRateHistory::factory()->create(['branch_id' => $branch->id, 'currency_code' => $currency->code]);
        RevaluationEntry::factory()->create(['currency_code' => $currency->code]);
        StockTransferItem::factory()->create(['currency_code' => $currency->code]);
        AccountLedger::factory()->create(['journal_entry_id' => $journal->id]);

        $this->assertCount(1, $branch->exchangeRates);
        $this->assertCount(1, $branch->exchangeRateHistories);
        $this->assertCount(1, $currency->exchangeRateHistories);
        $this->assertCount(1, $currency->revaluationEntries);
        $this->assertCount(1, $currency->stockTransferItems);
        $this->assertCount(1, $journal->accountLedgerEntries);
    }
}
