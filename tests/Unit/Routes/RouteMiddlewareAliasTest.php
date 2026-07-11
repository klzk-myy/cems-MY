<?php

namespace Tests\Unit\Routes;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RouteMiddlewareAliasTest extends TestCase
{
    /**
     * Vendor packages whose route middleware we do not control.
     *
     * @var array<int, string>
     */
    protected array $vendorPrefixes = [
        'ignition.',
        'sanctum.',
        'livewire.',
        'filament.',
        'horizon.',
        'telescope.',
    ];

    #[Test]
    public function application_routes_use_named_middleware_aliases(): void
    {
        $violations = [];

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName() ?? $route->uri();

            if ($this->isVendorRoute($name)) {
                continue;
            }

            foreach ($route->middleware() as $middleware) {
                // Skip aliases and built-in middleware short names.
                if (! str_contains($middleware, '\\')) {
                    continue;
                }

                $violations[] = "{$name}: {$middleware}";
            }
        }

        $this->assertEmpty(
            $violations,
            'Application routes must use named middleware aliases instead of inline class strings. Violations: '.implode(', ', $violations)
        );
    }

    private function isVendorRoute(string $name): bool
    {
        foreach ($this->vendorPrefixes as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
