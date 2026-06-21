<?php

use App\Models\BaseModel;
use App\Models\Bases\AccountingModel;
use App\Models\Bases\ComplianceModel;
use App\Models\Bases\SystemModel;
use App\Models\Bases\TransactionModel;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$basePath = app_path('Models');
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($basePath));

$baseClasses = [
    BaseModel::class,
    AccountingModel::class,
    ComplianceModel::class,
    TransactionModel::class,
    SystemModel::class,
];

$issues = [];

foreach ($rii as $file) {
    if (! $file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }

    $relative = str_replace([$basePath.'/', $basePath], '', $file->getPathname());
    $class = 'App\\Models\\'.str_replace(['/', '.php'], ['\\', ''], $relative);

    if (! class_exists($class)) {
        continue;
    }

    $reflection = new ReflectionClass($class);
    if ($reflection->isAbstract() || $reflection->isTrait() || in_array($class, $baseClasses)) {
        continue;
    }

    if (! $reflection->isSubclassOf(Model::class)) {
        continue;
    }

    $model = $reflection->newInstanceWithoutConstructor();
    $table = $model->getTable();

    if (! Schema::hasTable($table)) {
        $issues[] = [
            'model' => $class,
            'table' => $table,
            'issue' => 'missing_table',
            'field' => '-',
            'detail' => "Table {$table} does not exist",
        ];

        continue;
    }

    $columns = collect(Schema::getColumns($table))
        ->mapWithKeys(fn ($c) => [strtolower($c['name']) => $c])
        ->toArray();

    $fillable = $reflection->getProperty('fillable');
    $fillable->setAccessible(true);
    $fillableFields = $fillable->getValue($model);

    foreach ($fillableFields as $field) {
        if (! isset($columns[strtolower($field)])) {
            $issues[] = [
                'model' => $class,
                'table' => $table,
                'issue' => 'fillable_without_column',
                'field' => $field,
                'detail' => "Fillable field '{$field}' has no matching column in {$table}",
            ];
        }
    }

    $casts = $reflection->getProperty('casts');
    $casts->setAccessible(true);
    $castFields = $casts->getValue($model);

    foreach ($castFields as $field => $cast) {
        if (! isset($columns[strtolower($field)])) {
            $issues[] = [
                'model' => $class,
                'table' => $table,
                'issue' => 'dead_cast',
                'field' => $field,
                'detail' => "Cast '{$field}' => '{$cast}' has no matching column in {$table}",
            ];
        }
    }

    foreach ($columns as $columnName => $column) {
        if ($columnName === 'id' || in_array($columnName, ['created_at', 'updated_at', 'deleted_at'])) {
            continue;
        }

        $inFillable = in_array($columnName, $fillableFields);
        $inCasts = isset($castFields[$columnName]);

        if (! $inFillable && ! $inCasts) {
            $issues[] = [
                'model' => $class,
                'table' => $table,
                'issue' => 'column_not_fillable_or_cast',
                'field' => $columnName,
                'detail' => "Column '{$columnName}' in {$table} is not in \$fillable or \$casts",
            ];
        }
    }
}

foreach ($issues as $issue) {
    echo implode('|', [
        $issue['model'],
        $issue['table'],
        $issue['issue'],
        $issue['field'],
        $issue['detail'],
    ]).PHP_EOL;
}

echo PHP_EOL.'Total issues: '.count($issues).PHP_EOL;
