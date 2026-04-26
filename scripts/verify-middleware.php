<?php

// scripts/verify-middleware.php
$middlewareFiles = glob('app/Http/Middleware/*.php');
$registeredMiddleware = [];

// Get registered middleware from Kernel
$kernelContent = file_get_contents('app/Http/Kernel.php');
preg_match_all('/\'(\w+)\'\s*=>\s*([A-Za-z0-9\\\\]+)::class/', $kernelContent, $matches);
foreach ($matches[1] as $index => $name) {
    $registeredMiddleware[$name] = $matches[2][$index];
}

echo "Registered Middleware:\n";
foreach ($registeredMiddleware as $name => $class) {
    echo "  $name => $class\n";
}

echo "\nMiddleware Files:\n";
foreach ($middlewareFiles as $file) {
    $className = basename($file, '.php');
    echo "  $className\n";
}
