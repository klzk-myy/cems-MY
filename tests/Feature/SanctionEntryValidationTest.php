<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\SanctionEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SanctionEntryValidationTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function store_sanction_entry_requires_entity_name(): void
    {
        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $this->actingAs($user);

        $response = $this->postJson('/compliance/sanctions/entries', [
            'list_id' => 1,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('entity_name');
    }

    #[Test]
    public function store_sanction_entry_requires_list_id(): void
    {
        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $this->actingAs($user);

        $response = $this->postJson('/compliance/sanctions/entries', [
            'entity_name' => 'Test Entity',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('list_id');
    }

    #[Test]
    public function store_sanction_entry_requires_entity_type(): void
    {
        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $this->actingAs($user);

        $response = $this->postJson('/compliance/sanctions/entries', [
            'entity_name' => 'Test Entity',
            'list_id' => 1,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('entity_type');
    }

    #[Test]
    public function store_sanction_entry_validates_entity_type_enum(): void
    {
        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $this->actingAs($user);

        $response = $this->postJson('/compliance/sanctions/entries', [
            'entity_name' => 'Test Entity',
            'list_id' => 1,
            'entity_type' => 'InvalidType',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('entity_type');
    }

    #[Test]
    public function store_sanction_entry_validates_list_id_exists(): void
    {
        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $this->actingAs($user);

        $response = $this->postJson('/compliance/sanctions/entries', [
            'entity_name' => 'Test Entity',
            'list_id' => 9999,
            'entity_type' => 'Individual',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('list_id');
    }

    #[Test]
    public function update_sanction_entry_requires_entity_name(): void
    {
        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $this->actingAs($user);

        $entry = SanctionEntry::factory()->create();

        $response = $this->putJson("/compliance/sanctions/entries/{$entry->id}", [
            'entity_name' => '',
            'entity_type' => 'Individual',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('entity_name');
    }

    #[Test]
    public function update_sanction_entry_allows_optional_fields(): void
    {
        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $this->actingAs($user);

        $entry = SanctionEntry::factory()->create();

        $response = $this->putJson("/compliance/sanctions/entries/{$entry->id}", [
            'entity_name' => 'Updated Entity',
            'entity_type' => 'Organization',
        ]);

        $response->assertStatus(302);
    }
}
