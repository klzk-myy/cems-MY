<?php

$viewsDir = __DIR__.'/../resources/views';
$allViews = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($viewsDir, RecursiveDirectoryIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
    if ($file->getExtension() === 'php' && str_contains($file->getFilename(), '.blade.')) {
        $relativePath = str_replace($viewsDir.'/', '', $file->getPathname());
        $dotName = str_replace('/', '.', str_replace('.blade.php', '', $relativePath));
        $allViews[$dotName] = $relativePath;
    }
}
$appDirs = [__DIR__.'/../app', __DIR__.'/../resources/views', __DIR__.'/../routes', __DIR__.'/../config', __DIR__.'/../database'];
$referenced = [];
foreach ($appDirs as $dir) {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS));
    foreach ($files as $file) {
        if (! in_array($file->getExtension(), ['php', 'blade.php'])) {
            continue;
        }
        $content = file_get_contents($file->getPathname());
        preg_match_all('/view\s*\(\s*[\'"]([^\'"]+)[\'"]/', $content, $matches);
        foreach ($matches[1] as $v) {
            $referenced[] = $v;
        }
        preg_match_all('/@(?:include|extends|includeIf|each|component)\s*\(\s*[\'"]([^\'"]+)[\'"]/', $content, $matches);
        foreach ($matches[1] as $v) {
            $referenced[] = $v;
        }
        preg_match_all('/Mail::send\s*\(\s*[\'"]([^\'"]+)[\'"]/', $content, $matches);
        foreach ($matches[1] as $v) {
            $referenced[] = $v;
        }
        preg_match_all('/View::make\s*\(\s*[\'"]([^\'"]+)[\'"]/', $content, $matches);
        foreach ($matches[1] as $v) {
            $referenced[] = $v;
        }
    }
}
$referenced = array_unique($referenced);
$cleanReferenced = [];
foreach ($referenced as $ref) {
    if (str_contains($ref, '::')) {
        $cleanReferenced[] = explode('::', $ref, 2)[1];
    } else {
        $cleanReferenced[] = $ref;
    }
}
$cleanReferenced = array_unique($cleanReferenced);
$orphaned = [];
foreach ($allViews as $dotName => $path) {
    if (! in_array($dotName, $cleanReferenced)) {
        $found = false;
        foreach ($cleanReferenced as $ref) {
            if (str_ends_with($ref, '.'.$dotName) || $ref === $dotName) {
                $found = true;
                break;
            }
        }
        if (! $found && ! str_contains($dotName, 'components/')) {
            $orphaned[] = ['view' => $dotName, 'path' => $path, 'confidence' => 'HIGH'];
        }
    }
}
echo json_encode(['total_views' => count($allViews), 'referenced' => count($cleanReferenced), 'orphaned' => count($orphaned)], JSON_PRETTY_PRINT)."\n";
foreach ($orphaned as $o) {
    echo json_encode($o)."\n";
}
fwrite(STDERR, 'Total views: '.count($allViews)."\n");
fwrite(STDERR, 'Referenced: '.count($cleanReferenced)."\n");
fwrite(STDERR, 'Orphaned candidates: '.count($orphaned)."\n");
