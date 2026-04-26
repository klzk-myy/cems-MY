<?php

/**
 * Route Verification Script
 *
 * This script checks all route references in Blade views against defined routes
 * to identify any missing or invalid route references.
 *
 * Usage: php scripts/check-routes.php [options]
 *
 * Options:
 *   --format=json    Output results in JSON format
 *   --path=PATH      Custom path to views directory (default: resources/views)
 *
 * Examples:
 *   php scripts/check-routes.php
 *   php scripts/check-routes.php --format=json
 *   php scripts/check-routes.php --path=custom/views
 *
 * Exit codes:
 *   0 - All route references are valid
 *   1 - Missing routes found
 *   2 - Error occurred during execution
 *
 * Troubleshooting:
 *   - If you get "Directory not found" errors, ensure the views directory exists
 *   - If you get permission errors, check file system permissions
 *   - If routes are not loading, ensure Laravel is properly configured
 */

// Detect vendor/autoload.php dynamically
$vendorAutoload = __DIR__.'/../vendor/autoload.php';
if (! file_exists($vendorAutoload)) {
    $vendorAutoload = dirname(__DIR__, 2).'/vendor/autoload.php';
}

if (! file_exists($vendorAutoload)) {
    fwrite(STDERR, "Error: vendor/autoload.php not found. Please run 'composer install'.\n");
    exit(2);
}

require $vendorAutoload;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

// Parse command line options
$options = [
    'format' => 'text',
    'path' => null,
];

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--format=')) {
        $options['format'] = substr($arg, 9);
    } elseif (str_starts_with($arg, '--path=')) {
        $options['path'] = substr($arg, 6);
    }
}

try {
    $app = require_once __DIR__.'/../bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
} catch (Throwable $e) {
    fwrite(STDERR, "Error: Failed to bootstrap Laravel application: {$e->getMessage()}\n");
    exit(2);
}

$results = [
    'status' => 'success',
    'route_references' => [],
    'defined_routes' => [],
    'missing_routes' => [],
    'summary' => [
        'total_references' => 0,
        'total_defined' => 0,
        'total_missing' => 0,
    ],
];

try {
    // Step 1: Extract all route references from views
    if ($options['format'] === 'text') {
        echo "=== Route Verification Script ===\n\n";
        echo "Step 1: Extracting route references from Blade views...\n";
    }

    $viewsPath = $options['path'] ?? resource_path('views');

    if (! is_dir($viewsPath)) {
        throw new Exception("Views directory not found: {$viewsPath}");
    }

    $routeReferences = [];

    try {
        $files = File::allFiles($viewsPath);
    } catch (Throwable $e) {
        throw new Exception("Failed to read views directory: {$e->getMessage()}");
    }

    foreach ($files as $file) {
        if ($file->getExtension() === 'php') {
            try {
                $content = File::get($file->getPathname());

                // Match both single and double quoted route names
                // Pattern matches: route('name'), route("name"), route('name', [...]), route("name", [...])
                preg_match_all("/route\(['\"]([^'\"]+)['\"].*?\)/", $content, $matches);

                if (! empty($matches[1])) {
                    foreach ($matches[1] as $routeName) {
                        $routeReferences[$routeName] = $routeName;
                    }
                }
            } catch (Throwable $e) {
                if ($options['format'] === 'text') {
                    fwrite(STDERR, "Warning: Failed to read file {$file->getPathname()}: {$e->getMessage()}\n");
                }
            }
        }
    }

    $results['route_references'] = array_values($routeReferences);
    $results['summary']['total_references'] = count($routeReferences);

    if ($options['format'] === 'text') {
        echo 'Found '.count($routeReferences)." unique route references in views.\n\n";
    }

    // Step 2: Get all defined routes
    if ($options['format'] === 'text') {
        echo "Step 2: Getting all defined routes...\n";
    }

    $definedRoutes = [];

    try {
        $routes = Route::getRoutes();
        foreach ($routes as $route) {
            if ($route->getName()) {
                $definedRoutes[$route->getName()] = $route->getName();
            }
        }
    } catch (Throwable $e) {
        throw new Exception("Failed to get defined routes: {$e->getMessage()}");
    }

    $results['defined_routes'] = array_values($definedRoutes);
    $results['summary']['total_defined'] = count($definedRoutes);

    if ($options['format'] === 'text') {
        echo 'Found '.count($definedRoutes)." defined routes.\n\n";
    }

    // Step 3: Compare references to defined routes
    if ($options['format'] === 'text') {
        echo "Step 3: Comparing references to defined routes...\n";
    }

    $missingRoutes = [];
    foreach ($routeReferences as $routeName) {
        if (! isset($definedRoutes[$routeName])) {
            $missingRoutes[$routeName] = $routeName;
        }
    }

    $results['missing_routes'] = array_values($missingRoutes);
    $results['summary']['total_missing'] = count($missingRoutes);

    // Step 4: Report results
    if ($options['format'] === 'text') {
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
    } else {
        echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
    }

    // Exit with appropriate code
    exit(empty($missingRoutes) ? 0 : 1);
} catch (Throwable $e) {
    $results['status'] = 'error';
    $results['error'] = $e->getMessage();

    if ($options['format'] === 'text') {
        fwrite(STDERR, "Error: {$e->getMessage()}\n");
    } else {
        echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
    }

    exit(2);
}
