<?php

/**
 * Middleware Verification Script
 *
 * This script verifies middleware registration by comparing middleware files
 * to registered middleware in Kernel.php.
 *
 * Usage: php scripts/verify-middleware.php
 *
 * Exit codes:
 *   0 - All middleware are registered and used
 *   1 - Unused middleware found
 *   2 - Error occurred during execution
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

$middlewareFiles = glob('app/Http/Middleware/*.php');
$registeredMiddleware = [
    'global' => [],
    'groups' => [],
    'aliases' => [],
];

// Get registered middleware from Kernel
$kernelContent = file_get_contents('app/Http/Kernel.php');

// Remove comments to avoid matching commented code
$kernelContent = preg_replace('/\/\/.*$/m', '', $kernelContent);
$kernelContent = preg_replace('/\/\*.*?\*\//s', '', $kernelContent);

// Parse global middleware
preg_match('/protected \$middleware\s*=\s*\[(.*?)\];/s', $kernelContent, $globalMatches);
if (! empty($globalMatches[1])) {
    preg_match_all('/([A-Za-z0-9\\\\]+)::class/', $globalMatches[1], $globalClasses);
    foreach ($globalClasses[1] as $class) {
        $registeredMiddleware['global'][$class] = $class;
    }
}

// Parse middleware groups
preg_match('/protected \$middlewareGroups\s*=\s*\[(.*?)\];/s', $kernelContent, $groupMatches);
if (! empty($groupMatches[1])) {
    preg_match_all('/\'(\w+)\'\s*=>\s*\[(.*?)\]/s', $groupMatches[1], $groupDefs);
    foreach ($groupDefs[1] as $index => $groupName) {
        preg_match_all('/([A-Za-z0-9\\\\]+)::class/', $groupDefs[2][$index], $groupClasses);
        foreach ($groupClasses[1] as $class) {
            $registeredMiddleware['groups'][$groupName][$class] = $class;
        }
    }
}

// Parse middleware aliases
preg_match('/protected \$middlewareAliases\s*=\s*\[(.*?)\];/s', $kernelContent, $aliasMatches);
if (! empty($aliasMatches[1])) {
    preg_match_all('/\'([\w\.]+)\'\s*=>\s*([A-Za-z0-9\\\\]+)::class/', $aliasMatches[1], $aliasDefs);
    foreach ($aliasDefs[1] as $index => $aliasName) {
        $registeredMiddleware['aliases'][$aliasName] = $aliasDefs[2][$index];
    }
}

echo "=== Middleware Verification ===\n\n";

echo 'Global Middleware ('.count($registeredMiddleware['global'])."):\n";
foreach ($registeredMiddleware['global'] as $class) {
    echo "  - $class\n";
}

echo "\nMiddleware Groups:\n";
foreach ($registeredMiddleware['groups'] as $groupName => $classes) {
    echo "  $groupName (".count($classes)."):\n";
    foreach ($classes as $class) {
        echo "    - $class\n";
    }
}

echo "\nMiddleware Aliases (".count($registeredMiddleware['aliases'])."):\n";
foreach ($registeredMiddleware['aliases'] as $alias => $class) {
    echo "  $alias => $class\n";
}

echo "\nMiddleware Files (".count($middlewareFiles)."):\n";
foreach ($middlewareFiles as $file) {
    $className = basename($file, '.php');
    echo "  - $className\n";
}

// Check for unused middleware
$allRegistered = [];
foreach ($registeredMiddleware['global'] as $class) {
    $allRegistered[] = $class;
}
foreach ($registeredMiddleware['groups'] as $groupName => $classes) {
    foreach ($classes as $class) {
        $allRegistered[] = $class;
    }
}
foreach ($registeredMiddleware['aliases'] as $alias => $class) {
    $allRegistered[] = $class;
}

$unusedMiddleware = [];
foreach ($middlewareFiles as $file) {
    $className = basename($file, '.php');
    $fullClassName = 'App\\Http\\Middleware\\'.$className;

    $isUsed = false;
    foreach ($allRegistered as $registeredClass) {
        // Match by full class name or short class name
        if ($registeredClass === $fullClassName ||
            $registeredClass === $className ||
            $registeredClass === '\\'.$fullClassName ||
            str_ends_with($registeredClass, '\\'.$className)) {
            $isUsed = true;
            break;
        }
    }

    if (! $isUsed) {
        $unusedMiddleware[] = $className;
    }
}

echo "\n=== Results ===\n";
if (empty($unusedMiddleware)) {
    echo "✅ All middleware files are registered and used.\n";
    echo "No unused middleware found.\n";
} else {
    echo '❌ Found '.count($unusedMiddleware)." unused middleware:\n\n";
    foreach ($unusedMiddleware as $middleware) {
        echo "  - $middleware\n";
    }
    echo "\nThese middleware files exist but are not registered in Kernel.php.\n";
    echo "Please register them or remove the files.\n";
}

echo "\n=== Summary ===\n";
echo 'Total middleware files: '.count($middlewareFiles)."\n";
echo 'Total registered middleware: '.count($allRegistered)."\n";
echo 'Unused middleware: '.count($unusedMiddleware)."\n";

exit(empty($unusedMiddleware) ? 0 : 1);
