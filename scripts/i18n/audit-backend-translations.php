<?php

declare(strict_types=1);

$rootPath = realpath(__DIR__ . '/../../');
if ($rootPath === false) {
    fwrite(STDERR, "Unable to resolve project root.\n");
    exit(1);
}

$options = parseCliOptions($argv);
$format = strtolower((string) ($options['format'] ?? 'json'));
$outputPath = isset($options['out']) ? resolvePath($rootPath, (string) $options['out']) : null;
$failOnUntranslated = toBool($options['fail-on-backend-untranslated'] ?? null);

$allowlist = array_values(array_unique(array_map(
    static fn (string $term): string => mb_strtolower(trim($term)),
    require __DIR__ . '/allowlist_terms.php'
)));

$scanPaths = [
    'app/Http/Controllers',
    'app/Services',
    'app/Rules',
    'app/Exceptions',
];

$files = collectFiles($rootPath, $scanPaths);
$issues = [];

foreach ($files as $filePath) {
    $content = file_get_contents($filePath);
    if (!is_string($content)) {
        continue;
    }

    $relativePath = normalizePath((string) str_replace($rootPath . DIRECTORY_SEPARATOR, '', $filePath));
    $issues = array_merge($issues, findIssuesInFile($content, $relativePath, $allowlist));
}

$issues = deduplicateIssues($issues);

$report = [
    'generated_at' => date(DATE_ATOM),
    'scope' => $scanPaths,
    'summary' => [
        'files_scanned' => count($files),
        'backend_untranslated_messages' => count($issues),
    ],
    'issues' => $issues,
    'exit_policy' => [
        'fail_on_backend_untranslated' => $failOnUntranslated,
    ],
];

$rendered = $format === 'markdown'
    ? renderMarkdown($report)
    : json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

if ($rendered === false) {
    fwrite(STDERR, "Failed to render backend i18n report.\n");
    exit(1);
}

if ($outputPath !== null) {
    $outputDir = dirname($outputPath);
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0777, true);
    }
    file_put_contents($outputPath, $rendered);
} else {
    echo $rendered . PHP_EOL;
}

$shouldFail = $failOnUntranslated && count($issues) > 0;
exit($shouldFail ? 1 : 0);

function parseCliOptions(array $argv): array
{
    $result = [];
    foreach ($argv as $index => $arg) {
        if ($index === 0 || !str_starts_with($arg, '--')) {
            continue;
        }

        $arg = substr($arg, 2);
        [$key, $value] = array_pad(explode('=', $arg, 2), 2, true);
        $result[$key] = $value;
    }

    return $result;
}

function toBool(mixed $value): bool
{
    if ($value === null) {
        return false;
    }

    return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
}

function resolvePath(string $rootPath, string $path): string
{
    if ($path === '') {
        return $rootPath;
    }

    if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1 || str_starts_with($path, '/')) {
        return $path;
    }

    return $rootPath . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
}

function normalizePath(string $path): string
{
    return str_replace('\\', '/', $path);
}

function collectFiles(string $rootPath, array $scanPaths): array
{
    $files = [];
    $excludedPrefixes = [
        'app/Services/System/',
    ];

    foreach ($scanPaths as $scanPath) {
        $absolutePath = $rootPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $scanPath);
        if (!is_dir($absolutePath)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($absolutePath, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile() || strtolower((string) $fileInfo->getExtension()) !== 'php') {
                continue;
            }

            $relativePath = normalizePath((string) str_replace($rootPath . DIRECTORY_SEPARATOR, '', $fileInfo->getPathname()));
            if (in_array($relativePath, ['app/Services/QueueProfileService.php'], true)) {
                continue;
            }

            $isExcluded = false;
            foreach ($excludedPrefixes as $prefix) {
                if (str_starts_with($relativePath, $prefix)) {
                    $isExcluded = true;
                    break;
                }
            }

            if ($isExcluded) {
                continue;
            }

            $files[] = $fileInfo->getPathname();
        }
    }

    sort($files);

    return array_values(array_unique($files));
}

function findIssuesInFile(string $content, string $relativePath, array $allowlist): array
{
    $patterns = [
        [
            'type' => 'abort_untranslated',
            'category' => 'abort',
            'regex' => '/\babort\s*\(\s*\d+\s*,\s*([\'"])(?<text>(?:\\\\.|(?!\1).)+)\1/um',
        ],
        [
            'type' => 'json_message_untranslated',
            'category' => 'json_message',
            'regex' => '/[\'"](?:message|error)[\'"]\s*=>\s*([\'"])(?<text>(?:\\\\.|(?!\1).)+)\1/um',
        ],
        [
            'type' => 'flash_message_untranslated',
            'category' => 'flash_message',
            'regex' => '/->with\s*\(\s*[\'"](?:status|message|success|error|warning|info)[\'"]\s*,\s*([\'"])(?<text>(?:\\\\.|(?!\1).)+)\1/um',
        ],
        [
            'type' => 'exception_message_untranslated',
            'category' => 'exception_message',
            'regex' => '/throw\s+new\s+[\\\\A-Za-z_][\\\\A-Za-z0-9_]*\s*\(\s*([\'"])(?<text>(?:\\\\.|(?!\1).)+)\1/um',
        ],
    ];

    $issues = [];
    foreach ($patterns as $pattern) {
        if (!preg_match_all($pattern['regex'], $content, $matches, PREG_OFFSET_CAPTURE)) {
            continue;
        }

        $rawMatches = $matches[0] ?? [];
        $rawTexts = $matches['text'] ?? [];
        foreach ($rawTexts as $index => $capture) {
            $text = trim((string) stripcslashes($capture[0]));
            $offset = (int) $capture[1];
            $snippet = trim((string) ($rawMatches[$index][0] ?? ''));

            if (shouldIgnoreMessage($text, $allowlist)) {
                continue;
            }

            if (containsLocalizationCall($snippet)) {
                continue;
            }

            $issues[] = [
                'type' => $pattern['type'],
                'category' => $pattern['category'],
                'file' => $relativePath,
                'line' => offsetToLine($content, $offset),
                'text' => $text,
                'snippet' => $snippet,
            ];
        }
    }

    return $issues;
}

function shouldIgnoreMessage(string $text, array $allowlist): bool
{
    if ($text === '') {
        return true;
    }

    if (!preg_match('/[\p{L}]/u', $text)) {
        return true;
    }

    if (containsArabic($text)) {
        return true;
    }

    $normalized = mb_strtolower(trim($text));
    if (in_array($normalized, $allowlist, true)) {
        return true;
    }

    $validatorKeywords = [
        'required',
        'string',
        'integer',
        'boolean',
        'array',
        'numeric',
        'date',
        'email',
        'nullable',
        'sometimes',
        'confirmed',
        'exists',
        'unique',
    ];

    if (in_array($normalized, $validatorKeywords, true)) {
        return true;
    }

    if (preg_match('/^[a-z0-9_.:-]+$/u', $text) === 1) {
        return true;
    }

    if (str_contains($text, '{$')) {
        return true;
    }

    return false;
}

function containsLocalizationCall(string $snippet): bool
{
    return preg_match('/__\s*\(|trans\s*\(|\$t\s*\(/u', $snippet) === 1;
}

function containsArabic(string $text): bool
{
    return preg_match('/\p{Arabic}/u', $text) === 1;
}

function offsetToLine(string $content, int $offset): int
{
    if ($offset <= 0) {
        return 1;
    }

    return substr_count(substr($content, 0, $offset), "\n") + 1;
}

function deduplicateIssues(array $issues): array
{
    $map = [];
    foreach ($issues as $issue) {
        $key = implode('|', [
            (string) ($issue['type'] ?? ''),
            (string) ($issue['file'] ?? ''),
            (string) ($issue['line'] ?? ''),
            (string) ($issue['text'] ?? ''),
        ]);
        $map[$key] = $issue;
    }

    $deduped = array_values($map);
    usort($deduped, static function (array $a, array $b): int {
        $fileCompare = strcmp((string) ($a['file'] ?? ''), (string) ($b['file'] ?? ''));
        if ($fileCompare !== 0) {
            return $fileCompare;
        }

        return ((int) ($a['line'] ?? 0)) <=> ((int) ($b['line'] ?? 0));
    });

    return $deduped;
}

function renderMarkdown(array $report): string
{
    $summary = $report['summary'] ?? [];
    $lines = [];
    $lines[] = '# Backend Translation Audit Report';
    $lines[] = '';
    $lines[] = '- Generated: ' . ($report['generated_at'] ?? '');
    $lines[] = '';
    $lines[] = '| Metric | Value |';
    $lines[] = '| --- | ---: |';
    $lines[] = '| files_scanned | ' . (int) ($summary['files_scanned'] ?? 0) . ' |';
    $lines[] = '| backend_untranslated_messages | ' . (int) ($summary['backend_untranslated_messages'] ?? 0) . ' |';
    $lines[] = '';
    $lines[] = '## Issues';
    $lines[] = '';

    foreach ($report['issues'] ?? [] as $issue) {
        $lines[] = '- `' . ($issue['text'] ?? '') . '` at `' . ($issue['file'] ?? 'n/a') . ':' . ($issue['line'] ?? 0) . '`';
    }

    return implode(PHP_EOL, $lines);
}
