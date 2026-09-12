<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SetupControllerTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_shows_setup_page_in_development(): void
    {
        $this->app->instance('env', 'development');

        $response = $this->get(route('setup.index'));
        $response->assertOk();
    }

    #[Test]
    public function setup_routes_are_blocked_in_production_after_setup_complete(): void
    {
        $this->app->instance('env', 'production');

        User::factory()->create();
        Currency::factory()->create();
        ExchangeRate::factory()->create();
        Branch::factory()->create();

        $response = $this->get(route('setup.index'));
        // Middleware hard-blocks with 403 Forbidden once setup is complete
        // (commit 10a44f5a: "block setup wizard in all environments").
        $response->assertForbidden();
    }

    #[Test]
    public function complete_setup_rejects_an_empty_session_without_marking_setup_done(): void
    {
        // No env override: setting app 'env' away from 'testing' disables
        // the CSRF skip (VerifyCsrfToken::runningUnitTests) and 419s the POST.
        $response = $this->postJson(route('setup.complete'));

        $response->assertStatus(422)->assertJson(['success' => false]);

        // The immutable marker must not be set — otherwise every /setup
        // route locks behind EnsureSetupAccessible with no admin able to
        // reset (bricked install).
        if (Schema::hasTable('setup_state')) {
            $this->assertDatabaseMissing('setup_state', ['id' => 1]);
        }
    }
}
