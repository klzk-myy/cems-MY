<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\TransactionConfirmation;
use App\Models\User;
use App\Notifications\LargeTransactionNotification;
use App\Notifications\NotificationDigestMail;
use App\Services\System\NotificationDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NotificationPreferencesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function preferences_page_loads_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('notifications.preferences'));

        $response->assertOk();
        $response->assertSee('Notification Preferences');
        $response->assertSee('digest_enabled');
    }

    #[Test]
    public function guest_cannot_load_preferences_page(): void
    {
        $this->get(route('notifications.preferences'))->assertRedirect();
    }

    #[Test]
    public function saving_persists_only_known_type_keys(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('notifications.preferences.update'), [
            'types' => [
                'large_transaction' => '1',
                'not_a_real_key' => '1',
            ],
            'digest_enabled' => '1',
        ]);

        $response->assertRedirect();

        $prefs = $user->fresh()->notification_preferences;

        $this->assertTrue($prefs['large_transaction']);
        // Unticked known types are stored as explicit opt-outs.
        $this->assertFalse($prefs['transaction_approved']);
        $this->assertArrayNotHasKey('not_a_real_key', $prefs);
        $this->assertTrue($prefs['digest_enabled']);
    }

    #[Test]
    public function digest_opt_out_is_stored_when_checkbox_absent(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('notifications.preferences.update'), [
            'types' => ['report_email' => '1'],
        ]);

        $prefs = $user->fresh()->notification_preferences;

        $this->assertFalse($prefs['digest_enabled']);
        $this->assertTrue($prefs['report_email']);
    }

    #[Test]
    public function dispatcher_respects_saved_opt_out(): void
    {
        $user = User::factory()->create([
            'notification_preferences' => ['large_transaction' => false],
        ]);

        $notification = new LargeTransactionNotification(
            Transaction::factory()->create(),
            TransactionConfirmation::query()->make()
        );

        $this->assertFalse(NotificationDispatcher::shouldDeliver($user, $notification));

        $user->update(['notification_preferences' => ['large_transaction' => true]]);
        $this->assertTrue(NotificationDispatcher::shouldDeliver($user, $notification));
    }

    #[Test]
    public function opted_out_user_is_excluded_from_digest(): void
    {
        Mail::fake();

        $optedIn = User::factory()->create(['is_active' => true]);
        $optedOut = User::factory()->create([
            'is_active' => true,
            'notification_preferences' => ['digest_enabled' => false],
        ]);

        foreach ([$optedIn, $optedOut] as $user) {
            $user->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => 'App\Notifications\ReportEmailNotification',
                'data' => ['type' => 'test', 'url' => null],
            ]);
        }

        $this->artisanCommand('notifications:send-digest')->assertSuccessful();

        Mail::sent(NotificationDigestMail::class, function (NotificationDigestMail $mail) use ($optedIn, $optedOut) {
            $recipients = array_keys($mail->to);

            $this->assertContains($optedIn->email, $recipients);
            $this->assertNotContains($optedOut->email, $recipients);
        });
    }
}
