<?php

declare(strict_types=1);

$rootPath = realpath(__DIR__ . '/../../');
if ($rootPath === false) {
    fwrite(STDERR, "Unable to resolve project root.\n");
    exit(1);
}

$options = parseCliOptions($argv);
$scopes = array_filter(array_map('trim', explode(',', strtolower((string) ($options['scope'] ?? 'admin,user')))));
$format = strtolower((string) ($options['format'] ?? 'json'));
$outputPath = $options['out'] ?? null;
$requestedLocales = array_filter(array_map('trim', explode(',', strtolower((string) ($options['locales'] ?? 'ar,en')))));
$failOnMissingAr = toBool($options['fail-on-missing-ar'] ?? null);
$failOnMissingEn = toBool($options['fail-on-missing-en'] ?? null);
$failOnLiteralUntranslated = toBool($options['fail-on-literal-untranslated'] ?? null);
$failOnArEqualsEn = toBool($options['fail-on-ar-equals-en'] ?? null);
$failOnEnDefaultDrift = toBool($options['fail-on-en-default-drift'] ?? null);

if ($scopes === []) {
    $scopes = ['admin', 'user'];
}

if ($requestedLocales === []) {
    $requestedLocales = ['ar', 'en'];
}

$allowlist = array_values(array_unique(array_map('mb_strtolower', require __DIR__ . '/allowlist_terms.php')));
$excludedPaths = require __DIR__ . '/excluded_paths.php';
$excludedPaths = array_map(static fn ($path) => normalizePath($path), $excludedPaths);

$scopeMap = [
    'admin' => [
        'resources/js/Pages/Admin',
        'app/Http/Controllers/Admin',
    ],
    'user' => [
        'resources/js/Pages/User',
        'app/Http/Controllers/User',
    ],
    'public' => [
        'resources/js/Pages/Frontend',
    ],
    'auth' => [
        'resources/js/Pages/Auth',
        'app/Http/Controllers/AuthController.php',
    ],
    'controllers' => [
        'app/Http/Controllers',
    ],
];

$scopeAliases = [
    'all' => ['admin', 'user', 'public', 'auth', 'controllers'],
];

$expandedScopes = [];
foreach ($scopes as $scope) {
    if (isset($scopeAliases[$scope])) {
        $expandedScopes = array_merge($expandedScopes, $scopeAliases[$scope]);
        continue;
    }
    $expandedScopes[] = $scope;
}
$scopes = array_values(array_unique($expandedScopes));

$scanPaths = [];
foreach ($scopes as $scope) {
    if (isset($scopeMap[$scope])) {
        $scanPaths = array_merge($scanPaths, $scopeMap[$scope]);
    }
}

if (in_array('admin', $scopes, true) || in_array('user', $scopes, true)) {
    $scanPaths[] = 'resources/js/Components';
    $scanPaths[] = 'resources/views/errors';
}

$scanPaths = array_values(array_unique(array_map(static fn ($path) => normalizePath($path), $scanPaths)));

$files = collectFiles($rootPath, $scanPaths, $excludedPaths);
$localePayloads = loadLocalePayloads($rootPath, $requestedLocales);
$defaultEnPayload = loadLocalePayloads($rootPath, ['default_en'])['default_en'] ?? [];

$usedKeys = [];
$keyOccurrences = [];
$literalIssues = [];
$escapedKeyMap = [];
$escapedOccurrences = [];

foreach ($files as $filePath) {
    $content = file_get_contents($filePath);
    if ($content === false) {
        continue;
    }

    $relativePath = normalizePath(str_replace($rootPath . DIRECTORY_SEPARATOR, '', $filePath));

    foreach (extractTranslationKeys($content) as $match) {
        $key = $match['key'];
        if ($key === '' || str_contains($key, '${')) {
            continue;
        }

        $usedKeys[$key] = true;
        $keyOccurrences[$key] ??= [];
        $keyOccurrences[$key][] = [
            'file' => $relativePath,
            'line' => offsetToLine($content, $match['offset']),
            'snippet' => $match['snippet'],
        ];

        if (!($match['escaped'] ?? false)) {
            continue;
        }

        $escapedKeyMap[$key] = true;
        $escapedOccurrences[] = [
            'key' => $key,
            'file' => $relativePath,
            'line' => offsetToLine($content, $match['offset']),
            'snippet' => $match['snippet'],
            'raw_key' => $match['raw_key'] ?? null,
        ];
    }

    foreach (extractLiteralMatches($content) as $literalMatch) {
        $text = trim($literalMatch['text']);
        if ($text === '' || shouldIgnoreLiteral($text)) {
            continue;
        }

        if (containsArabic($text)) {
            continue;
        }

        $normalizedLiteral = mb_strtolower($text);
        $isAllowlisted = in_array($normalizedLiteral, $allowlist, true);

        $literalIssues[] = [
            'type' => $isAllowlisted ? 'literal_allowed_term' : 'literal_untranslated',
            'category' => $literalMatch['category'],
            'text' => $text,
            'file' => $relativePath,
            'line' => offsetToLine($content, $literalMatch['offset']),
            'snippet' => $literalMatch['snippet'],
        ];
    }
}

$usedKeyList = array_keys($usedKeys);
sort($usedKeyList);

$missingByLocale = [];
foreach ($requestedLocales as $locale) {
    $missingByLocale[$locale] = [];

    foreach ($usedKeyList as $key) {
        if (!array_key_exists($key, $localePayloads[$locale])) {
            $firstOccurrence = $keyOccurrences[$key][0] ?? null;
            $missingByLocale[$locale][] = [
                'type' => 'missing_' . $locale . '_key',
                'category' => 'missing_' . $locale . '_key',
                'key' => $key,
                'file' => $firstOccurrence['file'] ?? null,
                'line' => $firstOccurrence['line'] ?? null,
                'snippet' => $firstOccurrence['snippet'] ?? null,
            ];
        }
    }
}

$missingDiagnostics = buildMissingDiagnostics($missingByLocale, $escapedKeyMap);

$arEqualsEnIssues = collectArEqualsEnIssues(
    $usedKeyList,
    $localePayloads['ar'] ?? [],
    $localePayloads['en'] ?? [],
    $keyOccurrences,
    $allowlist
);

$defaultEnDrift = collectDefaultEnDrift(
    $localePayloads['en'] ?? [],
    $defaultEnPayload
);

$issues = [];
foreach ($requestedLocales as $locale) {
    $issues = array_merge($issues, $missingByLocale[$locale] ?? []);
}
$issues = array_values(array_merge(
    $issues,
    array_values(array_filter($literalIssues, static fn ($issue) => $issue['type'] !== 'literal_allowed_term')),
    $arEqualsEnIssues,
    $defaultEnDrift['issues']
));

$report = [
    'generated_at' => date(DATE_ATOM),
    'scope' => $scopes,
    'locales' => $requestedLocales,
    'scan_paths' => $scanPaths,
    'excluded_paths' => $excludedPaths,
    'summary' => [
        'files_scanned' => count($files),
        'used_keys' => count($usedKeyList),
        'missing_in_ar' => count($missingByLocale['ar'] ?? []),
        'missing_in_en' => count($missingByLocale['en'] ?? []),
        'missing_total_unique' => (int) ($missingDiagnostics['missing_total_unique'] ?? 0),
        'missing_escaped_unique' => (int) ($missingDiagnostics['missing_escaped_unique'] ?? 0),
        'literal_untranslated' => count(array_filter($literalIssues, static fn ($issue) => $issue['type'] === 'literal_untranslated')),
        'literal_allowed_term' => count(array_filter($literalIssues, static fn ($issue) => $issue['type'] === 'literal_allowed_term')),
        'ar_equal_en_nontechnical' => count($arEqualsEnIssues),
        'en_default_drift' => count($defaultEnDrift['issues']),
        'escaped_keys_detected' => count($escapedKeyMap),
    ],
    'missing_by_locale' => $missingByLocale,
    'missing_diagnostics' => $missingDiagnostics,
    'literal_issues' => $literalIssues,
    'escaped_keys_detected' => array_keys($escapedKeyMap),
    'escaped_occurrences' => $escapedOccurrences,
    'cross_locale_issues' => $arEqualsEnIssues,
    'default_en_drift' => [
        'missing_in_default_en' => $defaultEnDrift['missing_in_default_en'],
        'extra_in_default_en' => $defaultEnDrift['extra_in_default_en'],
        'value_mismatches' => $defaultEnDrift['value_mismatches'],
    ],
    'issues' => $issues,
    'exit_policy' => [
        'fail_on_missing_ar' => $failOnMissingAr,
        'fail_on_missing_en' => $failOnMissingEn,
        'fail_on_literal_untranslated' => $failOnLiteralUntranslated,
        'fail_on_ar_equals_en' => $failOnArEqualsEn,
        'fail_on_en_default_drift' => $failOnEnDefaultDrift,
    ],
];

$rendered = $format === 'markdown'
    ? renderMarkdownReport($report)
    : json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

if ($rendered === false) {
    fwrite(STDERR, "Failed to render audit report.\n");
    exit(1);
}

if (is_string($outputPath) && $outputPath !== '') {
    $fullOutputPath = isAbsolutePath($outputPath)
        ? $outputPath
        : $rootPath . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $outputPath);

    $outputDir = dirname($fullOutputPath);
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0777, true);
    }

    file_put_contents($fullOutputPath, $rendered);
}

if ($outputPath === null) {
    echo $rendered . PHP_EOL;
}

$hasFailure = false;
if ($failOnMissingAr && ($report['summary']['missing_in_ar'] ?? 0) > 0) {
    $hasFailure = true;
}
if ($failOnMissingEn && ($report['summary']['missing_in_en'] ?? 0) > 0) {
    $hasFailure = true;
}
if ($failOnLiteralUntranslated && ($report['summary']['literal_untranslated'] ?? 0) > 0) {
    $hasFailure = true;
}
if ($failOnArEqualsEn && ($report['summary']['ar_equal_en_nontechnical'] ?? 0) > 0) {
    $hasFailure = true;
}
if ($failOnEnDefaultDrift && ($report['summary']['en_default_drift'] ?? 0) > 0) {
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

    $normalized = strtolower(trim((string) $value));
    return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
}

function normalizePath(string $path): string
{
    return str_replace('\\', '/', $path);
}

function isAbsolutePath(string $path): bool
{
    return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
}

function collectFiles(string $rootPath, array $scanPaths, array $excludedPaths): array
{
    $extensions = ['php', 'vue', 'js', 'ts'];
    $files = [];

    foreach ($scanPaths as $scanPath) {
        $absolutePath = $rootPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $scanPath);
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

function loadLocalePayloads(string $rootPath, array $locales): array
{
    $payloads = [];

    foreach ($locales as $locale) {
        $path = $rootPath . DIRECTORY_SEPARATOR . 'lang' . DIRECTORY_SEPARATOR . $locale . '.json';
        if (!is_file($path)) {
            $payloads[$locale] = [];
            continue;
        }

        $content = file_get_contents($path);
        $decoded = is_string($content) ? json_decode($content, true) : null;
        $payloads[$locale] = is_array($decoded) ? $decoded : [];
    }

    return $payloads;
}

function collectArEqualsEnIssues(array $usedKeys, array $arPayload, array $enPayload, array $keyOccurrences, array $allowlist): array
{
    $issues = [];

    foreach ($usedKeys as $key) {
        if (!array_key_exists($key, $arPayload) || !array_key_exists($key, $enPayload)) {
            continue;
        }

        $arValue = normalizeScalarTranslationValue($arPayload[$key]);
        $enValue = normalizeScalarTranslationValue($enPayload[$key]);

        if ($arValue === null || $enValue === null || $arValue === '' || $enValue === '') {
            continue;
        }

        if (mb_strtolower($arValue) !== mb_strtolower($enValue)) {
            continue;
        }

        if (isSharedArEnValueAllowed($enValue, $allowlist)) {
            continue;
        }

        $firstOccurrence = $keyOccurrences[$key][0] ?? null;
        $issues[] = [
            'type' => 'ar_equals_en_value',
            'category' => 'cross_locale_quality',
            'key' => $key,
            'en_value' => $enValue,
            'ar_value' => $arValue,
            'file' => $firstOccurrence['file'] ?? null,
            'line' => $firstOccurrence['line'] ?? null,
            'snippet' => $firstOccurrence['snippet'] ?? null,
        ];
    }

    return $issues;
}

function collectDefaultEnDrift(array $enPayload, array $defaultEnPayload): array
{
    $missingInDefaultEn = [];
    $extraInDefaultEn = [];
    $valueMismatches = [];
    $issues = [];

    foreach ($enPayload as $key => $enValue) {
        if (!array_key_exists($key, $defaultEnPayload)) {
            $item = [
                'type' => 'missing_default_en_key',
                'category' => 'default_en_drift',
                'key' => $key,
            ];
            $missingInDefaultEn[] = $item;
            $issues[] = $item;
            continue;
        }

        if (!translationValuesMatch($enValue, $defaultEnPayload[$key])) {
            $item = [
                'type' => 'mismatched_default_en_value',
                'category' => 'default_en_drift',
                'key' => $key,
            ];
            $valueMismatches[] = $item;
            $issues[] = $item;
        }
    }

    foreach ($defaultEnPayload as $key => $_value) {
        if (array_key_exists($key, $enPayload)) {
            continue;
        }

        $item = [
            'type' => 'extra_default_en_key',
            'category' => 'default_en_drift',
            'key' => $key,
        ];
        $extraInDefaultEn[] = $item;
        $issues[] = $item;
    }

    return [
        'missing_in_default_en' => $missingInDefaultEn,
        'extra_in_default_en' => $extraInDefaultEn,
        'value_mismatches' => $valueMismatches,
        'issues' => $issues,
    ];
}

function normalizeScalarTranslationValue(mixed $value): ?string
{
    if (is_string($value) || is_numeric($value) || is_bool($value)) {
        return trim((string) $value);
    }

    return null;
}

function isSharedArEnValueAllowed(string $value, array $allowlist): bool
{
    $normalized = normalizeSharedValue($value);
    if ($normalized === '') {
        return true;
    }

    if (in_array($normalized, $allowlist, true)) {
        return true;
    }

    if (shouldIgnoreLiteral($value) || containsArabic($value)) {
        return true;
    }

    if (mb_strlen($value) < 6) {
        return true;
    }

    if (preg_match('/^[A-Z0-9][A-Z0-9 ._()\/-]{1,32}$/u', $value) === 1) {
        return true;
    }

    if (preg_match('/\((?:\.[a-z0-9]{2,6})\)/iu', $value) === 1) {
        return true;
    }

    $tokenized = preg_split('/[^\p{L}\p{N}]+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $technicalTokens = [
        'api', 'sdk', 'oauth', 'smtp', 'http', 'https', 'json', 'html',
        'csv', 'xlsx', 'url', 'webhook', 'webhooks', 'whatsapp', 'openai',
        'aws', 's3', 'otp', 'pin', 'qr', 'utc', 'nodejs', 'python',
        'delete', 'excel', 'cloud',
    ];

    if ($tokenized !== [] && count(array_diff($tokenized, $technicalTokens)) === 0) {
        return true;
    }

    return false;
}

function normalizeSharedValue(string $value): string
{
    return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value) ?? $value));
}

function translationValuesMatch(mixed $left, mixed $right): bool
{
    $leftNormalized = normalizeTranslationValueForDrift($left);
    $rightNormalized = normalizeTranslationValueForDrift($right);

    return $leftNormalized === $rightNormalized;
}

function normalizeTranslationValueForDrift(mixed $value): string
{
    if (is_string($value) || is_numeric($value) || is_bool($value) || $value === null) {
        return (string) $value;
    }

    $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return $encoded === false ? serialize($value) : $encoded;
}

function buildMissingDiagnostics(array $missingByLocale, array $escapedKeyMap): array
{
    $uniqueMissing = [];
    $uniqueMissingEscaped = [];
    $missingByFile = [];
    $missingBySurface = [];

    foreach ($missingByLocale as $locale => $issues) {
        foreach ($issues as $issue) {
            $key = (string) ($issue['key'] ?? '');
            if ($key === '') {
                continue;
            }

            $uniqueMissing[$key] = true;
            if (isset($escapedKeyMap[$key])) {
                $uniqueMissingEscaped[$key] = true;
            }

            $file = (string) ($issue['file'] ?? 'unknown');
            $fileKey = $file . '|' . $key;
            if (isset($missingByFile[$fileKey])) {
                continue;
            }

            $missingByFile[$fileKey] = [
                'file' => $file,
                'surface' => resolveSurfaceForPath($file),
            ];
        }
    }

    $countByFile = [];
    foreach ($missingByFile as $item) {
        $file = $item['file'];
        $surface = $item['surface'];
        $countByFile[$file] = ($countByFile[$file] ?? 0) + 1;
        $missingBySurface[$surface] = ($missingBySurface[$surface] ?? 0) + 1;
    }

    arsort($countByFile);
    arsort($missingBySurface);
    $uniqueMissingKeys = array_keys($uniqueMissing);
    sort($uniqueMissingKeys);
    $uniqueMissingEscapedKeys = array_keys($uniqueMissingEscaped);
    sort($uniqueMissingEscapedKeys);

    return [
        'missing_total_unique' => count($uniqueMissingKeys),
        'missing_escaped_unique' => count($uniqueMissingEscapedKeys),
        'missing_by_file' => $countByFile,
        'missing_by_surface' => $missingBySurface,
        'missing_sample' => array_slice($uniqueMissingKeys, 0, 50),
        'missing_escaped_sample' => array_slice($uniqueMissingEscapedKeys, 0, 50),
    ];
}

function resolveSurfaceForPath(string $filePath): string
{
    $path = normalizePath($filePath);

    if (str_starts_with($path, 'resources/js/Pages/Auth/')) {
        return 'auth';
    }
    if (str_starts_with($path, 'resources/js/Pages/Frontend/')) {
        return 'public';
    }
    if (str_starts_with($path, 'resources/js/Pages/Admin/')) {
        return 'admin';
    }
    if (str_starts_with($path, 'resources/js/Pages/User/')) {
        return 'user';
    }
    if (str_starts_with($path, 'resources/js/Components/')) {
        return 'components';
    }
    if (str_starts_with($path, 'app/Http/Controllers/')) {
        return 'controllers';
    }

    return 'other';
}

function extractTranslationKeys(string $content): array
{
    $matches = [];
    $patterns = [
        '/(?:\$t|trans|__|@lang)\(\s*([\'"])(?<key>(?:\\\\.|(?!\1).)*)\1\s*[\),]/u',
    ];

    foreach ($patterns as $pattern) {
        if (!preg_match_all($pattern, $content, $found, PREG_OFFSET_CAPTURE)) {
            continue;
        }

        foreach ($found['key'] as $index => $capture) {
            $rawKey = (string) $capture[0];
            $normalizedKey = stripcslashes($rawKey);
            $matches[] = [
                'key' => trim($normalizedKey),
                'raw_key' => $rawKey,
                'escaped' => preg_match('/\\\\[\'"]/', $rawKey) === 1,
                'offset' => (int) $capture[1],
                'snippet' => trim((string) ($found[0][$index][0] ?? '')),
            ];
        }
    }

    return $matches;
}

function extractLiteralMatches(string $content): array
{
    $matches = [];

    $objectPattern = '/\b(label|placeholder|title|subtitle|description|helperText|emptyText|buttonText|confirmText|cancelText)\b\s*:\s*([\'"])(.+?)\2/u';
    if (preg_match_all($objectPattern, $content, $found, PREG_OFFSET_CAPTURE)) {
        foreach ($found[3] as $index => $capture) {
            $matches[] = [
                'category' => (string) ($found[1][$index][0] ?? 'literal_untranslated'),
                'text' => (string) $capture[0],
                'offset' => (int) $capture[1],
                'snippet' => trim((string) ($found[0][$index][0] ?? '')),
            ];
        }
    }

    // Match only exact static attributes, skip Vue bound/compound props like :label / :save-state-label.
    $attributePattern = '/(?<![:@\w-])(placeholder|title|label|aria-label|alt)\s*=\s*([\'"])([^\'"]+)\2/u';
    if (preg_match_all($attributePattern, $content, $found, PREG_OFFSET_CAPTURE)) {
        foreach ($found[3] as $index => $capture) {
            $matches[] = [
                'category' => (string) ($found[1][$index][0] ?? 'literal_untranslated'),
                'text' => (string) $capture[0],
                'offset' => (int) $capture[1],
                'snippet' => trim((string) ($found[0][$index][0] ?? '')),
            ];
        }
    }

    $optionPattern = '/<option\b[^>]*>([^<{}][^<]*)<\/option>/u';
    if (preg_match_all($optionPattern, $content, $found, PREG_OFFSET_CAPTURE)) {
        foreach ($found[1] as $index => $capture) {
            $matches[] = [
                'category' => 'option_text',
                'text' => (string) $capture[0],
                'offset' => (int) $capture[1],
                'snippet' => trim((string) ($found[0][$index][0] ?? '')),
            ];
        }
    }

    $dialogPattern = '/\b(alert|confirm|prompt)\(\s*([\'"])([^\'"]+)\2\s*[\),]/u';
    if (preg_match_all($dialogPattern, $content, $found, PREG_OFFSET_CAPTURE)) {
        foreach ($found[3] as $index => $capture) {
            $matches[] = [
                'category' => (string) (($found[1][$index][0] ?? 'dialog') . '_text'),
                'text' => (string) $capture[0],
                'offset' => (int) $capture[1],
                'snippet' => trim((string) ($found[0][$index][0] ?? '')),
            ];
        }
    }

    return $matches;
}

function shouldIgnoreLiteral(string $text): bool
{
    if ($text === '' || preg_match('/^[\d\W_]+$/u', $text) === 1) {
        return true;
    }

    if (str_contains($text, '{{') || str_contains($text, '}}')) {
        return true;
    }

    if (preg_match('/^\$t\(/u', $text) === 1 || preg_match('/^(trans|__)\(/u', $text) === 1) {
        return true;
    }

    // Skip technical slugs and identifiers.
    if (preg_match('/^[a-z0-9._:-]+$/u', $text) === 1) {
        return true;
    }

    return false;
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

function renderMarkdownReport(array $report): string
{
    $summary = $report['summary'];
    $lines = [];
    $lines[] = '# I18n Audit Report';
    $lines[] = '';
    $lines[] = '- Generated: ' . ($report['generated_at'] ?? '');
    $lines[] = '- Scope: ' . implode(', ', $report['scope'] ?? []);
    $lines[] = '- Locales: ' . implode(', ', $report['locales'] ?? []);
    $lines[] = '';
    $lines[] = '## Summary';
    $lines[] = '';
    $lines[] = '| Metric | Value |';
    $lines[] = '| --- | ---: |';
    foreach ($summary as $key => $value) {
        $lines[] = '| ' . $key . ' | ' . $value . ' |';
    }
    $lines[] = '';
    $lines[] = '## Missing Arabic Keys';
    $lines[] = '';

    foreach (($report['missing_by_locale']['ar'] ?? []) as $issue) {
        $location = ($issue['file'] ?? 'n/a') . ':' . ($issue['line'] ?? 0);
        $lines[] = '- `' . $issue['key'] . '` at `' . $location . '`';
    }

    $lines[] = '';
    $lines[] = '## Missing English Keys';
    $lines[] = '';

    foreach (($report['missing_by_locale']['en'] ?? []) as $issue) {
        $location = ($issue['file'] ?? 'n/a') . ':' . ($issue['line'] ?? 0);
        $lines[] = '- `' . $issue['key'] . '` at `' . $location . '`';
    }

    $lines[] = '';
    $lines[] = '## Missing Diagnostics';
    $lines[] = '';
    $lines[] = '- Missing total (unique keys): ' . (int) ($report['missing_diagnostics']['missing_total_unique'] ?? 0);
    $lines[] = '- Missing escaped (unique keys): ' . (int) ($report['missing_diagnostics']['missing_escaped_unique'] ?? 0);
    $lines[] = '';
    $lines[] = '### Top Surfaces';
    $lines[] = '';

    foreach (($report['missing_diagnostics']['missing_by_surface'] ?? []) as $surface => $count) {
        $lines[] = '- `' . $surface . '` => ' . $count;
    }

    $lines[] = '';
    $lines[] = '## Cross-Locale Quality (AR=EN)';
    $lines[] = '';

    foreach (($report['cross_locale_issues'] ?? []) as $issue) {
        $location = ($issue['file'] ?? 'n/a') . ':' . ($issue['line'] ?? 0);
        $lines[] = '- `' . ($issue['key'] ?? 'n/a') . '` at `' . $location . '`';
    }

    $lines[] = '';
    $lines[] = '## EN / default_en Drift';
    $lines[] = '';
    $lines[] = '- Missing in default_en: ' . count($report['default_en_drift']['missing_in_default_en'] ?? []);
    $lines[] = '- Extra in default_en: ' . count($report['default_en_drift']['extra_in_default_en'] ?? []);
    $lines[] = '- Value mismatches: ' . count($report['default_en_drift']['value_mismatches'] ?? []);

    $lines[] = '';
    $lines[] = '## Literal Untranslated';
    $lines[] = '';

    foreach (($report['literal_issues'] ?? []) as $issue) {
        if (($issue['type'] ?? '') !== 'literal_untranslated') {
            continue;
        }
        $location = ($issue['file'] ?? 'n/a') . ':' . ($issue['line'] ?? 0);
        $lines[] = '- `' . $issue['text'] . '` at `' . $location . '`';
    }

    return implode(PHP_EOL, $lines);
}
