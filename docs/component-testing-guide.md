# CEMS-MY Component Testing Guide

## Overview

This guide covers testing strategies for Blade components in CEMS-MY.

## Component Test Structure

### Class-Based Component Tests

**Location:** `tests/Unit/View/Components/`

#### Alert Component Test

```php
<?php

namespace Tests\Unit\View\Components;

use App\View\Components\Alert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertTest extends TestCase
{
    use RefreshDatabase;

    public function test_alert_renders_with_type(): void
    {
        $component = new Alert(type: 'success', title: 'Success!');
        
        $this->assertEquals('success', $component->type);
        $this->assertEquals('Success!', $component->title);
        $this->assertTrue($component->shouldRender());
    }

    public function test_alert_should_not_render_without_content(): void
    {
        $component = new Alert();
        
        // Mock empty slot
        $component->slot = '';
        
        $this->assertFalse($component->shouldRender());
    }

    public function test_alert_style_classes(): void
    {
        $success = new Alert(type: 'success');
        $error = new Alert(type: 'error');
        $warning = new Alert(type: 'warning');
        $info = new Alert(type: 'info');

        $this->assertStringContainsString('bg-green-50', $success->getStyleClasses());
        $this->assertStringContainsString('bg-red-50', $error->getStyleClasses());
        $this->assertStringContainsString('bg-yellow-50', $warning->getStyleClasses());
        $this->assertStringContainsString('bg-blue-50', $info->getStyleClasses());
    }

    public function test_alert_icon_paths(): void
    {
        $component = new Alert(type: 'success');
        
        $this->assertNotEmpty($component->getIconPath());
    }

    public function test_alert_renders_view(): void
    {
        $component = new Alert(type: 'error', title: 'Test Error');
        
        $view = $component->render();
        
        $this->assertInstanceOf(\Illuminate\View\View::class, $view);
    }
}
```

#### DataTable Component Test

```php
<?php

namespace Tests\Unit\View\Components;

use App\Models\User;
use App\View\Components\DataTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DataTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_datatable_has_data(): void
    {
        User::factory()->count(3)->create();
        
        $users = User::paginate(10);
        $component = new DataTable(data: $users, columns: [
            ['key' => 'name', 'label' => 'Name'],
        ]);

        $this->assertTrue($component->hasData());
    }

    public function test_datatable_empty_state(): void
    {
        $component = new DataTable(
            data: collect([])->paginate(10),
            columns: [['key' => 'name', 'label' => 'Name']],
            emptyMessage: 'No users found'
        );

        $this->assertFalse($component->hasData());
        $this->assertEquals(2, $component->getColumnCount()); // name + actions
    }

    public function test_datatable_column_count(): void
    {
        $component = new DataTable(columns: [
            ['key' => 'name', 'label' => 'Name'],
            ['key' => 'email', 'label' => 'Email'],
            ['key' => 'role', 'label' => 'Role'],
        ]);

        $this->assertEquals(4, $component->getColumnCount()); // 3 + actions
    }
}
```

### Anonymous Component Tests

Anonymous components are tested via feature tests that render views:

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComponentRenderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_button_component_renders(): void
    {
        $response = $this->view('components.button', [
            'variant' => 'primary',
        ])->withSlot('Click Me');

        $response->assertSee('Click Me');
        $response->assertSee('bg-[#0a0a0a]');
    }

    public function test_badge_component_renders_with_variants(): void
    {
        $success = $this->view('components.badge', ['variant' => 'success'])
            ->withSlot('Active');
        
        $success->assertSee('bg-green-100');
        $success->assertSee('Active');
    }

    public function test_card_component_renders_with_title(): void
    {
        $response = $this->view('components.card', [
            'title' => 'Test Card',
            'description' => 'Card description',
        ])->withSlot('Card content');

        $response->assertSee('Test Card');
        $response->assertSee('Card description');
        $response->assertSee('Card content');
    }
}
```

## Visual Regression Tests (Optional)

Using [Dusk](https://laravel.com/docs/dusk) for visual testing:

```php
<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Laravel\Dusk\TestCase;

class ComponentVisualTest extends TestCase
{
    public function test_dark_mode_toggle(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/dashboard')
                    ->waitFor('[data-toggle="dark-mode"]')
                    ->click('[data-toggle="dark-mode"]')
                    ->waitFor('.dark')
                    ->assertDarkMode()
                    ->click('[data-toggle="dark-mode"]')
                    ->waitFor('.dark', false)
                    ->assertLightMode();
        });
    }

    public function test_alert_component_dismissable(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/test-alerts')
                    ->waitFor('.alert')
                    ->click('.alert button[\\@click="shown = false"]')
                    ->waitFor('.alert', false)
                    ->assertDontSee('Alert message');
        });
    }
}
```

## Running Tests

```bash
# Run all component tests
php artisan test --filter="Component"

# Run specific test class
php artisan test tests/Unit/View/Components/AlertTest.php

# Run with coverage
php artisan test --coverage --filter="Component"

# Run Dusk tests (visual)
php artisan dusk --filter="Component"
```

## Test Coverage Goals

| Component Type | Target Coverage | Current Status |
|---------------|-----------------|----------------|
| Class-based (Alert, DataTable, Navigation) | 80%+ | 🔄 In Progress |
| Anonymous (button, badge, card, etc.) | 60%+ | ⏳ Pending |
| Integration (full views) | 40%+ | ⏳ Pending |

## Best Practices

1. **Test Logic, Not Markup**: Test component behavior, not HTML structure
2. **Use Factories**: Create test data with factories
3. **Test Edge Cases**: Empty states, error states, boundary conditions
4. **Integration Tests**: Test components in real view contexts
5. **Visual Tests**: Use Dusk sparingly for critical UI flows

## Continuous Integration

Add to CI pipeline:

```yaml
# .github/workflows/tests.yml
- name: Run Component Tests
  run: php artisan test --filter="Component" --coverage
  
- name: Check Test Coverage
  run: |
    php artisan test --coverage --min=70
```

## Resources

- [Laravel Component Testing](https://laravel.com/docs/testing#testing-components)
- [Laravel Dusk](https://laravel.com/docs/dusk)
- [PHPUnit Documentation](https://phpunit.de/documentation.html)