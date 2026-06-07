<?php

$lines = file(__DIR__.'/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$env = [];
foreach ($lines as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#')) {
        continue;
    }
    $parts = explode('=', $line, 2);
    if (count($parts) === 2) {
        $env[trim($parts[0])] = trim($parts[1]);
    }
}
$dbName = $env['DB_DATABASE'] ?? 'cems_my';
$host = $env['DB_HOST'] ?? '127.0.0.1';
$user = $env['DB_USERNAME'] ?? 'root';
$pass = $env['DB_PASSWORD'] ?? '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=information_schema;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $pdo->prepare('SELECT TABLE_NAME, COLUMN_NAME FROM COLUMNS WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME, ORDINAL_POSITION');
    $stmt->execute([$dbName]);
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    fwrite(STDERR, 'DB ERROR: '.$e->getMessage()."\n");
    exit(1);
}

$schema = [];
foreach ($columns as $col) {
    $schema[$col['TABLE_NAME']][] = $col['COLUMN_NAME'];
}

$commonColumns = ['id', 'name', 'created_at', 'updated_at', 'deleted_at', 'code', 'description', 'status', 'type', 'active', 'notes', 'reference', 'date', 'amount', 'branch_id', 'user_id', 'created_by', 'updated_by', 'is_active'];

$searchDirs = [__DIR__.'/../app', __DIR__.'/../config', __DIR__.'/../routes', __DIR__.'/../resources/views', __DIR__.'/../database'];

$orphanedTables = [];
$orphanedColumns = [];

foreach ($schema as $table => $cols) {
    if (str_contains($table, 'migrations') || $table === 'cache' || $table === 'sessions' || $table === 'failed_jobs') {
        continue;
    }

    $tableRefs = 0;
    foreach ($searchDirs as $dir) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if (! in_array($file->getExtension(), ['php', 'blade.php'])) {
                continue;
            }
            $content = file_get_contents($file->getPathname());
            if (preg_match('/'.preg_quote($table, '/').'/', $content)) {
                $tableRefs++;
                break 2;
            }
            $studly = implode('', array_map('ucfirst', explode('_', $table)));
            if (substr($studly, -1) === 's') {
                $studly = substr($studly, 0, -1);
            }
            if (preg_match('/\b'.preg_quote($studly, '/').'\b/', $content)) {
                $tableRefs++;
                break 2;
            }
        }
    }

    if ($tableRefs === 0) {
        $orphanedTables[] = $table;

        continue;
    }

    foreach ($cols as $col) {
        if (in_array($col, $commonColumns)) {
            continue;
        }
        $colRefs = 0;
        foreach ($searchDirs as $dir) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS));
            foreach ($files as $file) {
                if (! in_array($file->getExtension(), ['php', 'blade.php'])) {
                    continue;
                }
                $content = file_get_contents($file->getPathname());
                if (preg_match('/\b'.preg_quote($col, '/').'\b/', $content)) {
                    $colRefs++;
                    break 2;
                }
            }
        }
        if ($colRefs === 0) {
            $orphanedColumns[] = ['table' => $table, 'column' => $col];
        }
    }
}

$result = ['orphaned_tables' => $orphanedTables, 'orphaned_columns' => $orphanedColumns];
echo json_encode($result, JSON_PRETTY_PRINT)."\n";
fwrite(STDERR, 'Orphaned tables: '.count($orphanedTables)."\n");
fwrite(STDERR, 'Orphaned columns: '.count($orphanedColumns)."\n");
