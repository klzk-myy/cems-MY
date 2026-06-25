<?php

namespace Tests\Unit\Models;

use App\Enums\JournalEntryStatus;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JournalEntryModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_journal_entry_has_lines(): void
    {
        $entry = JournalEntry::factory()->create();
        JournalLine::factory()->count(2)->create(['journal_entry_id' => $entry->id]);

        $this->assertCount(2, $entry->lines);
    }

    public function test_status_helpers(): void
    {
        $entry = JournalEntry::factory()->make(['status' => JournalEntryStatus::Posted]);

        $this->assertTrue($entry->isPosted());
        $this->assertFalse($entry->isDraft());
    }
}
