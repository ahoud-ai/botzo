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
$failOnOptionsUnlocalized = toBool($options['fail-on-options-unlocalized'] ?? null);

$checks = [
    [
        'file' => 'resources/js/Pages/Admin/Setting/Timezone.vue',
        'type' => 'forbidden_pattern',
        'pattern' => '/:options\\s*=\\s*[\'"]props\\.timezones[\'"]/m',
        'message' => 'Admin timezone settings page still passes raw timezone options.',
    ],
    [
        'file' => 'resources/js/Pages/Admin/Setting/Timezone.vue',
        'type' => 'required_pattern',
        'pattern' => '/localizeTimezoneOptions\\s*\\(/m',
        'message' => 'Admin timezone settings page does not use timezone localizer.',
    ],
    [
        'file' => 'resources/js/Pages/User/Settings/General.vue',
        'type' => 'forbidden_pattern',
        'pattern' => '/:options\\s*=\\s*[\'"]props\\.timezones[\'"]/m',
        'message' => 'Raw timezone options are passed directly from props.',
    ],
    [
        'file' => 'resources/js/Pages/User/Settings/General.vue',
        'type' => 'forbidden_pattern',
        'pattern' => '/:options\\s*=\\s*[\'"]props\\.countries[\'"]/m',
        'message' => 'Raw country options are passed directly from props.',
    ],
    [
        'file' => 'resources/js/Pages/User/Settings/General.vue',
        'type' => 'required_pattern',
        'pattern' => '/localizeTimezoneOptions\\s*\\(/m',
        'message' => 'Timezone localizer is not used in settings.',
    ],
    [
        'file' => 'resources/js/Pages/User/Settings/General.vue',
        'type' => 'required_pattern',
        'pattern' => '/localizeCountryOptions\\s*\\(/m',
        'message' => 'Country localizer is not used in settings.',
    ],
    [
        'file' => 'resources/js/Pages/User/Templates/Add.vue',
        'type' => 'forbidden_pattern',
        'pattern' => '/ref\\s*\\(\\s*props\\.languages\\s*\\)/m',
        'message' => 'Template Add keeps raw language options in ref(props.languages).',
    ],
    [
        'file' => 'resources/js/Pages/User/Templates/Add.vue',
        'type' => 'required_pattern',
        'pattern' => '/localizeTemplateLanguageOptions\\s*\\(/m',
        'message' => 'Template language localizer is not used in add template page.',
    ],
    [
        'file' => 'resources/js/Pages/User/Templates/Edit.vue',
        'type' => 'forbidden_pattern',
        'pattern' => '/ref\\s*\\(\\s*props\\.languages\\s*\\)/m',
        'message' => 'Template Edit keeps raw language options in ref(props.languages).',
    ],
    [
        'file' => 'resources/js/Pages/User/Templates/Edit.vue',
        'type' => 'required_pattern',
        'pattern' => '/localizeTemplateLanguageOptions\\s*\\(/m',
        'message' => 'Template language localizer is not used in edit template page.',
    ],
];

$issues = [];
$scannedFiles = [];

foreach ($checks as $check) {
    $relativeFile = normalizePath((string) $check['file']);
    $absoluteFile = resolvePath($rootPath, $relativeFile);
    $scannedFiles[$relativeFile] = true;

    if (!is_file($absoluteFile)) {
        $issues[] = [
            'type' => 'options_unlocalized_source',
            'category' => 'missing_file',
            'file' => $relativeFile,
            'line' => null,
            'snippet' => null,
            'message' => 'Target file not found for options audit.',
        ];
        continue;
    }

    $content = file_get_contents($absoluteFile);
    if (!is_string($content)) {
        $issues[] = [
            'type' => 'options_unlocalized_source',
            'category' => 'read_error',
            'file' => $relativeFile,
            'line' => null,
            'snippet' => null,
            'message' => 'Unable to read file contents.',
        ];
        continue;
    }

    $pattern = (string) $check['pattern'];
    $matchCount = preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE);

    if ($check['type'] === 'forbidden_pattern' && $matchCount > 0) {
        foreach ($matches[0] as $match) {
            $issues[] = [
                'type' => 'options_unlocalized_source',
                'category' => 'forbidden_pattern',
                'file' => $relativeFile,
                'line' => offsetToLine($content, (int) $match[1]),
                'snippet' => trim((string) $match[0]),
                'message' => (string) $check['message'],
            ];
        }
    }

    if ($check['type'] === 'required_pattern' && $matchCount === 0) {
        $issues[] = [
            'type' => 'options_unlocalized_source',
            'category' => 'missing_required_pattern',
            'file' => $relativeFile,
            'line' => null,
            'snippet' => null,
            'message' => (string) $check['message'],
        ];
    }
}

$report = [
    'generated_at' => date(DATE_ATOM),
    'scope' => ['admin_settings', 'user_settings', 'user_templates'],
    'summary' => [
        'files_scanned' => count($scannedFiles),
        'options_unlocalized_sources' => count($issues),
    ],
    'issues' => $issues,
    'exit_policy' => [
        'fail_on_options_unlocalized' => $failOnOptionsUnlocalized,
    ],
];

$rendered = $format === 'markdown'
    ? renderMarkdown($report)
    : json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

if ($rendered === false) {
    fwrite(STDERR, "Failed to render options audit report.\n");
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

$shouldFail = $failOnOptionsUnlocalized && count($issues) > 0;
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

function offsetToLine(string $content, int $offset): int
{
    return substr_count(substr($content, 0, $offset), "\n") + 1;
}

function renderMarkdown(array $report): string
{
    $summary = $report['summary'] ?? [];
    $lines = [];
    $lines[] = '# UI Options Audit Report';
    $lines[] = '';
    $lines[] = '- Generated: ' . ($report['generated_at'] ?? '');
    $lines[] = '';
    $lines[] = '| Metric | Value |';
    $lines[] = '| --- | ---: |';
    $lines[] = '| files_scanned | ' . ($summary['files_scanned'] ?? 0) . ' |';
    $lines[] = '| options_unlocalized_sources | ' . ($summary['options_unlocalized_sources'] ?? 0) . ' |';
    $lines[] = '';
    $lines[] = '## Issues';
    $lines[] = '';

    foreach ($report['issues'] ?? [] as $issue) {
        $file = $issue['file'] ?? 'n/a';
        $line = $issue['line'] ?? 'n/a';
        $message = $issue['message'] ?? '';
        $snippet = $issue['snippet'] ?? '';
        $lines[] = "- {$message} (`{$file}:{$line}`)";
        if ($snippet !== '') {
            $lines[] = "  - `{$snippet}`";
        }
    }

    return implode(PHP_EOL, $lines);
}
