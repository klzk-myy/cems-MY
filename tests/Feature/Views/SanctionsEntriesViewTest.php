<?php

namespace Tests\Feature\Views;

use App\Enums\UserRole;
use App\Models\SanctionEntry;
use App\Models\SanctionList;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SanctionsEntriesViewTest extends TestCase
{
    use DatabaseTransactions;

    protected User $complianceOfficer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->complianceOfficer = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
    }

    #[Test]
    public function create_form_renders_with_csrf_and_named_action(): void
    {
        $response = $this->actingAs($this->complianceOfficer)->get(route('compliance.sanctions.entries.create'));

        $response->assertStatus(200);
        $response->assertViewIs('compliance.sanctions.entries.create');
        $response->assertSee('name="_token"', false);
        $response->assertSee('action="'.e(route('compliance.sanctions.entries.store')).'"', false);
    }

    #[Test]
    public function store_creates_sanction_entry(): void
    {
        $list = SanctionList::factory()->create();

        $response = $this->actingAs($this->complianceOfficer)->post(route('compliance.sanctions.entries.store'), [
            'list_id' => $list->id,
            'entity_name' => 'ACME Corp',
            'entity_type' => 'Organization',
        ]);

        $response->assertRedirect(route('compliance.sanctions.entries.index'));
        $this->assertDatabaseHas('sanction_entries', ['entity_name' => 'ACME Corp']);
    }

    #[Test]
    public function store_accepts_details_and_aliases_as_strings(): void
    {
        $list = SanctionList::factory()->create();

        $response = $this->actingAs($this->complianceOfficer)->post(route('compliance.sanctions.entries.store'), [
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

    #[Test]
    public function store_rejects_details_as_array(): void
    {
        $list = SanctionList::factory()->create();

        $response = $this->actingAs($this->complianceOfficer)->post(route('compliance.sanctions.entries.store'), [
            'list_id' => $list->id,
            'entity_name' => 'Array Details Test',
            'entity_type' => 'Individual',
            'details' => ['foo' => 'bar'],
        ]);

        $response->assertSessionHasErrors('details');
    }

    #[Test]
    public function store_rejects_invalid_entity_type(): void
    {
        $list = SanctionList::factory()->create();

        $response = $this->actingAs($this->complianceOfficer)->post(route('compliance.sanctions.entries.store'), [
            'list_id' => $list->id,
            'entity_name' => 'Test',
            'entity_type' => 'invalid',
        ]);

        $response->assertSessionHasErrors('entity_type');
    }

    #[Test]
    public function edit_form_binds_model_data(): void
    {
        $entry = SanctionEntry::factory()->create([
            'entity_name' => 'ACME Corp',
            'list_source' => 'ofac',
            'entity_type' => 'Organization',
        ]);

        $response = $this->actingAs($this->complianceOfficer)->get(route('compliance.sanctions.entries.edit', $entry));

        $response->assertStatus(200);
        $response->assertSee('value="ACME Corp"', false);
        $response->assertSee('action="'.e(route('compliance.sanctions.entries.update', $entry)).'"', false);
        $response->assertSee('name="_method" value="PUT"', false);
    }

    #[Test]
    public function update_modifies_sanction_entry(): void
    {
        $entry = SanctionEntry::factory()->create([
            'entity_name' => 'Old Name',
            'list_source' => 'ofac',
            'entity_type' => 'Organization',
        ]);

        $response = $this->actingAs($this->complianceOfficer)->put(route('compliance.sanctions.entries.update', $entry), [
            'entity_name' => 'New Name',
            'list_source' => 'un',
            'entity_type' => 'Individual',
        ]);

        $response->assertRedirect(route('compliance.sanctions.entries.show', $entry));
        $entry->refresh();
        $this->assertEquals('New Name', $entry->entity_name);
        $this->assertEquals('un', $entry->list_source);
    }

    #[Test]
    public function update_succeeds_without_list_source(): void
    {
        $entry = SanctionEntry::factory()->create([
            'entity_name' => 'Test',
            'list_source' => 'ofac',
            'entity_type' => 'Organization',
        ]);

        $response = $this->actingAs($this->complianceOfficer)->put(route('compliance.sanctions.entries.update', $entry), [
            'entity_name' => 'Updated Name',
            'entity_type' => 'Organization',
        ]);

        $response->assertRedirect();
        $entry->refresh();
        $this->assertEquals('Updated Name', $entry->entity_name);
    }

    #[Test]
    public function update_rejects_invalid_entity_type(): void
    {
        $entry = SanctionEntry::factory()->create([
            'entity_name' => 'Test',
            'list_source' => 'ofac',
            'entity_type' => 'Organization',
        ]);

        $response = $this->actingAs($this->complianceOfficer)->put(route('compliance.sanctions.entries.update', $entry), [
            'entity_name' => 'Test',
            'list_source' => 'ofac',
            'entity_type' => 'Invalid',
        ]);

        $response->assertSessionHasErrors('entity_type');
    }

    #[Test]
    public function update_persists_address_fields(): void
    {
        $entry = SanctionEntry::factory()->create([
            'entity_name' => 'Test',
            'list_source' => 'ofac',
            'entity_type' => 'Organization',
        ]);

        $response = $this->actingAs($this->complianceOfficer)->put(route('compliance.sanctions.entries.update', $entry), [
            'entity_name' => 'Test',
            'list_source' => 'ofac',
            'entity_type' => 'Organization',
            'address' => '123 Main St',
            'city' => 'New York',
            'country' => 'United States',
            'postal_code' => '10001',
        ]);

        $response->assertRedirect();
        $entry->refresh();
        $this->assertEquals('123 Main St', $entry->address);
        $this->assertEquals('New York', $entry->city);
    }

    #[Test]
    public function entries_index_does_not_show_hardcoded_dummy_data(): void
    {
        SanctionEntry::factory()->count(3)->create();

        $response = $this->actingAs($this->complianceOfficer)->get(route('compliance.sanctions.entries.index'));

        $response->assertStatus(200);
        $response->assertDontSee('John Doe', false);
        $response->assertDontSee('OFAC-12345', false);
        $response->assertDontSee('123 Main Street', false);
    }
}
