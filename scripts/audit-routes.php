<?php

use Illuminate\Contracts\Console\Kernel;

/**
 * Route Audit Script
 *
 * This script audits all routes in the application and generates a comprehensive
 * mapping document showing current patterns, middleware usage, and naming conventions.
 *
 * Usage: php scripts/audit-routes.php > docs/superpowers/plans/route-audit.json
 */

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$routes = collect(Route::getRoutes()->getRoutes())
    ->map(function ($route) {
        $uri = $route->uri;
        $name = $route->getName();
        $methods = $route->methods;
        $middleware = $route->middleware();
        $action = $route->getActionName();

        // Parse controller and action
        $controller = null;
        $controllerAction = null;
        $isLivewire = false;

        if (str_contains($action, 'App\\Livewire')) {
            $isLivewire = true;
            $controller = $action;
        } elseif (str_contains($action, '@')) {
            [$controller, $controllerAction] = explode('@', $action);
        }

        // Analyze naming pattern
        $namingPattern = 'unknown';
        if ($name) {
            if (str_contains($name, '.')) {
                $namingPattern = 'dot-notation';
            } elseif (str_contains($name, '_')) {
                $namingPattern = 'snake-case';
            } elseif (preg_match('/[A-Z]/', $name)) {
                $namingPattern = 'camel-case';
            } else {
                $namingPattern = 'kebab-case';
            }
        }

        // Analyze prefix pattern
        $prefixPattern = 'unknown';
        $segments = explode('/', $uri);
        if (count($segments) > 1) {
            $firstSegment = $segments[1];
            if (str_contains($firstSegment, '-')) {
                $prefixPattern = 'kebab-case';
            } elseif (str_contains($firstSegment, '_')) {
                $prefixPattern = 'snake-case';
            } else {
                $prefixPattern = 'single-word';
            }
        }

        // Categorize route
        $category = 'other';
        if (str_starts_with($uri, 'setup')) {
            $category = 'setup';
        } elseif (str_starts_with($uri, 'dashboard') || str_starts_with($uri, 'performance')) {
            $category = 'operations';
        } elseif (str_starts_with($uri, 'transactions')) {
            $category = 'transactions';
        } elseif (str_starts_with($uri, 'customers')) {
            $category = 'customers';
        } elseif (str_starts_with($uri, 'counters')) {
            $category = 'counters';
        } elseif (str_starts_with($uri, 'stock')) {
            $category = 'stock';
        } elseif (str_starts_with($uri, 'compliance') || str_starts_with($uri, 'str')) {
            $category = 'compliance';
        } elseif (str_starts_with($uri, 'accounting')) {
            $category = 'accounting';
        } elseif (str_starts_with($uri, 'reports')) {
            $category = 'reports';
        } elseif (str_starts_with($uri, 'audit')) {
            $category = 'audit';
        } elseif (str_starts_with($uri, 'users')) {
            $category = 'users';
        } elseif (str_starts_with($uri, 'branches')) {
            $category = 'branches';
        } elseif (str_starts_with($uri, 'mfa')) {
            $category = 'mfa';
        } elseif (str_starts_with($uri, 'rates')) {
            $category = 'rates';
        } elseif (str_starts_with($uri, 'api')) {
            $category = 'api';
        } elseif (str_starts_with($uri, 'test-results')) {
            $category = 'test';
        }

        return [
            'uri' => $uri,
            'name' => $name,
            'methods' => $methods,
            'middleware' => $middleware,
            'controller' => $controller,
            'action' => $controllerAction,
            'is_livewire' => $isLivewire,
            'naming_pattern' => $namingPattern,
            'prefix_pattern' => $prefixPattern,
            'category' => $category,
        ];
    })
    ->values()
    ->sortBy('uri')
    ->values();

// Group routes by category
$routesByCategory = $routes->groupBy('category')->map(function ($categoryRoutes) {
    return $categoryRoutes->values();
});

// Analyze patterns
$middlewarePatterns = $routes->flatMap(function ($route) {
    return collect($route['middleware'])->map(function ($mw) {
        return $mw;
    });
})->countBy()->sortDesc();

$namingPatterns = $routes->countBy('naming_pattern')->sortDesc();
$prefixPatterns = $routes->countBy('prefix_pattern')->sortDesc();
$categories = $routes->countBy('category')->sortDesc();

// Identify inconsistencies
$inconsistencies = [];

// Check for routes without names
$unnamedRoutes = $routes->filter(fn ($r) => empty($r['name']));
if ($unnamedRoutes->count() > 0) {
    $inconsistencies[] = [
        'type' => 'unnamed_routes',
        'count' => $unnamedRoutes->count(),
        'routes' => $unnamedRoutes->pluck('uri')->toArray(),
    ];
}

// Check for mixed naming patterns within categories
foreach ($routesByCategory as $category => $categoryRoutes) {
    $patterns = $categoryRoutes->pluck('naming_pattern')->unique();
    if ($patterns->count() > 1) {
        $inconsistencies[] = [
            'type' => 'mixed_naming_patterns',
            'category' => $category,
            'patterns' => $patterns->toArray(),
        ];
    }
}

// Check for mixed prefix patterns within categories
foreach ($routesByCategory as $category => $categoryRoutes) {
    $patterns = $categoryRoutes->pluck('prefix_pattern')->unique();
    if ($patterns->count() > 1) {
        $inconsistencies[] = [
            'type' => 'mixed_prefix_patterns',
            'category' => $category,
            'patterns' => $patterns->toArray(),
        ];
    }
}

// Check for inline middleware
$inlineMiddlewareRoutes = $routes->filter(fn ($r) => ! empty($r['middleware']) && count($r['middleware']) > 0);
if ($inlineMiddlewareRoutes->count() > 0) {
    $inconsistencies[] = [
        'type' => 'inline_middleware',
        'count' => $inlineMiddlewareRoutes->count(),
        'sample' => $inlineMiddlewareRoutes->take(5)->pluck('uri')->toArray(),
    ];
}

// Build output
$output = [
    'summary' => [
        'total_routes' => $routes->count(),
        'livewire_routes' => $routes->where('is_livewire', true)->count(),
        'controller_routes' => $routes->where('is_livewire', false)->count(),
        'categories' => $categories->toArray(),
        'naming_patterns' => $namingPatterns->toArray(),
        'prefix_patterns' => $prefixPatterns->toArray(),
        'middleware_patterns' => $middlewarePatterns->toArray(),
    ],
    'inconsistencies' => $inconsistencies,
    'routes_by_category' => $routesByCategory->toArray(),
    'all_routes' => $routes->toArray(),
];

echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
