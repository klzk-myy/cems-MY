<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\SanctionEntry;
use App\Models\SanctionList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SanctionEntryUpdateDateTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function update_persists_listing_date(): void
    {
        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $this->actingAs($user);

        $list = SanctionList::factory()->create();
        $entry = SanctionEntry::factory()->create([
            'list_id' => $list->id,
            'listing_date' => null,
        ]);

        $response = $this->putJson("/compliance/sanctions/entries/{$entry->id}", [
            'entity_name' => $entry->entity_name,
            'entity_type' => $entry->entity_type?->value ?? 'Individual',
            'listing_date' => '2024-03-15',
            'list_source' => $list->name,
        ]);

        $response->assertRedirect();
        $this->assertEquals('2024-03-15', $entry->fresh()->listing_date?->format('Y-m-d'));
    }
}
