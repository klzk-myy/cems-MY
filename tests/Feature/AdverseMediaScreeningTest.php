<?php

namespace Tests\Feature;

use App\Enums\SystemAlertLevel;
use App\Models\AdverseMediaEntry;
use App\Models\AdverseMediaImportLog;
use App\Models\Customer;
use App\Models\SanctionEntry;
use App\Models\SanctionList;
use App\Models\ScreeningResult;
use App\Models\SystemAlert;
use App\Services\AdverseMediaImportService;
use App\Services\CustomerScreeningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdverseMediaScreeningTest extends TestCase
{
    use RefreshDatabase;

    protected CustomerScreeningService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CustomerScreeningService::class);
    }

    #[Test]
    public function adverse_media_match_creates_flagged_result_with_adverse_source(): void
    {
        $entry = AdverseMediaEntry::factory()->create([
            'name' => 'John Smith',
            'normalized_name' => 'john smith',
            'alias' => null,
        ]);

        $response = $this->service->screenName('John Smith');

        $this->assertTrue($response->isFlagged());
        $this->assertGreaterThanOrEqual(75.0, $response->confidenceScore);

        $result = ScreeningResult::findOrFail($response->resultId);

        $this->assertSame('adverse_media', $result->source);
        $this->assertSame($entry->id, $result->adverse_media_entry_id);
        $this->assertNull($result->sanction_entry_id);
        $this->assertSame('flag', $result->result);
    }

    #[Test]
    public function adverse_media_hits_never_auto_block_even_at_high_scores(): void
    {
        AdverseMediaEntry::factory()->create([
            'name' => 'Mohammad Abu Hassan',
            'normalized_name' => 'mohammad abu hassan',
        ]);

        $response = $this->service->screenName('Mohammad Abu Hassan');

        $this->assertGreaterThanOrEqual(90.0, $response->confidenceScore);
        $this->assertFalse($response->isBlocked());
        $this->assertTrue($response->isFlagged());
    }

    #[Test]
    public function sanctions_hit_still_blocks_via_threshold(): void
    {
        SanctionList::factory()->create(['slug' => 'adverse-test-list']);
        SanctionEntry::factory()->create([
            'entity_name' => 'John Smith',
            'normalized_name' => 'john smith',
            'soundex_code' => soundex('John Smith'),
            'metaphone_code' => metaphone('John Smith'),
            'aliases' => [],
        ]);

        $response = $this->service->screenName('John Smith');

        $this->assertTrue($response->isBlocked());

        $result = ScreeningResult::findOrFail($response->resultId);
        $this->assertSame('sanctions', $result->source);
        $this->assertSame('block', $result->result);
    }

    #[Test]
    public function confirmed_adverse_match_escalates_without_freezing_customer(): void
    {
        $customer = Customer::factory()->create([
            'full_name' => 'Adverse Test Customer',
            'is_active' => true,
        ]);

        $outcome = $this->service->handleConfirmedAdverseMatch($customer, 'medium');

        $customer->refresh();

        $this->assertFalse((bool) $customer->is_frozen);
        $this->assertFalse((bool) $customer->transactions_blocked);
        $this->assertSame('escalated_for_review', $outcome['action']);
        $this->assertSame('adverse_media', $outcome['list_type']);

        $alert = SystemAlert::where('source', 'adverse_media_screening')->first();
        $this->assertNotNull($alert);
        $this->assertSame('adverse_media', $alert->metadata['list_type']);
        $this->assertFalse($alert->metadata['requires_fiu_report']);
    }

    #[Test]
    public function high_severity_adverse_confirmation_raises_critical_alert(): void
    {
        $customer = Customer::factory()->create(['full_name' => 'High Sev Customer']);

        $this->service->handleConfirmedAdverseMatch($customer, 'high');

        $alert = SystemAlert::where('source', 'adverse_media_screening')->first();
        $this->assertNotNull($alert);
        $this->assertSame('critical', $alert->level instanceof SystemAlertLevel ? $alert->level->value : $alert->level);

        $customer->refresh();
        $this->assertFalse((bool) $customer->is_frozen);
    }

    #[Test]
    public function import_command_parses_csv_idempotently(): void
    {
        $path = storage_path('app/adverse-media-test.csv');
        file_put_contents($path, implode("\n", [
            'name,title,source,url,snippet,published_at,severity',
            'Jane Doe,Bank fraud probe opens,Jane Doe linked to bank fraud,https://news.example.com/jane,Investigators opened a probe.,2026-01-15,HIGH',
            'Bad Row,,,,,,extreme',
        ]));

        $exitOne = Artisan::call('adverse-media:import', ['file' => $path]);
        $exitTwo = Artisan::call('adverse-media:import', ['file' => $path]);

        $this->assertSame(0, $exitOne);
        $this->assertSame(0, $exitTwo);

        $this->assertSame(1, AdverseMediaEntry::count(), 'Re-import must upsert, not duplicate');

        $entry = AdverseMediaEntry::first();
        $this->assertSame('high', $entry->severity, 'Severity must be normalized to lowercase');
        $this->assertSame('2026-01-15', $entry->published_at->toDateString());
        $this->assertTrue($entry->is_active);

        $outputTwo = Artisan::output();
        $this->assertStringContainsString('Updated: 1', $outputTwo);
        $this->assertStringContainsString('Skipped: 1', $outputTwo);

        unlink($path);
    }

    #[Test]
    public function import_service_accepts_json_payloads_from_stdin_shape(): void
    {
        $payload = json_encode([
            [
                'name' => 'Alice Wong',
                'title' => 'Money laundering allegations',
                'source' => 'manual',
                'url' => null,
                'snippet' => null,
                'published_at' => null,
                'severity' => 'Medium',
            ],
        ]);

        $service = new AdverseMediaImportService;
        $result = $service->importFromContents((string) $payload);

        $this->assertSame(1, $result['added']);
        $this->assertSame(0, $result['skipped']);

        $entry = AdverseMediaEntry::where('name', 'Alice Wong')->first();
        $this->assertNotNull($entry);
        $this->assertSame('medium', $entry->severity);
        $this->assertSame('money laundering allegations', mb_strtolower($entry->article_title));
    }

    #[Test]
    public function csv_import_commits_per_chunk_and_accumulates_one_log_summary(): void
    {
        // Chunk size 2 forces multiple transactions over 6 valid + 1 bad row.
        $service = new class extends AdverseMediaImportService
        {
            protected const COMMIT_CHUNK_SIZE = 2;
        };

        $lines = ['name,title,source,url,severity'];
        foreach (range(1, 6) as $i) {
            $lines[] = "Person {$i},Title {$i},manual,https://e.test/{$i},low";
        }
        $lines[] = ',,,,extreme'; // invalid row -> skipped
        $csv = implode("\n", $lines);

        $first = $service->importFromContents($csv, 'chunked.csv');

        $this->assertSame(6, $first['added']);
        $this->assertSame(0, $first['updated']);
        $this->assertSame(1, $first['skipped']);
        $this->assertSame('partial', $first['status']);

        // Counts accumulated across chunks land in exactly one summary row.
        $this->assertSame(1, AdverseMediaImportLog::count());
        $log = AdverseMediaImportLog::first();
        $this->assertNotNull($log);
        $this->assertSame('chunked.csv', $log->imported_file);
        $this->assertSame(6, $log->records_added);
        $this->assertSame(1, $log->records_skipped);
        $this->assertSame('partial', $log->status);
        $this->assertSame(6, AdverseMediaEntry::count());

        // Idempotent re-import: every chunk upserts instead of duplicating.
        $second = $service->importFromContents($csv, 'chunked.csv');

        $this->assertSame(0, $second['added']);
        $this->assertSame(6, $second['updated']);
        $this->assertSame(1, $second['skipped']);
        $this->assertSame(2, AdverseMediaImportLog::count());
        $this->assertSame(6, AdverseMediaEntry::count(), 'Chunked commits must not duplicate rows');
    }
}
