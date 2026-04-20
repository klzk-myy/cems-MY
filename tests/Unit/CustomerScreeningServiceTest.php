<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\SanctionEntry;
use App\Models\SanctionList;
use App\Models\ScreeningResult;
use App\Models\Transaction;
use App\Services\CustomerScreeningService;
use App\Services\MathService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerScreeningServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CustomerScreeningService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CustomerScreeningService(new MathService);
    }

    public function test_screen_name_returns_clear_for_no_match(): void
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

    public function test_screen_name_returns_flag_for_partial_match(): void
    {
        $sanctionList = SanctionList::factory()->create([
            'slug' => 'test-list-2',
        ]);

        SanctionEntry::factory()->create([
            'list_id' => $sanctionList->id,
            'entity_name' => 'John Smith',
            'normalized_name' => 'john smith',
        ]);

        $response = $this->service->screenName('John Smith');

        $this->assertFalse($response->isClear());
        $this->assertGreaterThanOrEqual(75.0, $response->confidenceScore);
        $this->assertFalse($response->matches->isEmpty());
    }

    public function test_screen_name_uses_dob_for_confidence(): void
    {
        $sanctionList = SanctionList::factory()->create([
            'slug' => 'test-list-3',
        ]);

        $entry = SanctionEntry::factory()->create([
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

    public function test_screen_customer_checks_existing_sanction_hit(): void
    {
        $customer = Customer::factory()->create([
            'full_name' => 'Test Customer',
            'sanction_hit' => true,
        ]);

        $response = $this->service->screenCustomer($customer);

        $this->assertTrue($response->isBlocked());
        $this->assertEquals(100.0, $response->confidenceScore);
    }

    public function test_screen_customer_persists_result(): void
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

    public function test_threshold_is_75_percent(): void
    {
        $sanctionList = SanctionList::factory()->create([
            'slug' => 'test-list-6',
        ]);

        SanctionEntry::factory()->create([
            'list_id' => $sanctionList->id,
            'entity_name' => 'Mohammad Abu Hassan',
            'normalized_name' => 'mohammad abu hassan',
        ]);

        $exactResponse = $this->service->screenName('Mohammad Abu Hassan');

        $this->assertGreaterThanOrEqual(75.0, $exactResponse->confidenceScore);
    }

    public function test_levenshtein_similarity_calculation(): void
    {
        $service = new CustomerScreeningService(new MathService);

        $this->assertEquals(1.0, $service->levenshteinSimilarity('john', 'john'));
        $this->assertLessThan(1.0, $service->levenshteinSimilarity('john', 'jon'));
        $this->assertGreaterThan(0.7, $service->levenshteinSimilarity('john', 'johm'));
    }

    public function test_screen_transaction_uses_customer_info(): void
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

    public function test_batch_screen_returns_collection(): void
    {
        $customer1 = Customer::factory()->create(['full_name' => 'Customer One']);
        $customer2 = Customer::factory()->create(['full_name' => 'Customer Two']);

        $results = $this->service->batchScreen([$customer1->id, $customer2->id]);

        $this->assertCount(2, $results);
    }

    public function test_get_status_returns_correct_structure(): void
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

    public function test_get_history_returns_screening_results(): void
    {
        $customer = Customer::factory()->create(['full_name' => 'History Test Customer']);

        $this->service->screenCustomer($customer);
        $this->service->screenCustomer($customer);

        $history = $this->service->getHistory($customer);

        $this->assertGreaterThanOrEqual(2, $history->count());
    }
}
