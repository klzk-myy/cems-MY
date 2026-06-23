<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
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
        $response->assertNotFound();
    }
}
