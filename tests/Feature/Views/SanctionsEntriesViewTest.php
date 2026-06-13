<?php

namespace Tests\Feature\Views;

use App\Enums\UserRole;
use App\Models\SanctionEntry;
use App\Models\SanctionList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SanctionsEntriesViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_form_renders_with_csrf_and_named_action(): void
    {
        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);

        $response = $this->actingAs($user)->get(route('compliance.sanctions.entries.create'));

        $response->assertStatus(200);
        $response->assertViewIs('compliance.sanctions.entries.create');
        $response->assertSee('name="_token"', false);
        $response->assertSee('action="'.e(route('compliance.sanctions.entries.store')).'"', false);
    }

    public function test_store_creates_sanction_entry(): void
    {
        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $list = SanctionList::factory()->create();

        $response = $this->actingAs($user)->post(route('compliance.sanctions.entries.store'), [
            'list_id' => $list->id,
            'entity_name' => 'ACME Corp',
            'entity_type' => 'Organization',
        ]);

        $response->assertRedirect(route('compliance.sanctions.entries.index'));
        $this->assertDatabaseHas('sanction_entries', ['entity_name' => 'ACME Corp']);
    }

    public function test_store_accepts_details_and_aliases_as_strings(): void
    {
        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $list = SanctionList::factory()->create();

        $response = $this->actingAs($user)->post(route('compliance.sanctions.entries.store'), [
            'list_id' => $list->id,
            'entity_name' => 'Alias Test',
            'list_source' => 'ofac',
            'entity_type' => 'Individual',
            'aliases' => "Alias One\nAlias Two\n",
            'details' => 'Some additional details',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('sanction_entries', [
            'entity_name' => 'Alias Test',
            'details' => 'Some additional details',
        ]);

        $entry = SanctionEntry::where('entity_name', 'Alias Test')->first();
        $this->assertContains('Alias One', $entry->aliases);
        $this->assertContains('Alias Two', $entry->aliases);
    }

    public function test_store_rejects_details_as_array(): void
    {
        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $list = SanctionList::factory()->create();

        $response = $this->actingAs($user)->post(route('compliance.sanctions.entries.store'), [
            'list_id' => $list->id,
            'entity_name' => 'Array Details Test',
            'entity_type' => 'Individual',
            'details' => ['foo' => 'bar'],
        ]);

        $response->assertSessionHasErrors('details');
    }

    public function test_store_rejects_invalid_entity_type(): void
    {
        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $list = SanctionList::factory()->create();

        $response = $this->actingAs($user)->post(route('compliance.sanctions.entries.store'), [
            'list_id' => $list->id,
            'entity_name' => 'Test',
            'entity_type' => 'invalid',
        ]);

        $response->assertSessionHasErrors('entity_type');
    }
}
