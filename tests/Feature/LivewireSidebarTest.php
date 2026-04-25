<?php

namespace Tests\Feature;

use App\Livewire\Sidebar;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LivewireSidebarTest extends TestCase
{
    use RefreshDatabase;

    public function test_sidebar_component_can_be_rendered(): void
    {
        Livewire::test(Sidebar::class)
            ->assertStatus(200);
    }

    public function test_sidebar_sets_active_route_on_mount(): void
    {
        $component = Livewire::test(Sidebar::class);

        // The activeRoute should be set to the current request path
        $this->assertNotEmpty($component->instance()->activeRoute);
    }

    public function test_is_active_returns_true_for_matching_path(): void
    {
        $component = Livewire::test(Sidebar::class);

        // Manually set activeRoute to test the method
        $component->set('activeRoute', 'dashboard');

        $result = $component->instance()->isActive('dashboard');
        $this->assertTrue($result);
    }

    public function test_is_active_returns_false_for_non_matching_path(): void
    {
        $component = Livewire::test(Sidebar::class);

        // Set activeRoute to 'dashboard' and check for 'nonexistent'
        $component->set('activeRoute', 'dashboard');

        $result = $component->instance()->isActive('nonexistent');
        $this->assertFalse($result);
    }

    public function test_sidebar_renders_navigation_links(): void
    {
        Livewire::test(Sidebar::class)
            ->assertSee('Dashboard')
            ->assertSee('Transactions')
            ->assertSee('Customers')
            ->assertSee('Compliance');
    }

    public function test_sidebar_shows_login_link_when_not_authenticated(): void
    {
        Livewire::test(Sidebar::class)
            ->assertSee('Log in');
    }

    public function test_sidebar_shows_logout_when_authenticated(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Sidebar::class)
            ->assertSee('Log out');
    }
}
