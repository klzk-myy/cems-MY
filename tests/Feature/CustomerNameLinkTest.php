<?php

namespace Tests\Feature;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\ScreeningResult;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('slow')]
class CustomerNameLinkTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    #[Test]
    public function customer_names_link_to_the_customer_detail_page(): void
    {
        $teller = User::factory()->create(['role' => UserRole::Teller]);
        $customer = $this->createTestCustomer();

        Transaction::factory()->create([
            'type' => TransactionType::Buy,
            'currency_code' => 'USD',
            'quantity' => '100.00',
            'amount_myr' => '400.00',
            'rate' => '4.00',
            'customer_id' => $customer->id,
            'user_id' => $teller->id,
            'branch_id' => $teller->branch_id,
            'status' => TransactionStatus::Completed,
            'idempotency_key' => uniqid('test_', true),
        ]);

        $response = $this->actingAs($teller)->get('/transactions');

        $response->assertOk();
        $response->assertSee('href="'.route('customers.show', $customer).'"', escape: false);
    }

    #[Test]
    public function customers_index_names_link_to_details(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $customer = $this->createTestCustomer();

        $response = $this->actingAs($manager)->get('/customers');

        $response->assertOk();
        $response->assertSee('href="'.route('customers.show', $customer).'"', escape: false);
    }

    #[Test]
    public function customer_detail_page_shows_each_screening_result_in_detail(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $customer = $this->createTestCustomer();

        $result = ScreeningResult::factory()->create([
            'customer_id' => $customer->id,
            'screened_name' => 'Ahmad Testable',
            'result' => 'flag',
            'match_score' => 0.87,
            'match_type' => 'levenshtein',
            'action_taken' => 'flag',
            'matched_fields' => ['normalized_name' => 'Ahmad Testable'],
            'disposition' => 'false_positive',
            'disposition_reason' => 'Different person entirely',
        ]);

        $response = $this->actingAs($manager)->get(route('customers.show', $customer));

        $response->assertOk();
        $response->assertSee('Screening Results');
        $response->assertSee('Ahmad Testable');
        $response->assertSee('87% match');
        $response->assertSee('Levenshtein');
        $response->assertSee('Different person entirely');
        $response->assertSee('False positive');
    }

    #[Test]
    public function customer_detail_page_shows_empty_screening_state(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $customer = $this->createTestCustomer();

        $response = $this->actingAs($manager)->get(route('customers.show', $customer));

        $response->assertOk();
        $response->assertSee('No screening results recorded.');
    }
}
