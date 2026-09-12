<?php

namespace Tests\Feature\Api;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SanctionsWebhookTest extends TestCase
{
    #[Test]
    public function sanctions_health_rejects_without_token(): void
    {
        config(['sanctions.webhook.token' => 'test-webhook-token']);

        $response = $this->getJson(route('api.v1.webhooks.sanctions.health'));

        $response->assertUnauthorized();
    }

    #[Test]
    public function sanctions_health_is_reachable_with_valid_token(): void
    {
        config(['sanctions.webhook.token' => 'test-webhook-token']);

        $response = $this->getJson(
            route('api.v1.webhooks.sanctions.health'),
            ['X-Webhook-Token' => 'test-webhook-token']
        );

        $response->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('service', 'sanctions-webhook');
    }

    #[Test]
    public function sanctions_health_rejects_when_no_token_configured(): void
    {
        config(['sanctions.webhook.token' => '']);

        $response = $this->getJson(
            route('api.v1.webhooks.sanctions.health'),
            ['X-Webhook-Token' => 'anything']
        );

        $response->assertUnauthorized();
    }

    #[Test]
    public function sanctions_update_rejects_without_token(): void
    {
        $response = $this->postJson(route('api.v1.webhooks.sanctions.update'));

        $response->assertUnauthorized();
    }
}
