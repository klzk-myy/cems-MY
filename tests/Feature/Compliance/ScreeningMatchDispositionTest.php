<?php

namespace Tests\Feature\Compliance;

use App\Enums\SystemAlertLevel;
use App\Models\AdverseMediaEntry;
use App\Models\Compliance\SanctionEntry;
use App\Models\Compliance\SanctionList;
use App\Models\Compliance\ScreeningResult;
use App\Models\Customer;
use App\Models\SystemAlert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ScreeningMatchDispositionTest extends TestCase
{
    use RefreshDatabase;

    private User $officer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->officer = User::factory()->create(['role' => 'compliance_officer']);
    }

    #[Test]
    public function guest_is_blocked_from_matches_index(): void
    {
        $this->get(route('compliance.screening.matches.index'))
            ->assertRedirect(route('login'));
    }

    #[Test]
    public function role_without_view_screening_results_is_blocked_from_matches(): void
    {
        $accountant = User::factory()->create(['role' => 'accountant']);

        $this->actingAs($accountant)
            ->get(route('compliance.screening.matches.index'))
            ->assertForbidden();
    }

    #[Test]
    public function teller_can_view_matches_but_cannot_dispose_them(): void
    {
        // Tellers hold view_screening_results by default: read-only access
        // to screening results, while disposition writes stay compliance-only.
        $teller = User::factory()->create(['role' => 'teller']);
        $result = ScreeningResult::factory()->flagged()->create();

        $this->actingAs($teller)
            ->get(route('compliance.screening.matches.index'))
            ->assertOk();

        $this->actingAs($teller)
            ->get(route('compliance.screening.matches.show', $result))
            ->assertOk();

        $this->actingAs($teller)
            ->post(route('compliance.screening.matches.confirm', $result), ['reason' => 'test'])
            ->assertForbidden();

        $this->actingAs($teller)
            ->post(route('compliance.screening.matches.dismiss', $result), ['reason' => 'test'])
            ->assertForbidden();
    }

    #[Test]
    public function index_lists_pending_flag_and_block_results_only(): void
    {
        $pendingFlag = ScreeningResult::factory()->flagged()->create();
        $pendingBlock = ScreeningResult::factory()->blocked()->create();
        ScreeningResult::factory()->clear()->create();
        ScreeningResult::factory()->blocked()->create(['dispositioned_at' => now(), 'disposition' => 'dismissed']);

        $response = $this->actingAs($this->officer)
            ->get(route('compliance.screening.matches.index'));

        $response->assertStatus(200)->assertViewIs('compliance.screening.matches.index');

        $results = $response->viewData('results');
        $this->assertSame(2, $results->count());
        $this->assertTrue($results->contains('id', $pendingFlag->id));
        $this->assertTrue($results->contains('id', $pendingBlock->id));

        $filtered = $this->actingAs($this->officer)
            ->get(route('compliance.screening.matches.index', ['status' => 'block']))
            ->viewData('results');

        $this->assertTrue($filtered->every(fn (ScreeningResult $r) => $r->result === 'block'));
    }

    #[Test]
    public function show_displays_result_detail(): void
    {
        $result = ScreeningResult::factory()->flagged()->create();

        $this->actingAs($this->officer)
            ->get(route('compliance.screening.matches.show', $result))
            ->assertStatus(200)
            ->assertViewIs('compliance.screening.matches.show')
            ->assertViewHas('result', fn (ScreeningResult $viewResult) => $viewResult->id === $result->id);
    }

    #[Test]
    public function confirm_requires_reason(): void
    {
        $result = ScreeningResult::factory()->blocked()->create();

        $this->actingAs($this->officer)
            ->from(route('compliance.screening.matches.show', $result))
            ->post(route('compliance.screening.matches.confirm', $result), ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $result->refresh();
        $this->assertTrue($result->isPending());
    }

    #[Test]
    public function confirm_freezes_customer_blocks_transactions_and_records_disposition(): void
    {
        $list = SanctionList::factory()->create();
        $entry = SanctionEntry::factory()->create(['list_id' => $list->id]);
        $customer = Customer::factory()->create(['is_active' => true]);
        $result = ScreeningResult::factory()->blocked()->create([
            'customer_id' => $customer->id,
            'sanction_entry_id' => $entry->id,
        ]);

        $this->actingAs($this->officer)
            ->post(route('compliance.screening.matches.confirm', $result), [
                'reason' => 'Confirmed identity match on UNSCR list',
            ])
            ->assertRedirect(route('compliance.screening.matches.index'))
            ->assertSessionHas('success');

        $customer->refresh();
        $result->refresh();

        $this->assertTrue($customer->is_frozen);
        $this->assertTrue((bool) $customer->transactions_blocked);
        $this->assertSame('confirmed', $result->disposition);
        $this->assertSame('Confirmed identity match on UNSCR list', $result->disposition_reason);
        $this->assertSame($this->officer->id, (int) $result->dispositioned_by);

        $alert = SystemAlert::where('source', 'sanctions_screening')->first();
        $this->assertNotNull($alert);
        $this->assertSame(SystemAlertLevel::Critical, $alert->level);
    }

    #[Test]
    public function confirm_rejects_inactive_customer(): void
    {
        $customer = Customer::factory()->create(['is_active' => false]);
        $result = ScreeningResult::factory()->flagged()->create(['customer_id' => $customer->id]);

        $this->actingAs($this->officer)
            ->post(route('compliance.screening.matches.confirm', $result), [
                'reason' => 'Positive match, applicant rejected',
            ])
            ->assertRedirect(route('compliance.screening.matches.index'));

        $customer->refresh();
        $this->assertFalse((bool) $customer->is_active);
    }

    #[Test]
    public function confirm_adverse_media_match_escalates_without_freezing(): void
    {
        $entry = AdverseMediaEntry::factory()->create(['severity' => 'high']);
        $customer = Customer::factory()->create(['is_active' => true]);
        $result = ScreeningResult::factory()->flagged()->create([
            'customer_id' => $customer->id,
            'source' => 'adverse_media',
            'adverse_media_entry_id' => $entry->id,
        ]);

        $this->actingAs($this->officer)
            ->post(route('compliance.screening.matches.confirm', $result), [
                'reason' => 'Confirmed identity match on adverse media article',
            ])
            ->assertRedirect(route('compliance.screening.matches.index'))
            ->assertSessionHas('success');

        $customer->refresh();
        $result->refresh();

        $this->assertFalse((bool) $customer->is_frozen);
        $this->assertFalse((bool) $customer->transactions_blocked);
        $this->assertTrue((bool) $customer->is_active);
        $this->assertSame('confirmed', $result->disposition);

        $alert = SystemAlert::where('source', 'adverse_media_screening')->first();
        $this->assertNotNull($alert);
    }

    #[Test]
    public function dismiss_marks_result_as_dismissed(): void
    {
        $result = ScreeningResult::factory()->flagged()->create();

        $this->actingAs($this->officer)
            ->post(route('compliance.screening.matches.dismiss', $result), [
                'reason' => 'False positive - different person',
            ])
            ->assertRedirect(route('compliance.screening.matches.index'))
            ->assertSessionHas('success');

        $result->refresh();

        $this->assertSame('dismissed', $result->disposition);
        $this->assertSame('False positive - different person', $result->disposition_reason);
        $this->assertSame($this->officer->id, (int) $result->dispositioned_by);
        $this->assertFalse((bool) $result->customer->is_frozen);
    }
}
