<?php

declare(strict_types=1);

$rootPath = realpath(__DIR__ . '/../../');
if ($rootPath === false) {
    fwrite(STDERR, "Unable to resolve project root.\n");
    exit(1);
}

$options = parseCliOptions($argv);
$format = strtolower((string) ($options['format'] ?? 'json'));
$apply = toBool($options['apply'] ?? null);
$outputPath = isset($options['out']) ? resolvePath($rootPath, (string) $options['out']) : null;
$requestedLocales = array_filter(array_map('trim', explode(',', (string) ($options['locales'] ?? ''))));

$localeFiles = collectLocaleFiles($rootPath, $requestedLocales);
$scanPaths = [
    'app',
    'bootstrap',
    'config',
    'database',
    'resources/js',
    'resources/views',
    'routes',
    'tests',
];
$excludedPaths = array_map(
    static fn (string $path): string => normalizePath($path),
    [
        'vendor',
        'node_modules',
        'public/build',
        '_archive',
        'docs',
        'lang',
        'tmp',
        'storage',
    ]
);

$files = collectFiles($rootPath, $scanPaths, $excludedPaths);
$localePayloads = loadLocalePayloads($localeFiles);
$knownKeys = [];

foreach ($localePayloads as $payload) {
    foreach (array_keys($payload) as $key) {
        $knownKeys[$key] = true;
    }
}

$usedKeys = [];

foreach ($files as $filePath) {
    $content = file_get_contents($filePath);
    if (!is_string($content) || $content === '') {
        continue;
    }

    foreach (extractTranslationKeys($content) as $key) {
        if (isset($knownKeys[$key])) {
            $usedKeys[$key] = true;
        }
    }

    foreach (extractDynamicTranslationCandidates($content) as $candidate) {
        if (isset($knownKeys[$candidate])) {
            $usedKeys[$candidate] = true;
        }
    }
}

$reportLocales = [];
$totalUnused = 0;

foreach ($localePayloads as $locale => $payload) {
    $kept = [];
    $unused = [];

    foreach ($payload as $key => $value) {
        if (isset($usedKeys[$key])) {
            $kept[$key] = $value;
            continue;
        }

        $unused[$key] = $value;
    }

    if ($apply && $unused !== []) {
        writeLocalePayload($localeFiles[$locale], $kept);
    }

    $unusedKeys = array_keys($unused);
    sort($unusedKeys);

    $reportLocales[$locale] = [
        'file' => normalizePath(str_replace($rootPath . DIRECTORY_SEPARATOR, '', $localeFiles[$locale])),
        'total_keys' => count($payload),
        'used_keys' => count($payload) - count($unused),
        'unused_keys' => count($unused),
        'sample_unused_keys' => array_slice($unusedKeys, 0, 25),
    ];

    $totalUnused += count($unused);
}

$report = [
    'generated_at' => date(DATE_ATOM),
    'mode' => $apply ? 'apply' : 'dry-run',
    'scan_paths' => $scanPaths,
    'excluded_paths' => $excludedPaths,
    'summary' => [
        'files_scanned' => count($files),
        'locale_files' => count($localeFiles),
        'known_keys' => count($knownKeys),
        'used_keys' => count($usedKeys),
        'unused_keys' => $totalUnused,
    ],
    'locales' => $reportLocales,
];

$rendered = $format === 'markdown'
    ? renderMarkdown($report)
    : json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

if ($rendered === false) {
    fwrite(STDERR, "Failed to render prune report.\n");
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

exit(0);

function parseCliOptions(array $argv): array
{
    $options = [];

    foreach ($argv as $index => $arg) {
        if ($index === 0 || !str_starts_with($arg, '--')) {
            continue;
        }

        $arg = substr($arg, 2);
        [$key, $value] = array_pad(explode('=', $arg, 2), 2, true);
        $options[$key] = $value;
    }

    return $options;
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

function collectLocaleFiles(string $rootPath, array $requestedLocales): array
{
    $langPath = $rootPath . DIRECTORY_SEPARATOR . 'lang';
    $files = [];

    foreach (glob($langPath . DIRECTORY_SEPARATOR . '*.json') ?: [] as $path) {
        $locale = pathinfo($path, PATHINFO_FILENAME);
        if ($requestedLocales !== [] && !in_array($locale, $requestedLocales, true)) {
            continue;
        }

        $files[$locale] = $path;
    }

    ksort($files);

    return $files;
}

function loadLocalePayloads(array $localeFiles): array
{
    $payloads = [];

    foreach ($localeFiles as $locale => $path) {
        $content = file_get_contents($path);
        $decoded = is_string($content) ? json_decode($content, true) : null;
        $payloads[$locale] = is_array($decoded) ? $decoded : [];
    }

    return $payloads;
}

function collectFiles(string $rootPath, array $scanPaths, array $excludedPaths): array
{
    $extensions = ['php', 'vue', 'js', 'ts'];
    $files = [];

    foreach ($scanPaths as $scanPath) {
        $absolutePath = $rootPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $scanPath);
        if (is_file($absolutePath)) {
            $relativePath = normalizePath(str_replace($rootPath . DIRECTORY_SEPARATOR, '', $absolutePath));
            if (!isExcluded($relativePath, $excludedPaths)) {
                $files[] = $absolutePath;
            }
            continue;
        }

        if (!is_dir($absolutePath)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($absolutePath, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }

            $extension = strtolower((string) $fileInfo->getExtension());
            if (!in_array($extension, $extensions, true)) {
                continue;
            }

            $fullPath = $fileInfo->getPathname();
            $relativePath = normalizePath(str_replace($rootPath . DIRECTORY_SEPARATOR, '', $fullPath));

            if (isExcluded($relativePath, $excludedPaths)) {
                continue;
            }

            $files[] = $fullPath;
        }
    }

    sort($files);

    return array_values(array_unique($files));
}

function isExcluded(string $relativePath, array $excludedPaths): bool
{
    foreach ($excludedPaths as $excludedPath) {
        if (str_starts_with($relativePath, $excludedPath)) {
            return true;
        }
    }

    return false;
}

function extractTranslationKeys(string $content): array
{
    $matches = [];
    $patterns = [
        '/(?:\$t|trans|__|@lang)\(\s*[\'"]([^\'"]+)[\'"]\s*[\),]/u',
    ];

    foreach ($patterns as $pattern) {
        if (!preg_match_all($pattern, $content, $found, PREG_OFFSET_CAPTURE)) {
            continue;
        }

        foreach ($found[1] as $capture) {
            $key = trim((string) ($capture[0] ?? ''));
            if ($key !== '' && !str_contains($key, '${')) {
                $matches[$key] = true;
            }
        }
    }

    return array_keys($matches);
}

function extractDynamicTranslationCandidates(string $content): array
{
    $matches = [];

    $patterns = [
        '/\b(label|placeholder|title|subtitle|description|helperText|helper_text|emptyText|buttonText|confirmText|cancelText|heading|caption|tab|message)\b\s*:\s*([\'"])(.+?)\2/us',
        '/[\'"](label|placeholder|title|subtitle|description|helperText|helper_text|emptyText|buttonText|confirmText|cancelText|heading|caption|tab|message)[\'"]\s*=>\s*([\'"])(.+?)\2/us',
        '/(?<![:@])\b(placeholder|title|label|aria-label|alt)\s*=\s*([\'"])([^\'"]+)\2/u',
    ];

    foreach ($patterns as $pattern) {
        if (!preg_match_all($pattern, $content, $found, PREG_SET_ORDER)) {
            continue;
        }

        foreach ($found as $entry) {
            $candidate = trim((string) ($entry[3] ?? ''));
            if ($candidate === '' || shouldIgnoreLiteral($candidate)) {
                continue;
            }

            $matches[$candidate] = true;
        }
    }

    return array_keys($matches);
}

function shouldIgnoreLiteral(string $text): bool
{
    if ($text === '' || preg_match('/^[\d\W_]+$/u', $text) === 1) {
        return true;
    }

    if (str_contains($text, '{{') || str_contains($text, '}}') || str_contains($text, '${')) {
        return true;
    }

    if (preg_match('/^\$t\(/u', $text) === 1 || preg_match('/^(trans|__)\(/u', $text) === 1) {
        return true;
    }

    if (preg_match('/^[a-z0-9._:-]+$/u', $text) === 1) {
        return true;
    }

    return false;
}

function writeLocalePayload(string $path, array $payload): void
{
    $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($encoded === false) {
        throw new RuntimeException('Unable to encode locale payload for {$path}');
    }

    file_put_contents($path, $encoded . PHP_EOL);
}

function renderMarkdown(array $report): string
{
    $lines = [];
    $lines[] = '# Locale Prune Report';
    $lines[] = '';
    $lines[] = '- Generated: ' . ($report['generated_at'] ?? '');
    $lines[] = '- Mode: ' . ($report['mode'] ?? 'dry-run');
    $lines[] = '';
    $lines[] = '## Summary';
    $lines[] = '';
    $lines[] = '| Metric | Value |';
    $lines[] = '| --- | ---: |';

    foreach (($report['summary'] ?? []) as $key => $value) {
        $lines[] = '| ' . $key . ' | ' . $value . ' |';
    }

    $lines[] = '';
    $lines[] = '## Locales';
    $lines[] = '';

    foreach (($report['locales'] ?? []) as $locale => $data) {
        $lines[] = '### ' . $locale;
        $lines[] = '- File: `' . ($data['file'] ?? '') . '`';
        $lines[] = '- Total keys: ' . ($data['total_keys'] ?? 0);
        $lines[] = '- Used keys: ' . ($data['used_keys'] ?? 0);
        $lines[] = '- Unused keys: ' . ($data['unused_keys'] ?? 0);

        $samples = $data['sample_unused_keys'] ?? [];
        if ($samples !== []) {
            $lines[] = '- Sample: `' . implode('`, `', $samples) . '`';
        }

        $lines[] = '';
    }

    return implode(PHP_EOL, $lines);
}
