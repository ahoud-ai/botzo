<?php

declare(strict_types=1);

$rootPath = realpath(__DIR__ . '/../../');
if ($rootPath === false) {
    fwrite(STDERR, "Unable to resolve project root.\n");
    exit(1);
}

$options = parseCliOptions($argv);

$staticPath = resolvePath($rootPath, (string) ($options['static'] ?? 'tmp/i18n-audit-static.json'));
$astPath = resolvePath($rootPath, (string) ($options['ast'] ?? 'tmp/i18n-audit-ast.json'));
$optionsPath = resolvePath($rootPath, (string) ($options['options'] ?? 'tmp/i18n-audit-options.json'));
$format = strtolower((string) ($options['format'] ?? 'json'));
$outputPath = isset($options['out']) ? resolvePath($rootPath, (string) $options['out']) : null;

$failOnMissingAr = toBool($options['fail-on-missing-ar'] ?? null);
$failOnMissingEn = toBool($options['fail-on-missing-en'] ?? null);
$failOnLiteralUntranslated = toBool($options['fail-on-literal-untranslated'] ?? null);
$failOnTemplateUntranslated = toBool($options['fail-on-template-untranslated'] ?? null);
$failOnOptionsUnlocalized = toBool($options['fail-on-options-unlocalized'] ?? null);
$failOnArEqualsEn = toBool($options['fail-on-ar-equals-en'] ?? null);
$failOnEnDefaultDrift = toBool($options['fail-on-en-default-drift'] ?? null);

$staticReport = readJsonFile($staticPath);
$astReport = readJsonFile($astPath);
$optionsReport = readJsonFile($optionsPath);

if ($staticReport === null) {
    fwrite(STDERR, "Missing or invalid static report: {$staticPath}\n");
    exit(1);
}

if ($astReport === null) {
    fwrite(STDERR, "Missing or invalid AST report: {$astPath}\n");
    exit(1);
}

if ($optionsReport === null) {
    fwrite(STDERR, "Missing or invalid options report: {$optionsPath}\n");
    exit(1);
}

$summary = [
    'files_scanned_static' => (int) ($staticReport['summary']['files_scanned'] ?? 0),
    'files_scanned_ast' => (int) ($astReport['summary']['files_scanned'] ?? 0),
    'used_keys' => (int) ($staticReport['summary']['used_keys'] ?? 0),
    'missing_in_ar' => (int) ($staticReport['summary']['missing_in_ar'] ?? 0),
    'missing_in_en' => (int) ($staticReport['summary']['missing_in_en'] ?? 0),
    'missing_total_unique' => (int) ($staticReport['summary']['missing_total_unique'] ?? 0),
    'missing_escaped_unique' => (int) ($staticReport['summary']['missing_escaped_unique'] ?? 0),
    'literal_untranslated' => (int) ($staticReport['summary']['literal_untranslated'] ?? 0),
    'literal_allowed_term' => (int) ($staticReport['summary']['literal_allowed_term'] ?? 0),
    'ar_equal_en_nontechnical' => (int) ($staticReport['summary']['ar_equal_en_nontechnical'] ?? 0),
    'en_default_drift' => (int) ($staticReport['summary']['en_default_drift'] ?? 0),
    'escaped_keys_detected' => (int) ($staticReport['summary']['escaped_keys_detected'] ?? 0),
    'template_text_untranslated' => (int) ($astReport['summary']['template_text_untranslated'] ?? 0),
    'template_allowed_term' => (int) ($astReport['summary']['template_allowed_term'] ?? 0),
    'options_unlocalized_sources' => (int) ($optionsReport['summary']['options_unlocalized_sources'] ?? 0),
];

$merged = [
    'generated_at' => date(DATE_ATOM),
    'scope' => array_values(array_unique(array_merge(
        $staticReport['scope'] ?? [],
        $astReport['scope'] ?? []
    ))),
    'locales' => $staticReport['locales'] ?? ['ar', 'en'],
    'summary' => $summary,
    'static_report_path' => normalizePath((string) str_replace($rootPath . DIRECTORY_SEPARATOR, '', $staticPath)),
    'ast_report_path' => normalizePath((string) str_replace($rootPath . DIRECTORY_SEPARATOR, '', $astPath)),
    'options_report_path' => normalizePath((string) str_replace($rootPath . DIRECTORY_SEPARATOR, '', $optionsPath)),
    'missing_by_locale' => $staticReport['missing_by_locale'] ?? [],
    'missing_diagnostics' => $staticReport['missing_diagnostics'] ?? [],
    'literal_issues' => $staticReport['literal_issues'] ?? [],
    'escaped_keys_detected' => $staticReport['escaped_keys_detected'] ?? [],
    'escaped_occurrences' => $staticReport['escaped_occurrences'] ?? [],
    'cross_locale_issues' => $staticReport['cross_locale_issues'] ?? [],
    'default_en_drift' => $staticReport['default_en_drift'] ?? [
        'missing_in_default_en' => [],
        'extra_in_default_en' => [],
        'value_mismatches' => [],
    ],
    'template_issues' => $astReport['template_issues'] ?? [],
    'options_issues' => $optionsReport['issues'] ?? [],
    'issues' => array_values(array_merge(
        $staticReport['issues'] ?? [],
        array_values(array_filter(
            $astReport['template_issues'] ?? [],
            static fn ($item) => ($item['type'] ?? '') === 'template_text_untranslated'
        )),
        $optionsReport['issues'] ?? []
    )),
    'exit_policy' => [
        'fail_on_missing_ar' => $failOnMissingAr,
        'fail_on_missing_en' => $failOnMissingEn,
        'fail_on_literal_untranslated' => $failOnLiteralUntranslated,
        'fail_on_template_untranslated' => $failOnTemplateUntranslated,
        'fail_on_options_unlocalized' => $failOnOptionsUnlocalized,
        'fail_on_ar_equals_en' => $failOnArEqualsEn,
        'fail_on_en_default_drift' => $failOnEnDefaultDrift,
    ],
];

$rendered = $format === 'markdown'
    ? renderMarkdown($merged)
    : json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

if ($rendered === false) {
    fwrite(STDERR, "Failed to render merged report.\n");
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

$hasFailure = false;
if ($failOnMissingAr && $summary['missing_in_ar'] > 0) {
    $hasFailure = true;
}
if ($failOnMissingEn && $summary['missing_in_en'] > 0) {
    $hasFailure = true;
}
if ($failOnLiteralUntranslated && $summary['literal_untranslated'] > 0) {
    $hasFailure = true;
}
if ($failOnTemplateUntranslated && $summary['template_text_untranslated'] > 0) {
    $hasFailure = true;
}
if ($failOnOptionsUnlocalized && $summary['options_unlocalized_sources'] > 0) {
    $hasFailure = true;
}
if ($failOnArEqualsEn && $summary['ar_equal_en_nontechnical'] > 0) {
    $hasFailure = true;
}
if ($failOnEnDefaultDrift && $summary['en_default_drift'] > 0) {
    $hasFailure = true;
}

exit($hasFailure ? 1 : 0);

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

function readJsonFile(string $path): ?array
{
    if (!is_file($path)) {
        return null;
    }

    $raw = file_get_contents($path);
    if (!is_string($raw)) {
        return null;
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}

function normalizePath(string $path): string
{
    return str_replace('\\', '/', $path);
}

function renderMarkdown(array $report): string
{
    $s = $report['summary'] ?? [];
    $lines = [];
    $lines[] = '# Combined I18n Audit Report';
    $lines[] = '';
    $lines[] = '- Generated: ' . ($report['generated_at'] ?? '');
    $lines[] = '- Scope: ' . implode(', ', $report['scope'] ?? []);
    $lines[] = '';
    $lines[] = '## Summary';
    $lines[] = '';
    $lines[] = '| Metric | Value |';
    $lines[] = '| --- | ---: |';

    foreach ($s as $key => $value) {
        $lines[] = '| ' . $key . ' | ' . $value . ' |';
    }

    $lines[] = '';
    $lines[] = '## Untranslated Template Text';
    $lines[] = '';

    foreach (($report['template_issues'] ?? []) as $issue) {
        if (($issue['type'] ?? '') !== 'template_text_untranslated') {
            continue;
        }
        $lines[] = '- `' . ($issue['text'] ?? '') . '` at `' . ($issue['file'] ?? 'n/a') . ':' . ($issue['line'] ?? 0) . '`';
    }

    return implode(PHP_EOL, $lines);
}
