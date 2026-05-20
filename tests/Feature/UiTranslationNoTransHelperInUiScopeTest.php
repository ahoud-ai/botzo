<?php

namespace Tests\Feature;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use Tests\TestCase;

class UiTranslationNoTransHelperInUiScopeTest extends TestCase
{
    public function test_ui_scope_does_not_use_laravel_vue_i18n_trans_helper(): void
    {
        $projectRoot = base_path();
        $scanRoots = [
            'resources/js/Pages/Admin',
            'resources/js/Pages/User',
            'resources/js/Pages/Frontend',
            'resources/js/Pages/Auth',
            'resources/js/Components',
        ];

        $excluded = array_map(
            static fn (string $path): string => str_replace('\\', '/', trim($path, '/')),
            require base_path('scripts/i18n/excluded_paths.php')
        );

        $violations = [];

        foreach ($scanRoots as $scanRoot) {
            $absoluteScanRoot = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $scanRoot);
            if (!is_dir($absoluteScanRoot)) {
                continue;
            }

            $iterator = new RegexIterator(
                new RecursiveIteratorIterator(new RecursiveDirectoryIterator($absoluteScanRoot)),
                '/^.+\.vue$/i',
                RegexIterator::GET_MATCH
            );

            foreach ($iterator as $match) {
                $filePath = (string) ($match[0] ?? '');
                if ($filePath === '') {
                    continue;
                }

                $relativePath = str_replace('\\', '/', ltrim(str_replace($projectRoot, '', $filePath), '/'));
                if ($this->isExcluded($relativePath, $excluded)) {
                    continue;
                }

                $content = file_get_contents($filePath);
                if (!is_string($content)) {
                    continue;
                }

                if (
                    str_contains($content, 'laravel-vue-i18n')
                    || preg_match('/(?<![\\w$])trans\\s*\\(/', $content) === 1
                ) {
                    $violations[] = $relativePath;
                }
            }
        }

        sort($violations);

        $this->assertCount(
            0,
            $violations,
            "Found forbidden laravel-vue-i18n/trans usage in UI scope:\n" . implode(PHP_EOL, $violations)
        );
    }

    private function isExcluded(string $relativePath, array $excludedPrefixes): bool
    {
        foreach ($excludedPrefixes as $excludedPrefix) {
            if ($excludedPrefix !== '' && str_starts_with($relativePath, $excludedPrefix)) {
                return true;
            }
        }

        return false;
    }
}
