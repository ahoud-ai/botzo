<?php

namespace Tests\Feature;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class NoFrontendDebugArtifactsTest extends TestCase
{
    public function test_frontend_production_scope_has_no_console_log_or_debugger_statements(): void
    {
        $root = base_path('resources/js');
        $violations = [];

        if (!is_dir($root)) {
            $this->assertTrue(true);
            return;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $ext = strtolower($file->getExtension());
            if (!in_array($ext, ['js', 'vue', 'ts'], true)) {
                continue;
            }

            $path = $file->getPathname();
            if (preg_match('/\.(test|spec)\./i', $path)) {
                continue;
            }

            $relativePath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $path);
            $lines = @file($path);

            if ($lines === false) {
                continue;
            }

            $insideBlockComment = false;
            foreach ($lines as $index => $line) {
                $lineNumber = $index + 1;
                $trimmed = ltrim($line);

                if ($insideBlockComment) {
                    if (str_contains($trimmed, '*/')) {
                        $insideBlockComment = false;
                    }
                    continue;
                }

                if (str_starts_with($trimmed, '/*')) {
                    if (!str_contains($trimmed, '*/')) {
                        $insideBlockComment = true;
                    }
                    continue;
                }

                if (str_starts_with($trimmed, '//')) {
                    continue;
                }

                if (preg_match('/\bconsole\.log\s*\(/', $line) === 1) {
                    $violations[] = "{$relativePath}:{$lineNumber} console.log";
                }

                if (preg_match('/\bdebugger\b/', $line) === 1) {
                    $violations[] = "{$relativePath}:{$lineNumber} debugger";
                }
            }
        }

        $this->assertSame(
            [],
            $violations,
            "Frontend debug artifacts found:\n" . implode(PHP_EOL, $violations)
        );
    }
}

