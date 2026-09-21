<?php

namespace Tests\Feature;

use App\Enums\SystemAlertLevel;
use App\Models\AdverseMediaEntry;
use App\Models\Compliance\SanctionEntry;
use App\Models\Compliance\SanctionList;
use App\Models\Compliance\ScreeningResult;
use App\Models\Customer;
use App\Models\SystemAlert;
use App\Services\Screening\CustomerScreeningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
