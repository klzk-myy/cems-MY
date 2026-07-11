<?php

namespace Tests\Unit\Routes;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RouteNamingConsistencyTest extends TestCase
{
    /**
     * Routes that are allowed to remain unnamed.
     *
     * @var array<int, string>
     */
    protected array $allowedUnnamedUris = [
        'up', // Laravel health check route
        'broadcasting/auth', // Laravel Echo broadcasting auth endpoint
    ];

    #[Test]
    public function critical_routes_have_names(): void
    {
        $routes = Route::getRoutes();

        $requiredNamedUris = [
            'login',
            'test/query-log',
        ];

        foreach ($requiredNamedUris as $uri) {
            $matchingRoutes = collect($routes)->filter(fn ($route) => $route->uri() === $uri);

            $this->assertFalse(
                $matchingRoutes->isEmpty(),
                "Route for URI [{$uri}] not found."
            );

            foreach ($matchingRoutes as $route) {
                $this->assertNotEmpty(
                    $route->getName(),
                    "Route [{$uri}] (".implode('|', $route->methods()).') must have a name.'
                );
            }
        }
    }

    #[Test]
    public function only_allowed_routes_are_unnamed(): void
    {
        $unnamed = collect(Route::getRoutes())
            ->filter(fn ($route) => empty($route->getName()))
            ->map(fn ($route) => $route->uri())
            ->unique()
            ->values();

        $unexpected = $unnamed->reject(fn ($uri) => in_array($uri, $this->allowedUnnamedUris, true));

        $this->assertEmpty(
            $unexpected->toArray(),
            'Unexpected unnamed routes found: '.implode(', ', $unexpected->toArray())
        );
    }
}
