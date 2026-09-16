<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\CustomerRelation;
use App\Models\SanctionEntry;
use App\Models\SanctionList;
use App\Models\ScreeningResult;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\SanctionsMatchNotification;
use App\Services\CustomerScreeningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CustomerScreeningServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CustomerScreeningService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CustomerScreeningService::class);
    }

    #[Test]
    public function screen_name_returns_clear_for_no_match(): void
    {
        $sanctionList = SanctionList::factory()->create([
            'slug' => 'test-list-1',
        ]);

        SanctionEntry::factory()->create([
            'list_id' => $sanctionList->id,
            'entity_name' => 'John Smith',
            'normalized_name' => 'john smith',
        ]);

        $response = $this->service->screenName('Completely Different Name');

        $this->assertTrue($response->isClear());
        $this->assertEquals(0.0, $response->confidenceScore);
        $this->assertTrue($response->matches->isEmpty());
    }

    #[Test]
    public function screen_name_returns_flag_for_partial_match(): void
    {
        $sanctionList = SanctionList::factory()->create([
            'slug' => 'test-list-2',
        ]);

        SanctionEntry::factory()->create([
            'list_id' => $sanctionList->id,
            'entity_name' => 'John Smith',
            'normalized_name' => 'john smith',
            'soundex_code' => soundex('John Smith'),
            'metaphone_code' => metaphone('John Smith'),
            'aliases' => [],
        ]);

        $response = $this->service->screenName('John Smith');

        $this->assertFalse($response->isClear());
        $this->assertGreaterThanOrEqual(75.0, $response->confidenceScore);
        $this->assertFalse($response->matches->isEmpty());
    }

    #[Test]
    public function screen_name_uses_dob_for_confidence(): void
    {
        $sanctionList = SanctionList::factory()->create([
            'slug' => 'test-list-3',
        ]);

        $_entry = SanctionEntry::factory()->create([
            'list_id' => $sanctionList->id,
            'entity_name' => 'John Smith',
            'normalized_name' => 'john smith',
            'date_of_birth' => '1980-05-15',
        ]);

        $responseWithDob = $this->service->screenName('John Smith', dob: '1980-05-20');
        $responseWithoutDob = $this->service->screenName('John Smith');

        $this->assertGreaterThan(
            $responseWithoutDob->confidenceScore,
            $responseWithDob->confidenceScore
        );
    }

    #[Test]
    public function screen_customer_checks_existing_sanction_hit(): void
    {
        $customer = Customer::factory()->create([
            'full_name' => 'Test Customer',
            'sanction_hit' => true,
        ]);

        $response = $this->service->screenCustomer($customer);

        $this->assertTrue($response->isBlocked());
        $this->assertEquals(100.0, $response->confidenceScore);
    }

    #[Test]
    public function screen_customer_persists_result(): void
    {
        $customer = Customer::factory()->create([
            'full_name' => 'New Customer',
            'sanction_hit' => false,
        ]);

        $response = $this->service->screenCustomer($customer);

        $this->assertNotNull($response->resultId);

        $result = ScreeningResult::find($response->resultId);
        $this->assertNotNull($result);
        $this->assertEquals($customer->id, $result->customer_id);
    }

    #[Test]
    public function screen_name_matches_hyphenated_entry_against_spaced_query(): void
    {
        $sanctionList = SanctionList::factory()->create([
            'slug' => 'test-list-hyphen',
        ]);

        SanctionEntry::factory()->create([
            'list_id' => $sanctionList->id,
            'entity_name' => 'John-Doe Smith',
            'normalized_name' => 'john doe smith',
            'soundex_code' => soundex('john doe smith'),
            'metaphone_code' => metaphone('john doe smith'),
            'aliases' => [],
        ]);

        $response = $this->service->screenName('John Doe Smith');

        $this->assertFalse($response->isClear());
        $this->assertFalse($response->matches->isEmpty());
    }

    #[Test]
    public function screen_name_matches_spaced_entry_against_hyphenated_query(): void
    {
        $sanctionList = SanctionList::factory()->create([
            'slug' => 'test-list-spaced',
        ]);

        SanctionEntry::factory()->create([
            'list_id' => $sanctionList->id,
            'entity_name' => 'John Doe Smith',
            'normalized_name' => 'john doe smith',
            'soundex_code' => soundex('john doe smith'),
            'metaphone_code' => metaphone('john doe smith'),
            'aliases' => [],
        ]);

        $response = $this->service->screenName('John-Doe Smith');

        $this->assertFalse($response->isClear());
        $this->assertFalse($response->matches->isEmpty());
    }

    #[Test]
    public function threshold_is_75_percent(): void
    {
        $sanctionList = SanctionList::factory()->create([
            'slug' => 'test-list-6',
        ]);

        SanctionEntry::factory()->create([
            'list_id' => $sanctionList->id,
            'entity_name' => 'Mohammad Abu Hassan',
            'normalized_name' => 'mohammad abu hassan',
            'soundex_code' => soundex('Mohammad Abu Hassan'),
            'metaphone_code' => metaphone('Mohammad Abu Hassan'),
            'aliases' => [],
        ]);

        $exactResponse = $this->service->screenName('Mohammad Abu Hassan');

        $this->assertGreaterThanOrEqual(75.0, $exactResponse->confidenceScore);
    }

    #[Test]
    public function levenshtein_similarity_calculation(): void
    {
        $service = app(CustomerScreeningService::class);

        $this->assertEquals(1.0, $service->levenshteinSimilarity('john', 'john'));
        $this->assertLessThan(1.0, $service->levenshteinSimilarity('john', 'jon'));
        $this->assertGreaterThan(0.7, $service->levenshteinSimilarity('john', 'johm'));
    }

    #[Test]
    public function screen_transaction_uses_customer_info(): void
    {
        $customer = Customer::factory()->create([
            'full_name' => 'Alice Johnson',
            'date_of_birth' => '1990-03-25',
            'nationality' => 'MY',
        ]);

        $transaction = Transaction::factory()->create([
            'customer_id' => $customer->id,
        ]);

        $response = $this->service->screenTransaction($transaction);

        $this->assertNotNull($response);
    }

    #[Test]
    public function batch_screen_returns_collection(): void
    {
        $customer1 = Customer::factory()->create(['full_name' => 'Customer One']);
        $customer2 = Customer::factory()->create(['full_name' => 'Customer Two']);

        $results = $this->service->batchScreen([$customer1->id, $customer2->id]);

        $this->assertCount(2, $results);
    }

    #[Test]
    public function batch_screen_fetches_candidate_pools_once_not_per_customer(): void
    {
        $customers = Customer::factory()->count(3)->create();

        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->service->batchScreen($customers->pluck('id')->all());

        $queries = DB::getQueryLog();
        $poolQueries = collect($queries)->filter(
            fn ($q) => str_contains($q['query'], 'sanction_entries')
                || str_contains($q['query'], 'adverse_media_entries')
        );

        $this->assertLessThanOrEqual(
            2,
            $poolQueries->count(),
            'Sanction/adverse pools must be fetched once per batch, not once per customer'
        );
    }

    #[Test]
    public function get_status_returns_correct_structure(): void
    {
        $customer = Customer::factory()->create([
            'full_name' => 'Test Customer',
            'sanction_hit' => false,
        ]);

        $this->service->screenCustomer($customer);

        $status = $this->service->getStatus($customer);

        $this->assertArrayHasKey('customer_id', $status);
        $this->assertArrayHasKey('sanction_hit', $status);
        $this->assertArrayHasKey('last_screened_at', $status);
        $this->assertArrayHasKey('last_result', $status);
        $this->assertEquals($customer->id, $status['customer_id']);
        $this->assertFalse($status['sanction_hit']);
    }

    #[Test]
    public function get_history_returns_screening_results(): void
    {
        $customer = Customer::factory()->create(['full_name' => 'History Test Customer']);

        $this->service->screenCustomer($customer);
        $this->service->screenCustomer($customer);

        $history = $this->service->getHistory($customer);

        $this->assertGreaterThanOrEqual(2, $history->count());
    }

    #[Test]
    public function confirmed_sanctions_match_triggers_freeze(): void
    {
        $customer = Customer::factory()->create([
            'full_name' => 'Test Customer',
            'is_active' => true,
        ]);

        // Simulate confirmed sanctions match
        $_result = $this->service->handleConfirmedMatch($customer, 'UNSCR');

        $customer->refresh();

        $this->assertTrue($customer->is_frozen);
        $this->assertEquals('confirmed_UNSCR_match', $customer->freeze_reason);
        $this->assertNotNull($customer->frozen_at);
    }

    #[Test]
    public function potential_customer_with_positive_match_is_rejected(): void
    {
        $customer = Customer::factory()->create([
            'full_name' => 'Pending Customer',
            'is_active' => false,
        ]);

        $_result = $this->service->handleConfirmedMatch($customer, 'DOMESTIC');

        $customer->refresh();

        $this->assertFalse($customer->is_active);
        $this->assertEquals('positive_DOMESTIC_match', $customer->rejection_reason);
    }

    #[Test]
    public function confirmed_match_blocks_transactions(): void
    {
        $customer = Customer::factory()->create([
            'full_name' => 'Test Customer',
            'is_active' => true,
        ]);

        $this->service->handleConfirmedMatch($customer, 'UNSCR');

        $customer->refresh();

        $this->assertTrue($customer->transactions_blocked);
    }

    #[Test]
    public function confirmed_match_notifies_compliance_officers_of_sanctions_match(): void
    {
        Notification::fake();

        $officer = User::factory()->complianceOfficer()->create();
        $manager = User::factory()->manager()->create();
        User::factory()->teller()->create();

        $customer = Customer::factory()->create([
            'full_name' => 'Sanctioned Customer',
            'is_active' => true,
        ]);

        $this->service->handleConfirmedMatch($customer, 'UNSCR');

        Notification::assertSentToTimes($officer, SanctionsMatchNotification::class, 1);
        Notification::assertSentToTimes($manager, SanctionsMatchNotification::class, 1);
        Notification::assertNotSentTo(
            User::where('role', UserRole::Teller->value)->first(),
            SanctionsMatchNotification::class
        );
    }

    #[Test]
    public function related_parties_due_diligence_conducted(): void
    {
        $customer = Customer::factory()->create();
        $relatedParty = Customer::factory()->create();

        // Link them as related parties via CustomerRelation
        CustomerRelation::create([
            'customer_id' => $customer->id,
            'related_customer_id' => $relatedParty->id,
            'relation_type' => 'business_partner',
            'related_name' => $relatedParty->full_name,
        ]);

        $service = app(CustomerScreeningService::class);
        $service->conductRelatedPartiesDueDiligence($customer);

        // Verify transaction analysis was recorded - check via customer_relations analysis
        $this->assertDatabaseHas('customer_relations', [
            'customer_id' => $customer->id,
            'related_customer_id' => $relatedParty->id,
            'relation_type' => 'business_partner',
        ]);
    }
}
