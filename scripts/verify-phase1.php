<?php

/**
 * Phase 1 Verification Script
 *
 * This script verifies that all Phase 1 requirements are met:
 * - No dd() debug calls in production code
 * - All route references in views are valid
 * - No critical TODO comments remain
 * - Middleware is optimized (no unused middleware)
 *
 * Usage: php scripts/verify-phase1.php
 *
 * Exit codes:
 *   0 - All checks pass
 *   1 - One or more checks failed
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

echo "=== Phase 1 Verification ===\n\n";

$allPassed = true;

// Check 1: No dd() calls in production code
echo "Check 1: No dd() debug calls in production code...\n";
$ddCalls = shell_exec("grep -r 'dd(' app/ --include='*.php' 2>/dev/null | grep -v 'add\|edd\|odd\|pdd\|rdd' | grep -E 'dd\([^)]*\);' | wc -l");
$ddCalls = trim($ddCalls);
if ($ddCalls == 0) {
    echo "✅ PASS: No dd() calls found\n\n";
} else {
    echo "❌ FAIL: Found {$ddCalls} dd() call(s)\n\n";
    $allPassed = false;
}

// Check 2: All route references are valid
echo "Check 2: All route references in views are valid...\n";
$checkRoutesOutput = shell_exec('php scripts/check-routes.php 2>&1');
if (str_contains($checkRoutesOutput, 'All route references in views are valid!')) {
    echo "✅ PASS: All route references are valid\n\n";
} else {
    echo "❌ FAIL: Some route references are invalid\n\n";
    $allPassed = false;
}

// Check 3: No critical TODOs remain
echo "Check 3: No critical TODO comments remain...\n";
$findTodosOutput = shell_exec('php scripts/find-todos.php 2>&1');
$criticalTodos = shell_exec("php scripts/find-todos.php 2>&1 | grep -i 'critical\|urgent\|security' | wc -l");
$criticalTodos = trim($criticalTodos);
if ($criticalTodos == 0) {
    echo "✅ PASS: No critical TODOs found\n\n";
} else {
    echo "❌ FAIL: Found {$criticalTodos} critical TODO(s)\n\n";
    $allPassed = false;
}

// Check 4: Middleware is optimized
echo "Check 4: Middleware is optimized (no unused middleware)...\n";
$verifyMiddlewareOutput = shell_exec('php scripts/verify-middleware.php 2>&1');
if (str_contains($verifyMiddlewareOutput, 'All middleware files are registered and used.')) {
    echo "✅ PASS: All middleware are registered and used\n\n";
} else {
    echo "❌ FAIL: Some middleware are unused\n\n";
    $allPassed = false;
}

// Check 5: Run full test suite
echo "Check 5: Full test suite passes...\n";
$testOutput = shell_exec('php artisan test 2>&1');
if (str_contains($testOutput, 'PASS') && ! str_contains($testOutput, 'FAIL')) {
    echo "✅ PASS: All tests pass\n\n";
} else {
    echo "❌ FAIL: Some tests failed\n\n";
    $allPassed = false;
}

echo "=== Summary ===\n";
if ($allPassed) {
    echo "✅ All Phase 1 checks passed!\n";
    echo "Phase 1 is complete and ready for deployment.\n";
    exit(0);
} else {
    echo "❌ Some Phase 1 checks failed.\n";
    echo "Please review the failed checks above.\n";
    exit(1);
}
