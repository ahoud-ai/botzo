<?php

namespace Tests\Feature;

use Tests\TestCase;

class UnsafeFilePatternTest extends TestCase
{
    public function test_repository_has_no_probe_or_temp_runtime_files(): void
    {
        $forbiddenPaths = [
            '_write_test.txt',
            '_root_write_test.txt',
            'docroot.php',
            'root-ok.txt',
            'public/__probe.txt',
            'public/docroot.php',
            'public/public-ok.txt',
        ];

        $existing = [];

        foreach ($forbiddenPaths as $path) {
            if (file_exists(base_path($path))) {
                $existing[] = $path;
            }
        }

        $this->assertSame(
            [],
            $existing,
            "Forbidden temporary/probe files must not exist in project root:\n" . implode(PHP_EOL, $existing)
        );
    }

    public function test_repository_has_no_forbidden_os_or_backup_artifacts(): void
    {
        $output = [];
        $statusCode = 0;
        exec('git ls-files --cached --others --exclude-standard', $output, $statusCode);

        $this->assertSame(0, $statusCode, 'Failed to read git file list for hygiene assertions.');

        $forbiddenMatches = array_values(array_filter(array_map(static fn ($line) => str_replace('\\', '/', trim($line)), $output), static function ($path) {
            if ($path === '') {
                return false;
            }

            return str_ends_with($path, '/.DS_Store')
                || str_ends_with($path, '.bak')
                || str_ends_with($path, '.tmp')
                || str_ends_with($path, '.old')
                || str_ends_with($path, '.orig');
        }));

        sort($forbiddenMatches);

        $this->assertSame(
            [],
            $forbiddenMatches,
            "Forbidden OS/backup artifacts detected:\n" . implode(PHP_EOL, $forbiddenMatches)
        );
    }
}

