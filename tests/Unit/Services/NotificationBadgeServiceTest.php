<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\System\NotificationBadgeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NotificationBadgeServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function service_can_be_instantiated(): void
    {
        $service = app(NotificationBadgeService::class);

        $this->assertInstanceOf(NotificationBadgeService::class, $service);
    }

    #[Test]
    public function unread_list_normalizes_stored_urls_to_same_site_paths(): void
    {
        $user = User::factory()->admin()->create();
        $service = app(NotificationBadgeService::class);

        $cases = [
            'http://staging.local.host:8080/compliance/flags/20/resolve' => '/compliance/flags/20/resolve',
            'https://example.com/path?x=1' => '/path?x=1',
            '/relative/path' => '/relative/path',
            '//evil.example.com/x' => null,
            'javascript:alert(1)' => null,
        ];

        foreach ($cases as $stored => $expected) {
            $user->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => 'test',
                'data' => ['type' => 'system_health_alert', 'message' => 'm', 'url' => $stored],
                'created_at' => now(),
            ]);
        }

        $urls = array_column($service->unreadList($user), 'url');

        sort($urls);

        $this->assertSame(
            [null, null, '/compliance/flags/20/resolve', '/path?x=1', '/relative/path'],
            $urls
        );
    }
}
