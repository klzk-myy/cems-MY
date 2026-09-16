<?php

namespace Tests\Unit\Services\Compliance;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Compliance\AlertTriageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Covers AlertTriageService::notifyAvailableOfficers — the single method all
 * compliance escalation paths now share (creation, confirmation, screening,
 * monitors).
 */
class AlertTriageNotificationTest extends TestCase
{
    use RefreshDatabase;

    private AlertTriageService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->service = app(AlertTriageService::class);
    }

    #[Test]
    public function it_notifies_all_active_compliance_officers_and_managers(): void
    {
        $officer = User::factory()->create(['role' => UserRole::ComplianceOfficer, 'is_active' => true]);
        $manager = User::factory()->create(['role' => UserRole::Manager, 'is_active' => true]);
        $teller = User::factory()->create(['role' => UserRole::Teller, 'is_active' => true]);
        $inactive = User::factory()->create(['role' => UserRole::ComplianceOfficer, 'is_active' => false]);

        $notification = new TestNotification;

        $count = $this->service->notifyAvailableOfficers($notification);

        $this->assertSame(2, $count);
        Notification::assertSentTo($officer, TestNotification::class);
        Notification::assertSentTo($manager, TestNotification::class);
        Notification::assertNotSentTo($teller, TestNotification::class);
        Notification::assertNotSentTo($inactive, TestNotification::class);
    }

    #[Test]
    public function it_excludes_the_actor_when_an_exclusion_is_given(): void
    {
        $actor = User::factory()->create(['role' => UserRole::ComplianceOfficer, 'is_active' => true]);
        $other = User::factory()->create(['role' => UserRole::Manager, 'is_active' => true]);

        $count = $this->service->notifyAvailableOfficers(
            new TestNotification,
            $actor->id
        );

        $this->assertSame(1, $count);
        Notification::assertNotSentTo($actor, TestNotification::class);
        Notification::assertSentTo($other, TestNotification::class);
    }

    #[Test]
    public function it_returns_zero_when_no_officers_exist(): void
    {
        $this->assertSame(
            0,
            $this->service->notifyAvailableOfficers(new TestNotification)
        );
    }
}

final class TestNotification extends BaseNotification
{
    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }
}
