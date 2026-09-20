<?php

namespace Tests\Feature\Admin;

use App\Enums\ImportStatus;
use App\Enums\ImportTrigger;
use App\Enums\UpdateStatus;
use App\Enums\UserRole;
use App\Models\SanctionEntry;
use App\Models\SanctionImportLog;
use App\Models\SanctionList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature coverage for the admin-only sanction source management page:
 * listing, adding a source, removing a source (entries leave the
 * screening pool with it), and role gating.
 */
class SanctionSourceManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        $this->setMfaVerification($this->admin);
    }

    #[Test]
    public function admin_can_view_sources(): void
    {
        SanctionList::factory()->create(['name' => 'Test List', 'uploaded_by' => $this->admin->id]);

        $this->actingAs($this->admin)
            ->get(route('admin.sanctions.index'))
            ->assertOk()
            ->assertSee('Test List');
    }

    #[Test]
    public function index_shows_update_history(): void
    {
        $list = SanctionList::factory()->create(['name' => 'History List', 'uploaded_by' => $this->admin->id]);
        SanctionImportLog::factory()->create([
            'list_id' => $list->id,
            'imported_at' => now(),
            'records_added' => 120,
            'records_updated' => 5,
            'records_deactivated' => 2,
            'status' => ImportStatus::Partial,
            'triggered_by' => ImportTrigger::Manual,
            'user_id' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.sanctions.index'))
            ->assertOk()
            ->assertSee('Update History')
            ->assertSee('History List')
            ->assertSee('Partial')
            ->assertSee('Manual')
            ->assertSee($this->admin->username)
            ->assertSee('120');
    }

    #[Test]
    public function update_history_shows_removed_source_fallback(): void
    {
        $list = SanctionList::factory()->create(['uploaded_by' => $this->admin->id]);
        SanctionImportLog::factory()->create([
            'list_id' => $list->id,
            'imported_at' => now(),
            'status' => ImportStatus::Success,
            'triggered_by' => ImportTrigger::Scheduled,
        ]);
        $list->delete();

        $this->actingAs($this->admin)
            ->get(route('admin.sanctions.index'))
            ->assertOk()
            ->assertSee('Removed source');
    }

    #[Test]
    public function non_admin_roles_are_forbidden(): void
    {
        foreach ([UserRole::Teller, UserRole::Manager, UserRole::ComplianceOfficer, UserRole::Accountant] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->setMfaVerification($user);

            $this->actingAs($user)
                ->get(route('admin.sanctions.index'))
                ->assertForbidden();
        }
    }

    #[Test]
    public function admin_can_add_a_source(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.sanctions.store'), [
                'name' => 'Domestic Watchlist',
                'list_type' => 'Domestic',
                'source_url' => 'https://example.com/list.csv',
                'source_format' => 'CSV',
            ])
            ->assertRedirect(route('admin.sanctions.index'));

        $this->assertDatabaseHas('sanction_lists', [
            'name' => 'Domestic Watchlist',
            'slug' => 'domestic-watchlist',
            'list_type' => 'Domestic',
            'update_status' => UpdateStatus::NeverRun->value,
            'is_active' => true,
        ]);
    }

    #[Test]
    public function source_format_is_required_when_url_is_given(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.sanctions.store'), [
                'name' => 'Broken List',
                'list_type' => 'Internal',
                'source_url' => 'https://example.com/list.csv',
            ])
            ->assertSessionHasErrors('source_format');
    }

    #[Test]
    public function removing_a_source_soft_deletes_its_entries(): void
    {
        $list = SanctionList::factory()->create(['uploaded_by' => $this->admin->id]);
        $entry = SanctionEntry::factory()->create(['list_id' => $list->id]);

        $this->actingAs($this->admin)
            ->delete(route('admin.sanctions.destroy', $list))
            ->assertRedirect(route('admin.sanctions.index'));

        $this->assertSoftDeleted('sanction_lists', ['id' => $list->id]);
        $this->assertSoftDeleted('sanction_entries', ['id' => $entry->id]);
        // Screening pools must no longer see the removed source's entries.
        $this->assertEquals(0, SanctionEntry::count());
    }
}
