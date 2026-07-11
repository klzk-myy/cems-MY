<?php

namespace Tests\Unit\CodeQuality;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

class DeprecatedMarkersTest extends TestCase
{
    /**
     * Patterns that look like TODO / XXX / DEPRECATED / HACK markers.
     *
     * @var array<int, string>
     */
    protected array $markerPatterns = [
        '/(?:#|\/\/|\*)\s*XXX\b/',
        '/(?:#|\/\/|\*)\s*TODO\b/',
        '/(?:#|\/\/|\*)\s*HACK\b/',
        '/(?:#|\/\/|\*)\s*FIXME\b/',
        '/(?:#|\/\/|\*)\s*DEPRECATED\b/',
    ];

    /**
     * Paths that should be scanned.
     *
     * @var array<int, string>
     */
    protected array $scanPaths = [
        'app',
        'routes',
        'config',
        'scripts',
    ];

    /**
     * File patterns to ignore.
     *
     * @var array<int, string>
     */
    protected array $ignoredFiles = [
        '*.blade.php',
    ];

    #[Test]
    public function no_todo_or_deprecated_markers_in_application_code(): void
    {
        $violations = [];
        $basePath = base_path();

        foreach ($this->scanPaths as $relativePath) {
            $path = $basePath.'/'.$relativePath;

            if (! is_dir($path)) {
                continue;
            }

            $finder = new Finder;
            $finder->files()->in($path)->name('*.{php,md,json,sh}')->notName($this->ignoredFiles);

            foreach ($finder as $file) {
                $content = $file->getContents();
                $relative = $file->getRelativePathname();

                foreach ($this->markerPatterns as $pattern) {
                    if (preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                        $line = substr_count(substr($content, 0, $matches[0][1]), "\n") + 1;
                        $violations[] = "{$relative}:{$line}: {$matches[0][0]}";
                        break;
                    }
                }
            }
        }

        $this->assertEmpty(
            $violations,
            'Found TODO / XXX / HACK / FIXME / DEPRECATED markers in application code: '.implode(', ', $violations)
        );
    }
}
