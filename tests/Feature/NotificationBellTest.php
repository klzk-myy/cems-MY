<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use App\Notifications\SystemHealthAlertNotification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NotificationBellTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    private function createNotification(User $user, string $message = 'Disk space critical'): DatabaseNotification
    {
        return $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => SystemHealthAlertNotification::class,
            'data' => [
                'type' => 'system_health_alert',
                'level_label' => 'Critical',
                'message' => $message,
                'url' => null,
            ],
            'created_at' => now(),
        ]);
    }

    private function createDlqTransaction(): Transaction
    {
        $transaction = Transaction::factory()->create();
        $transaction->is_dlq = true;
        $transaction->save();

        return $transaction;
    }

    #[Test]
    public function bell_shows_unread_notification_count_and_items(): void
    {
        $admin = User::factory()->admin()->create();
        $this->createNotification($admin, 'Disk space critical');
        $this->createNotification($admin, 'Large cash deposit flagged');

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('aria-label="Notifications"', false)
            ->assertSee('Mark all as read')
            ->assertSee('Disk space critical')
            ->assertSee('Large cash deposit flagged');
    }

    #[Test]
    public function dlq_chip_data_is_admin_only(): void
    {
        $this->createDlqTransaction();

        // The chip lives inside an Alpine x-if template, so its markup is
        // always present in the HTML; the visibility is driven by the dlq
        // count seeded into the component via data-dlq. Admins get the live
        // count, everyone else 0.
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-dlq="1"', false);

        $teller = User::factory()->teller()->create();
        $this->actingAs($teller)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-dlq="0"', false);
    }

    #[Test]
    public function mark_all_read_marks_every_notification_as_read(): void
    {
        $admin = User::factory()->admin()->create();
        $first = $this->createNotification($admin);
        $second = $this->createNotification($admin);

        $this->actingAs($admin)
            ->post(route('notifications.read-all'))
            ->assertRedirect();

        $this->assertNotNull($first->fresh()->read_at);
        $this->assertNotNull($second->fresh()->read_at);
    }

    #[Test]
    public function mark_read_marks_only_the_target_notification(): void
    {
        $admin = User::factory()->admin()->create();
        $target = $this->createNotification($admin);
        $other = $this->createNotification($admin);

        $this->actingAs($admin)
            ->post(route('notifications.read', $target))
            ->assertRedirect();

        $this->assertNotNull($target->fresh()->read_at);
        $this->assertNull($other->fresh()->read_at);
    }

    #[Test]
    public function mark_read_with_redirect_navigates_to_relative_url(): void
    {
        $admin = User::factory()->admin()->create();
        $target = $this->createNotification($admin);

        $this->actingAs($admin)
            ->post(route('notifications.read', $target), ['redirect' => '/transactions'])
            ->assertRedirect('/transactions');

        $this->assertNotNull($target->fresh()->read_at);
    }

    #[Test]
    public function mark_read_rejects_external_redirect(): void
    {
        $admin = User::factory()->admin()->create();
        $target = $this->createNotification($admin);

        // Protocol-relative and absolute URLs must fall back to back()
        // rather than bouncing the user off-site.
        $this->actingAs($admin)
            ->post(route('notifications.read', $target), ['redirect' => '//evil.example.com/x'])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('notifications.read', $target), ['redirect' => 'https://evil.example.com'])
            ->assertRedirect();
    }

    #[Test]
    public function user_cannot_manage_another_users_notification(): void
    {
        $owner = User::factory()->admin()->create();
        $intruder = User::factory()->teller()->create();
        $notification = $this->createNotification($owner);

        $this->actingAs($intruder)
            ->post(route('notifications.read', $notification))
            ->assertForbidden();

        $this->assertNull($notification->fresh()->read_at);
    }

    #[Test]
    public function unread_count_endpoint_returns_badge_data(): void
    {
        $admin = User::factory()->admin()->create();
        $this->createNotification($admin);
        $this->createDlqTransaction();

        $this->actingAs($admin)
            ->getJson(route('notifications.unread-count'))
            ->assertOk()
            ->assertJsonPath('data.count', 1)
            ->assertJsonPath('data.dlq_count', 1);

        $teller = User::factory()->teller()->create();

        $this->actingAs($teller)
            ->getJson(route('notifications.unread-count'))
            ->assertOk()
            ->assertJsonPath('data.count', 0)
            ->assertJsonPath('data.dlq_count', 0);
    }

    #[Test]
    public function guests_cannot_mark_notifications_as_read(): void
    {
        // Web requests redirect to login; JSON requests 401.
        $this->post(route('notifications.read-all'))->assertRedirect();
        $this->getJson(route('notifications.unread-count'))->assertUnauthorized();
    }

    #[Test]
    public function unread_count_polling_does_not_extend_the_session_idle_timer(): void
    {
        // The bell polls unread-count every 60s; if that request stamped
        // last_activity, an open tab would silently defeat the idle-session-
        // timeout security control (BNM compliance).
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('dashboard'));

        $fixedTimestamp = now()->subMinutes(5)->timestamp;
        session(['last_activity' => $fixedTimestamp]);

        $this->actingAs($admin)
            ->getJson(route('notifications.unread-count'))
            ->assertOk();

        $this->assertSame($fixedTimestamp, session('last_activity'));
    }
}
