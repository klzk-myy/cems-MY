<?php

/**
 * Code Review Graph Generator
 * Analyzes Laravel codebase to build dependency graph
 */

require __DIR__.'/vendor/autoload.php';

class CodeReviewGraphGenerator
{
    private array $nodes = [];

    private array $edges = [];

    private int $nodeId = 0;

    private array $fileCache = [];

    public function generate(): void
    {
        echo "Starting code review graph generation...\n";

        // Scan all PHP files in app/
        $directories = [
            'app/Http/Controllers' => 'Controller',
            'app/Services' => 'Service',
            'app/Models' => 'Model',
            'app/Http/Middleware' => 'Middleware',
            'app/Enums' => 'Enum',
            'app/Exceptions' => 'Exception',
            'app/Jobs' => 'Job',
            'app/Observers' => 'Observer',
            'app/Events' => 'Event',
        ];

        foreach ($directories as $dir => $type) {
            $this->scanDirectory($dir, $type);
        }

        // Build dependency edges
        $this->buildDependencies();

        // Export to multiple formats
        $this->exportToD3Json();
        $this->exportToCypher();
        $this->exportToDot();
        $this->exportStatistics();

        echo "\n✓ Code review graph generated successfully!\n";
        echo '  - Nodes: '.count($this->nodes)."\n";
        echo '  - Edges: '.count($this->edges)."\n";
    }

    private function scanDirectory(string $directory, string $type): void
    {
        $basePath = __DIR__.'/'.$directory;
        if (! is_dir($basePath)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($basePath)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $this->processFile($file->getPathname(), $type);
            }
        }
    }

    private function processFile(string $filepath, string $type): void
    {
        $content = file_get_contents($filepath);
        $this->fileCache[$filepath] = $content;

        // Extract namespace and class
        $namespace = $this->extractNamespace($content);
        $className = $this->extractClassName($content);

        if (! $className) {
            return;
        }

        $fullClassName = $namespace ? $namespace.'\\'.$className : $className;
        $relativePath = str_replace(__DIR__.'/', '', $filepath);

        // Extract dependencies
        $dependencies = $this->extractDependencies($content);

        $this->nodes[$fullClassName] = [
            'id' => ++$this->nodeId,
            'name' => $className,
            'fullName' => $fullClassName,
            'type' => $type,
            'path' => $relativePath,
            'namespace' => $namespace,
            'dependencies' => $dependencies,
            'lines' => substr_count($content, "\n") + 1,
            'methods' => $this->extractMethods($content),
        ];
    }

    private function extractNamespace(string $content): ?string
    {
        if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function extractClassName(string $content): ?string
    {
        if (preg_match('/(?:class|interface|trait|enum)\s+(\w+)/', $content, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function extractDependencies(string $content): array
    {
        $dependencies = [];

        // Extract use statements
        preg_match_all('/use\s+([^;]+);/', $content, $matches);
        foreach ($matches[1] as $use) {
            $use = trim($use);
            // Skip Laravel framework classes and vendor classes
            if (str_starts_with($use, 'App\\') || str_starts_with($use, 'Illuminate\\')) {
                $dependencies[] = [
                    'type' => 'use',
                    'class' => $use,
                ];
            }
        }

        // Extract constructor injections
        preg_match_all('/function\s+__construct\s*\([^)]*\)/s', $content, $constructorMatches);
        if (! empty($constructorMatches[0])) {
            foreach ($constructorMatches[0] as $constructor) {
                preg_match_all('/(?:protected|public|private)\s+(?:\??\s*)(\w+)\s*\$\w+/', $constructor, $paramMatches);
                foreach ($paramMatches[1] as $type) {
                    if (ctype_upper($type[0])) { // Likely a class name
                        $dependencies[] = [
                            'type' => 'injection',
                            'class' => $type,
                        ];
                    }
                }
            }
        }

        // Extract extends
        if (preg_match('/extends\s+(\w+)/', $content, $matches)) {
            $dependencies[] = [
                'type' => 'extends',
                'class' => $matches[1],
            ];
        }

        // Extract implements
        if (preg_match('/implements\s+([^\{]+)/', $content, $matches)) {
            $interfaces = explode(',', $matches[1]);
            foreach ($interfaces as $interface) {
                $dependencies[] = [
                    'type' => 'implements',
                    'class' => trim($interface),
                ];
            }
        }

        return $dependencies;
    }

    private function extractMethods(string $content): array
    {
        $methods = [];
        preg_match_all('/(?:public|protected|private)\s+(?:static\s+)?function\s+(\w+)\s*\(/', $content, $matches);

        foreach ($matches[1] as $method) {
            if (! str_starts_with($method, '__')) {
                $methods[] = $method;
            }
        }

        return $methods;
    }

    private function buildDependencies(): void
    {
        foreach ($this->nodes as $sourceClass => $node) {
            foreach ($node['dependencies'] as $dep) {
                $targetClass = $this->resolveClassName($dep['class'], $node['namespace']);

                if (isset($this->nodes[$targetClass])) {
                    $this->edges[] = [
                        'source' => $sourceClass,
                        'target' => $targetClass,
                        'type' => $dep['type'],
                    ];
                }
            }
        }
    }

    private function resolveClassName(string $class, ?string $namespace): string
    {
        // If it's already a fully qualified name
        if (str_contains($class, '\\')) {
            return $class;
        }

        // Check if it's in nodes (already fully qualified)
        foreach ($this->nodes as $fullName => $node) {
            if ($node['name'] === $class) {
                return $fullName;
            }
        }

        // Try with namespace
        if ($namespace) {
            $fullName = $namespace.'\\'.$class;
            if (isset($this->nodes[$fullName])) {
                return $fullName;
            }
        }

        return $class;
    }

    private function exportToD3Json(): void
    {
        $graph = [
            'nodes' => [],
            'links' => [],
        ];

        foreach ($this->nodes as $class => $node) {
            $graph['nodes'][] = [
                'id' => $class,
                'name' => $node['name'],
                'type' => $node['type'],
                'group' => $this->getGroup($node['type']),
                'lines' => $node['lines'],
                'path' => $node['path'],
                'methodCount' => count($node['methods']),
            ];
        }

        foreach ($this->edges as $edge) {
            $graph['links'][] = [
                'source' => $edge['source'],
                'target' => $edge['target'],
                'type' => $edge['type'],
            ];
        }

        file_put_contents(
            __DIR__.'/.code-review-graph/graph.json',
            json_encode($graph, JSON_PRETTY_PRINT)
        );
    }

    private function exportToCypher(): void
    {
        $cypher = '';

        // Create nodes
        foreach ($this->nodes as $class => $node) {
            $cypher .= sprintf(
                "CREATE (:%s {name: '%s', fullName: '%s', type: '%s', lines: %d, path: '%s'})\n",
                $node['type'],
                addslashes($node['name']),
                addslashes($class),
                $node['type'],
                $node['lines'],
                addslashes($node['path'])
            );
        }

        // Create relationships
        foreach ($this->edges as $edge) {
            $cypher .= sprintf(
                "MATCH (a {fullName: '%s'}), (b {fullName: '%s'}) CREATE (a)-[:%s]->(b)\n",
                addslashes($edge['source']),
                addslashes($edge['target']),
                ucfirst($edge['type'])
            );
        }

        file_put_contents(__DIR__.'/.code-review-graph/graph.cypher', $cypher);
    }

    private function exportToDot(): void
    {
        $dot = "digraph CodeReviewGraph {\n";
        $dot .= "  rankdir=TB;\n";
        $dot .= "  node [shape=box, style=rounded];\n\n";

        // Define nodes with colors by type
        $colors = [
            'Controller' => 'lightblue',
            'Service' => 'lightgreen',
            'Model' => 'lightyellow',
            'Middleware' => 'lightcoral',
            'Enum' => 'lightpink',
            'Exception' => 'lightsalmon',
            'Job' => 'lightcyan',
            'Observer' => 'plum',
            'Event' => 'wheat',
        ];

        foreach ($this->nodes as $class => $node) {
            $color = $colors[$node['type']] ?? 'white';
            $dot .= sprintf(
                '  "%s" [fillcolor=%s, label="%s\\n(%s)"];'."\n",
                $class,
                $color,
                $node['name'],
                $node['type']
            );
        }

        $dot .= "\n";

        // Define edges
        foreach ($this->edges as $edge) {
            $dot .= sprintf(
                '  "%s" -> "%s" [label="%s"];'."\n",
                $edge['source'],
                $edge['target'],
                $edge['type']
            );
        }

        $dot .= "}\n";

        file_put_contents(__DIR__.'/.code-review-graph/graph.dot', $dot);
    }

    private function exportStatistics(): void
    {
        $stats = [
            'generated_at' => date('Y-m-d H:i:s'),
            'total_nodes' => count($this->nodes),
            'total_edges' => count($this->edges),
            'by_type' => [],
            'avg_lines_per_type' => [],
            'most_connected' => [],
            'layer_coupling' => [],
        ];

        // Count by type
        foreach ($this->nodes as $node) {
            $type = $node['type'];
            if (! isset($stats['by_type'][$type])) {
                $stats['by_type'][$type] = ['count' => 0, 'lines' => 0];
            }
            $stats['by_type'][$type]['count']++;
            $stats['by_type'][$type]['lines'] += $node['lines'];
        }

        // Average lines per type
        foreach ($stats['by_type'] as $type => $data) {
            $stats['avg_lines_per_type'][$type] = round($data['lines'] / $data['count'], 2);
        }

        // Most connected nodes
        $connections = [];
        foreach ($this->edges as $edge) {
            $connections[$edge['source']] = ($connections[$edge['source']] ?? 0) + 1;
            $connections[$edge['target']] = ($connections[$edge['target']] ?? 0) + 1;
        }
        arsort($connections);
        $stats['most_connected'] = array_slice($connections, 0, 20, true);

        // Layer coupling (Controller -> Service -> Model)
        $layerCoupling = [
            'Controller->Service' => 0,
            'Controller->Model' => 0,
            'Service->Service' => 0,
            'Service->Model' => 0,
            'Model->Model' => 0,
            'Other' => 0,
        ];

        foreach ($this->edges as $edge) {
            $sourceType = $this->nodes[$edge['source']]['type'] ?? 'Other';
            $targetType = $this->nodes[$edge['target']]['type'] ?? 'Other';
            $key = $sourceType.'->'.$targetType;

            if (isset($layerCoupling[$key])) {
                $layerCoupling[$key]++;
            } else {
                $layerCoupling['Other']++;
            }
        }

        $stats['layer_coupling'] = $layerCoupling;

        file_put_contents(
            __DIR__.'/.code-review-graph/statistics.json',
            json_encode($stats, JSON_PRETTY_PRINT)
        );
    }

    private function getGroup(string $type): int
    {
        return match ($type) {
            'Controller' => 1,
            'Service' => 2,
            'Model' => 3,
            'Middleware' => 4,
            'Enum' => 5,
            'Exception' => 6,
            'Job' => 7,
            'Observer' => 8,
            'Event' => 9,
            default => 0,
        };
    }
}

// Run the generator
$generator = new CodeReviewGraphGenerator;
$generator->generate();
