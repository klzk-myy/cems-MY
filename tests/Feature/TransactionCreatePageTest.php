<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * C-note: the transaction create page must not eager-load the full customer
 * table (the typeahead hits customers.search via AJAX). Counters are no
 * longer offered at all — the booking till resolves from the teller's open
 * session, so nothing branch-structural can leak through the form.
 */
class TransactionCreatePageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function create_page_defers_customer_list_and_offers_no_counter_picker(): void
    {
        $otherBranch = Branch::factory()->create();
        $teller = User::factory()->create(['role' => UserRole::Teller]);
        Counter::factory()->create(['branch_id' => $teller->branch_id]);
        Counter::factory()->create(['branch_id' => $otherBranch->id]);
        Customer::factory()->count(3)->create();

        $response = $this->actingAs($teller)->get('/transactions/create');

        $response->assertStatus(200);
        // Customer records never reach the view — the register-or-match panel
        // searches customers.search via AJAX and carries only keyed-in fields.
        $response->assertViewMissing('customers');
        $response->assertViewMissing('counters');
        $response->assertDontSee('name="counter_id"', false);
        $response->assertSee('name="full_name"', false);
        $response->assertSee('name="id_number"', false);
    }
}
