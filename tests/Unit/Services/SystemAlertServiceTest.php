<?php

namespace Tests\Unit\Services;

use App\Enums\SystemAlertLevel;
use App\Services\System\SystemAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SystemAlertServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function service_can_be_instantiated(): void
    {
        $service = app(SystemAlertService::class);

        $this->assertInstanceOf(SystemAlertService::class, $service);
    }

    #[Test]
    public function alert_is_persisted_when_email_delivery_fails(): void
    {
        $service = Mockery::mock(SystemAlertService::class)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('sendEmail')
            ->once()
            ->andThrow(new \RuntimeException('mail transport down'));

        $alert = $service->send('Position limit breached', SystemAlertLevel::Critical->value, []);

        // The alert row is the compliance record — a mailbox outage must not
        // roll it back with the delivery attempt.
        $this->assertDatabaseHas('system_alerts', [
            'id' => $alert->id,
            'message' => 'Position limit breached',
            'level' => SystemAlertLevel::Critical->value,
        ]);
    }
}
