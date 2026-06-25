<?php

$patterns = ['TODO', 'FIXME', 'XXX', 'HACK'];
$files = [];

foreach ($patterns as $pattern) {
    $output = shell_exec("grep -rn '$pattern' app/ --include='*.php'");
    if ($output) {
        $files[$pattern] = explode("\n", trim($output));
    }
}

echo "TODO Comments Found:\n";
foreach ($files as $pattern => $matches) {
    echo "\n$pattern:\n";
    foreach ($matches as $match) {
        echo "  $match\n";
    }
}
