<?php

namespace Tests\Unit\Models;

use App\Models\JournalEntry;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransactionJournalEntryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function transaction_has_journal_entry_relationship(): void
    {
        $journalEntry = JournalEntry::factory()->create();
        $transaction = Transaction::factory()->create([
            'journal_entry_id' => $journalEntry->id,
        ]);

        $this->assertNotNull($transaction->journalEntry);
        $this->assertEquals($journalEntry->id, $transaction->journalEntry->id);
    }

    #[Test]
    public function transaction_has_deferred_journal_entry_relationship(): void
    {
        $journalEntry = JournalEntry::factory()->create();
        $transaction = Transaction::factory()->create([
            'deferred_journal_entry_id' => $journalEntry->id,
        ]);

        $this->assertNotNull($transaction->deferredJournalEntry);
        $this->assertEquals($journalEntry->id, $transaction->deferredJournalEntry->id);
    }
}
