<?php

declare(strict_types=1);

/**
 * Deep i18n audit that does not depend on existing project audit scripts.
 *
 * Outputs:
 * - missing translation keys used via __()/trans()/$t()
 * - plain text candidates that are likely user-facing and not wrapped in translation helpers
 *
 * Usage:
 *   php scripts/i18n/deep-untranslated-audit.php --format=json --out=tmp/deep-i18n-audit.json
 */

$root = realpath(__DIR__.'/../../');
if ($root === false) {
    fwrite(STDERR, "Unable to resolve project root.\n");
    exit(1);
}

$options = parseCliOptions($argv);
$format = strtolower((string) ($options['format'] ?? 'json'));
$out = isset($options['out']) ? resolvePath($root, (string) $options['out']) : null;
$includePlain = toBool($options['include-plain'] ?? '1');

$langAr = loadJsonMap($root.'/lang/ar.json');
$langEn = loadJsonMap($root.'/lang/en.json');

$trackedFiles = listTrackedFiles($root);

$excludePrefixes = [
    'vendor/',
    'node_modules/',
    'public/build/',
    'public/prebuilt-build/',
    '_archive/',
    'docs/',
    'storage/',
    'bootstrap/cache/',
];

$scannableExtensions = ['php', 'vue', 'js', 'ts'];

$files = [];
foreach ($trackedFiles as $file) {
    $normalized = normalizePath($file);
    if (str_contains($normalized, '/.git/')) {
        continue;
    }

    $excluded = false;
    foreach ($excludePrefixes as $prefix) {
        if (str_starts_with($normalized, $prefix)) {
            $excluded = true;
            break;
        }
    }
    if ($excluded) {
        continue;
    }

    $ext = strtolower(pathinfo($normalized, PATHINFO_EXTENSION));
    if ($ext === 'blade.php' || str_ends_with($normalized, '.blade.php')) {
        $ext = 'blade.php';
    }

    if (!in_array($ext, $scannableExtensions, true) && $ext !== 'blade.php') {
        continue;
    }

    $files[] = $normalized;
}

sort($files);

$missingTranslationKeys = [];
$plainTextCandidates = [];
$translationCallsTotal = 0;

foreach ($files as $relativeFile) {
    $absolute = $root.'/'.$relativeFile;
    $content = @file_get_contents($absolute);
    if (!is_string($content)) {
        continue;
    }

    $translationCalls = scanTranslationCalls($content, $relativeFile);
    $translationCallsTotal += count($translationCalls);

    foreach ($translationCalls as $call) {
        $key = trim((string) $call['key']);
        if ($key === '') {
            continue;
        }

        $missing = [];
        if (!array_key_exists($key, $langAr)) {
            $missing[] = 'ar';
        }
        if (!array_key_exists($key, $langEn)) {
            $missing[] = 'en';
        }

        if ($missing === []) {
            continue;
        }

        $missingTranslationKeys[] = [
            'key' => $key,
            'file' => $relativeFile,
            'line' => $call['line'],
            'source' => $call['source'],
            'missing_locales' => $missing,
        ];
    }

    if ($includePlain) {
        foreach (scanPlainTextCandidates($content, $relativeFile) as $candidate) {
            $text = trim((string) $candidate['text']);
            if ($text === '') {
                continue;
            }

            $plainTextCandidates[] = [
                'text' => $text,
                'file' => $relativeFile,
                'line' => $candidate['line'],
                'category' => $candidate['category'],
                'has_ar_translation' => array_key_exists($text, $langAr),
                'has_en_translation' => array_key_exists($text, $langEn),
            ];
        }
    }
}

$missingTranslationKeys = dedupeRecords($missingTranslationKeys, static function (array $row): string {
    return implode('|', [
        $row['key'] ?? '',
        $row['file'] ?? '',
        (string) ($row['line'] ?? ''),
        implode(',', (array) ($row['missing_locales'] ?? [])),
    ]);
});

$plainTextCandidates = dedupeRecords($plainTextCandidates, static function (array $row): string {
    return implode('|', [
        $row['text'] ?? '',
        $row['file'] ?? '',
        (string) ($row['line'] ?? ''),
        $row['category'] ?? '',
    ]);
});

$missingUniqueByText = [];
foreach ($missingTranslationKeys as $row) {
    $missingUniqueByText[(string) $row['key']] = true;
}
foreach ($plainTextCandidates as $row) {
    if (($row['has_ar_translation'] ?? false) === false || ($row['has_en_translation'] ?? false) === false) {
        $missingUniqueByText[(string) $row['text']] = true;
    }
}

$report = [
    'generated_at' => date(DATE_ATOM),
    'scope' => [
        'files_scanned' => count($files),
        'extensions' => $scannableExtensions,
        'include_plain_candidates' => $includePlain,
    ],
    'summary' => [
        'translation_calls_total' => $translationCallsTotal,
        'missing_translation_key_occurrences' => count($missingTranslationKeys),
        'plain_text_candidate_occurrences' => count($plainTextCandidates),
        'unique_strings_missing_any_locale' => count($missingUniqueByText),
    ],
    'missing_translation_keys' => $missingTranslationKeys,
    'plain_text_candidates' => $plainTextCandidates,
];

$rendered = $format === 'markdown'
    ? renderMarkdown($report)
    : json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

if ($rendered === false) {
    fwrite(STDERR, "Failed to render i18n audit report.\n");
    exit(1);
}

if ($out !== null) {
    $outDir = dirname($out);
    if (!is_dir($outDir)) {
        mkdir($outDir, 0777, true);
    }
    file_put_contents($out, $rendered);
} else {
    echo $rendered.PHP_EOL;
}

exit(0);

function parseCliOptions(array $argv): array
{
    $options = [];
    foreach (array_slice($argv, 1) as $arg) {
        if (!str_starts_with($arg, '--')) {
            continue;
        }

        $arg = substr($arg, 2);
        if (str_contains($arg, '=')) {
            [$key, $value] = explode('=', $arg, 2);
            $options[$key] = $value;
        } else {
            $options[$arg] = '1';
        }
    }

    return $options;
}

function resolvePath(string $root, string $path): string
{
    if ($path === '') {
        return $path;
    }

    if ($path[0] === '/' || preg_match('/^[a-zA-Z]:[\\\\\\/]/', $path) === 1) {
        return $path;
    }

    return $root.'/'.$path;
}

function normalizePath(string $path): string
{
    return str_replace('\\', '/', $path);
}

function toBool(mixed $value): bool
{
    $normalized = strtolower(trim((string) $value));
    return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
}

/**
 * @return array<string, string>
 */
function loadJsonMap(string $path): array
{
    if (!is_file($path)) {
        return [];
    }

    $raw = file_get_contents($path);
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }

    $json = json_decode($raw, true);
    if (!is_array($json)) {
        return [];
    }

    $result = [];
    foreach ($json as $key => $value) {
        if (is_string($key) && is_string($value)) {
            $result[$key] = $value;
        }
    }

    return $result;
}

/**
 * @return array<int, string>
 */
function listTrackedFiles(string $root): array
{
    $cmd = 'git -C '.escapeshellarg($root).' ls-files';
    $output = [];
    $code = 0;
    exec($cmd, $output, $code);

    if ($code !== 0) {
        return [];
    }

    return array_values(array_filter(array_map('trim', $output), static fn ($line) => $line !== ''));
}

/**
 * @return array<int, array{key:string,line:int,source:string}>
 */
function scanTranslationCalls(string $content, string $file): array
{
    $matches = [];

    $patterns = [
        '/__\(\s*([\'"])(?<key>(?:\\\\.|(?!\1).)+)\1/um' => '__',
        '/trans\(\s*([\'"])(?<key>(?:\\\\.|(?!\1).)+)\1/um' => 'trans',
        '/\$t\(\s*([\'"])(?<key>(?:\\\\.|(?!\1).)+)\1/um' => '$t',
        '/\$tc\(\s*([\'"])(?<key>(?:\\\\.|(?!\1).)+)\1/um' => '$tc',
    ];

    foreach ($patterns as $regex => $source) {
        if (!preg_match_all($regex, $content, $found, PREG_OFFSET_CAPTURE)) {
            continue;
        }

        foreach (($found['key'] ?? []) as $capture) {
            $key = stripcslashes((string) $capture[0]);
            $offset = (int) $capture[1];
            $line = offsetToLine($content, $offset);

            if (!isLikelyHumanText($key)) {
                continue;
            }

            $matches[] = [
                'key' => trim($key),
                'line' => $line,
                'source' => $source,
            ];
        }
    }

    return $matches;
}

/**
 * @return array<int, array{text:string,line:int,category:string}>
 */
function scanPlainTextCandidates(string $content, string $file): array
{
    $results = [];
    $lines = preg_split("/\r\n|\n|\r/", $content) ?: [];
    $normalizedFile = normalizePath($file);
    $ext = strtolower(pathinfo($normalizedFile, PATHINFO_EXTENSION));
    $isVueLike = str_ends_with($normalizedFile, '.vue') || str_ends_with($normalizedFile, '.blade.php');
    $isJsLike = in_array($ext, ['js', 'ts'], true);
    $isPhpLike = str_ends_with($normalizedFile, '.php') && !$isVueLike;

    foreach ($lines as $idx => $line) {
        $lineNo = $idx + 1;
        $trimmed = trim($line);

        if ($trimmed === '') {
            continue;
        }

        if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*') || str_starts_with($trimmed, '/*')) {
            continue;
        }

        if (str_contains($line, '__(') || str_contains($line, 'trans(') || str_contains($line, '$t(') || str_contains($line, '$tc(')) {
            continue;
        }

        $candidates = [];

        if ($isPhpLike) {
            // Backend key/value user-facing candidates (message, error, title, label, etc.)
            if (preg_match_all('/[\'"](?<k>message|error|errors|warning|success|info|title|label|description|placeholder)[\'"]\s*=>\s*([\'"])(?<v>(?:\\\\.|(?!\2).)+)\2/ui', $line, $m1)) {
                foreach ($m1['v'] as $text) {
                    $candidates[] = ['text' => stripcslashes((string) $text), 'category' => 'php_key_value'];
                }
            }

            if (preg_match_all('/abort\(\s*\d+\s*,\s*([\'"])(?<v>(?:\\\\.|(?!\1).)+)\1/ui', $line, $m2)) {
                foreach ($m2['v'] as $text) {
                    $candidates[] = ['text' => stripcslashes((string) $text), 'category' => 'php_abort'];
                }
            }

            if (preg_match_all('/throw\s+new\s+[A-Za-z_\\\\][A-Za-z0-9_\\\\]*\s*\(\s*([\'"])(?<v>(?:\\\\.|(?!\1).)+)\1/um', $line, $m3)) {
                foreach ($m3['v'] as $text) {
                    $candidates[] = ['text' => stripcslashes((string) $text), 'category' => 'php_exception'];
                }
            }
        }

        if ($isVueLike) {
            // Vue/Blade template plain text: >Text<
            if (preg_match_all('/>(?<v>[^<>{}{]{2,})</u', $line, $m4)) {
                foreach ($m4['v'] as $text) {
                    $candidates[] = ['text' => html_entity_decode(trim((string) $text), ENT_QUOTES | ENT_HTML5), 'category' => 'vue_text_node'];
                }
            }

            // HTML attributes likely visible to users
            if (preg_match_all('/(?:title|placeholder|aria-label|label)\s*=\s*"(?<v>[^"]+)"/u', $line, $m5)) {
                foreach ($m5['v'] as $text) {
                    $candidates[] = ['text' => trim((string) $text), 'category' => 'template_attr'];
                }
            }
        }

        if ($isJsLike || $isVueLike) {
            // JS object props likely visible to users
            if (preg_match_all('/(?:label|title|message|description|text)\s*:\s*([\'"])(?<v>(?:\\\\.|(?!\1).)+)\1/u', $line, $m6)) {
                foreach ($m6['v'] as $text) {
                    $candidates[] = ['text' => stripcslashes((string) $text), 'category' => 'js_object_prop'];
                }
            }
        }

        foreach ($candidates as $candidate) {
            $text = normalizeCandidateText((string) ($candidate['text'] ?? ''));
            if (!isLikelyHumanText($text)) {
                continue;
            }

            $results[] = [
                'text' => $text,
                'line' => $lineNo,
                'category' => (string) ($candidate['category'] ?? 'unknown'),
            ];
        }
    }

    return $results;
}

function normalizeCandidateText(string $text): string
{
    $text = preg_replace('/\s+/u', ' ', trim($text)) ?? trim($text);
    return trim($text);
}

function isLikelyHumanText(string $text): bool
{
    $text = trim($text);
    if ($text === '') {
        return false;
    }

    if (mb_strlen($text) < 2) {
        return false;
    }

    if (!preg_match('/[\p{L}]/u', $text)) {
        return false;
    }

    // Ignore technical tokens / keys / routes / slugs
    if (preg_match('/^[a-z0-9_.:\\/{}-]+$/iu', $text) === 1) {
        return false;
    }

    if (preg_match('/^(GET|POST|PUT|PATCH|DELETE|HEAD|OPTIONS)$/i', $text) === 1) {
        return false;
    }

    return true;
}

function offsetToLine(string $content, int $offset): int
{
    if ($offset <= 0) {
        return 1;
    }

    return substr_count(substr($content, 0, $offset), "\n") + 1;
}

/**
 * @template T
 * @param  array<int, T>  $rows
 * @param  callable(T): string  $keyResolver
 * @return array<int, T>
 */
function dedupeRecords(array $rows, callable $keyResolver): array
{
    $seen = [];
    $result = [];

    foreach ($rows as $row) {
        $key = $keyResolver($row);
        if ($key === '') {
            continue;
        }
        if (isset($seen[$key])) {
            continue;
        }

        $seen[$key] = true;
        $result[] = $row;
    }

    return $result;
}

function renderMarkdown(array $report): string
{
    $lines = [];
    $lines[] = '# Deep Untranslated Audit';
    $lines[] = '';
    $lines[] = 'Generated at: '.$report['generated_at'];
    $lines[] = '';
    $lines[] = '## Summary';
    foreach (($report['summary'] ?? []) as $key => $value) {
        $lines[] = '- '.$key.': '.$value;
    }
    $lines[] = '';
    $lines[] = '## Missing Translation Keys';
    foreach (($report['missing_translation_keys'] ?? []) as $row) {
        $lines[] = sprintf(
            '- `%s` (%s) at %s:%d',
            (string) ($row['key'] ?? ''),
            implode(',', (array) ($row['missing_locales'] ?? [])),
            (string) ($row['file'] ?? ''),
            (int) ($row['line'] ?? 0)
        );
    }
    $lines[] = '';
    $lines[] = '## Plain Text Candidates';
    foreach (($report['plain_text_candidates'] ?? []) as $row) {
        $lines[] = sprintf(
            '- `%s` [%s] at %s:%d',
            (string) ($row['text'] ?? ''),
            (string) ($row['category'] ?? ''),
            (string) ($row['file'] ?? ''),
            (int) ($row['line'] ?? 0)
        );
    }

    return implode(PHP_EOL, $lines).PHP_EOL;
}
