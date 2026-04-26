<?php

/**
 * Route Verification Script
 *
 * This script checks all route references in Blade views against defined routes
 * to identify any missing or invalid route references.
 *
 * Usage: php scripts/check-routes.php
 */

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

echo "=== Route Verification Script ===\n\n";

// Step 1: Extract all route references from views
echo "Step 1: Extracting route references from Blade views...\n";
$viewsPath = resource_path('views');
$routeReferences = [];

$files = File::allFiles($viewsPath);
foreach ($files as $file) {
    if ($file->getExtension() === 'php') {
        $content = File::get($file->getPathname());
        preg_match_all("/route\('([^']+)'\)/", $content, $matches);
        if (! empty($matches[1])) {
            foreach ($matches[1] as $routeName) {
                $routeReferences[$routeName] = $routeName;
            }
        }
    }
}

echo 'Found '.count($routeReferences)." unique route references in views.\n\n";

// Step 2: Get all defined routes
echo "Step 2: Getting all defined routes...\n";
$definedRoutes = [];
$routes = Route::getRoutes();
foreach ($routes as $route) {
    if ($route->getName()) {
        $definedRoutes[$route->getName()] = $route->getName();
    }
}

echo 'Found '.count($definedRoutes)." defined routes.\n\n";

// Step 3: Compare references to defined routes
echo "Step 3: Comparing references to defined routes...\n";
$missingRoutes = [];
foreach ($routeReferences as $routeName) {
    if (! isset($definedRoutes[$routeName])) {
        $missingRoutes[$routeName] = $routeName;
    }
}

// Step 4: Report results
echo "\n=== Results ===\n\n";

if (empty($missingRoutes)) {
    echo "✅ All route references in views are valid!\n";
    echo "No missing routes found.\n";
} else {
    echo '❌ Found '.count($missingRoutes)." missing route(s):\n\n";
    foreach ($missingRoutes as $routeName) {
        echo "  - $routeName\n";
    }
    echo "\nThese routes are referenced in views but not defined in routes.\n";
    echo "Please define them or update the view references.\n";
}

echo "\n=== Summary ===\n";
echo 'Total route references in views: '.count($routeReferences)."\n";
echo 'Total defined routes: '.count($definedRoutes)."\n";
echo 'Missing routes: '.count($missingRoutes)."\n";

// Exit with appropriate code
exit(empty($missingRoutes) ? 0 : 1);
