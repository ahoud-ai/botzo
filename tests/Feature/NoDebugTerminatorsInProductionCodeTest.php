<?php

namespace Tests\Feature;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class NoDebugTerminatorsInProductionCodeTest extends TestCase
{
    public function test_production_php_code_has_no_dd_dump_die_or_exit_calls(): void
    {
        $roots = [
            base_path('app'),
            base_path('modules'),
        ];

        $violations = [];

        foreach ($roots as $root) {
            if (!is_dir($root)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

            foreach ($iterator as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $path = $file->getPathname();
                $relativePath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $path);

                foreach ($this->scanPhpFileForDebugTerminators($path) as $violation) {
                    $violations[] = "{$relativePath}:{$violation['line']} {$violation['token']}";
                }
            }
        }

        $this->assertSame(
            [],
            $violations,
            "Debug terminators found in production code:\n" . implode(PHP_EOL, $violations)
        );
    }

    /**
     * @return array<int, array{line:int, token:string}>
     */
    private function scanPhpFileForDebugTerminators(string $path): array
    {
        $code = file_get_contents($path);
        if ($code === false) {
            return [];
        }

        $tokens = token_get_all($code);
        $violations = [];
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if (!is_array($token)) {
                continue;
            }

            [$tokenId, $tokenValue, $line] = $token;

            if ($tokenId === T_EXIT) {
                $violations[] = [
                    'line' => $line,
                    'token' => trim($tokenValue) === '' ? 'exit' : trim($tokenValue),
                ];
                continue;
            }

            if ($tokenId !== T_STRING) {
                continue;
            }

            $functionName = strtolower($tokenValue);
            if (!in_array($functionName, ['dd', 'dump'], true)) {
                continue;
            }

            $next = $this->nextSignificantToken($tokens, $i + 1);
            if ($next === '(') {
                $violations[] = [
                    'line' => $line,
                    'token' => $functionName,
                ];
            }
        }

        return $violations;
    }

    /**
     * @param array<int, mixed> $tokens
     */
    private function nextSignificantToken(array $tokens, int $startIndex): string|null
    {
        $count = count($tokens);
        for ($j = $startIndex; $j < $count; $j++) {
            $current = $tokens[$j];

            if (!is_array($current)) {
                return $current;
            }

            if (in_array($current[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return $current[1];
        }

        return null;
    }
}

