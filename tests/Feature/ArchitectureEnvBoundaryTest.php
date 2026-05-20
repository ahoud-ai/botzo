<?php

namespace Tests\Feature;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class ArchitectureEnvBoundaryTest extends TestCase
{
    public function test_env_helper_is_not_used_outside_config_files(): void
    {
        $violations = [];

        foreach ($this->phpFilesToScan() as $path) {
            $contents = file_get_contents($path);
            if ($contents === false) {
                $violations[] = $path.' (unreadable)';

                continue;
            }

            $tokens = token_get_all($contents);
            $tokenCount = count($tokens);

            for ($index = 0; $index < $tokenCount; $index++) {
                $token = $tokens[$index];
                if (! is_array($token) || $token[0] !== T_STRING || strtolower($token[1]) !== 'env') {
                    continue;
                }

                $next = $this->nextNonWhitespaceToken($tokens, $index + 1);
                if ($next === '(') {
                    $violations[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $path).':'.$token[2];
                }
            }
        }

        $this->assertSame([], $violations, 'env() usage detected outside config: '.implode(', ', $violations));
    }

    /**
     * @return array<int, string>
     */
    private function phpFilesToScan(): array
    {
        $roots = [
            app_path(),
            base_path('bootstrap'),
            base_path('routes'),
            base_path('tests'),
        ];

        $files = [];

        foreach ($roots as $root) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    /**
     * @param  array<int, mixed>  $tokens
     */
    private function nextNonWhitespaceToken(array $tokens, int $index): mixed
    {
        $tokenCount = count($tokens);

        while ($index < $tokenCount) {
            $token = $tokens[$index];
            if (is_string($token)) {
                return $token;
            }

            if (! in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                return $token[1];
            }

            $index++;
        }

        return null;
    }
}
