<?php

namespace Tests\Feature\Api;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SanctionsWebhookTest extends TestCase
{
    #[Test]
    public function sanctions_health_is_reachable_without_sanctum(): void
    {
        $response = $this->getJson(route('api.v1.webhooks.sanctions.health'));

        $response->assertOk();
    }

    #[Test]
    public function sanctions_update_rejects_without_token(): void
    {
        $response = $this->postJson(route('api.v1.webhooks.sanctions.update'));

        $response->assertUnauthorized();
    }
}
