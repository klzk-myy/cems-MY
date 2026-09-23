<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Compliance\SanctionEntry;
use App\Models\Compliance\SanctionList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Admin sanction-list sync (web): the manual "update list" action that
 * downloads and imports the external feed. The remote fetch is faked; the
 * tests pin the route's auth (admin + MFA step-up), the success flash, and
 * the failure surface.
 */
class AdminSanctionsSyncTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => UserRole::Admin]);
    }

    #[Test]
    public function sync_imports_entries_from_the_remote_feed(): void
    {
        Http::fake([
            '*' => Http::response([
                'results' => [
                    [
                        'id' => 'sync-001',
                        'name' => ['John Doe'],
                        'entity_type' => 'Person',
                        'nationality' => 'US',
                    ],
                ],
            ], 200),
        ]);

        $list = SanctionList::factory()->create([
            'source_url' => 'https://api.opensanctions.org/sync-test',
            'slug' => 'sync-test-list',
            'is_active' => true,
        ]);

        $this->actingAs($this->admin)
            ->withSession($this->mfaSession())
            ->post(route('admin.sanctions.sync', $list))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(
            1,
            SanctionEntry::where('list_id', $list->id)->count(),
            'The sync must import the entries from the feed'
        );
    }

    #[Test]
    public function sync_failure_surfaces_an_error_flash_without_importing(): void
    {
        // Skip the download service's retry backoff — one attempt is enough
        // to pin the failure surface.
        config(['sanctions.download.retry_attempts' => 1]);

        Http::fake([
            '*' => Http::response('service unavailable', 500),
        ]);

        $list = SanctionList::factory()->create([
            'source_url' => 'https://api.opensanctions.org/broken-feed',
            'slug' => 'broken-feed-list',
            'is_active' => true,
        ]);

        $this->actingAs($this->admin)
            ->withSession($this->mfaSession())
            ->post(route('admin.sanctions.sync', $list))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(0, SanctionEntry::where('list_id', $list->id)->count());
    }

    #[Test]
    public function non_admin_cannot_trigger_a_sync(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $list = SanctionList::factory()->create([
            'source_url' => 'https://api.opensanctions.org/sync-test',
            'is_active' => true,
        ]);

        $this->actingAs($manager)
            ->withSession($this->mfaSession())
            ->post(route('admin.sanctions.sync', $list))
            ->assertForbidden();
    }

    /**
     * Session payload satisfying the mfa.verified step-up on the route group.
     *
     * @return array<string, mixed>
     */
    private function mfaSession(): array
    {
        return ['mfa_verified' => true, 'mfa_verified_at' => now()->timestamp];
    }
}
