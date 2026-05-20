<?php

declare(strict_types=1);

/**
 * Wrap likely user-facing raw PHP strings with __() safely.
 *
 * Targets:
 * - 'message' => '...'
 * - 'error' => '...'
 * - similar UI-facing keys
 * - abort(code, '...')
 * - throw new Exception('...')
 *
 * Usage:
 *   php scripts/i18n/wrap-php-user-facing-strings.php --format=json --out=tmp/wrap-php-i18n.json
 */

$root = realpath(__DIR__.'/../../');
if ($root === false) {
    fwrite(STDERR, "Unable to resolve project root.\n");
    exit(1);
}

$options = parseCliOptions($argv);
$format = strtolower((string) ($options['format'] ?? 'json'));
$out = isset($options['out']) ? resolvePath($root, (string) $options['out']) : null;

$tracked = listTrackedPhpFiles($root);
$changes = [];
$changedFiles = 0;

foreach ($tracked as $relativeFile) {
    $absolute = $root.'/'.$relativeFile;
    $original = @file_get_contents($absolute);
    if (!is_string($original) || $original === '') {
        continue;
    }

    $content = $original;
    $fileChanges = 0;

    // Key/value message-like entries.
    $content = preg_replace_callback(
        '/([\'"](?:message|error|errors|warning|success|info|title|label|description|placeholder)[\'"]\s*=>\s*)([\'"])((?:\\\\.|(?!\2).)+)\2/u',
        static function (array $m) use ($relativeFile, &$changes, &$fileChanges): string {
            $prefix = (string) ($m[1] ?? '');
            $raw = stripcslashes((string) ($m[3] ?? ''));
            if (!isLikelyHumanText($raw)) {
                return (string) $m[0];
            }

            $wrapped = "__('".escapeSingleQuotedPhpString($raw)."')";
            $fileChanges++;
            $changes[] = [
                'file' => $relativeFile,
                'kind' => 'key_value',
                'original' => $raw,
            ];

            return $prefix.$wrapped;
        },
        $content
    ) ?? $content;

    // abort(status, 'message')
    $content = preg_replace_callback(
        '/abort\(\s*(\d+)\s*,\s*([\'"])((?:\\\\.|(?!\2).)+)\2\s*\)/u',
        static function (array $m) use ($relativeFile, &$changes, &$fileChanges): string {
            $status = (string) ($m[1] ?? '');
            $raw = stripcslashes((string) ($m[3] ?? ''));
            if (!isLikelyHumanText($raw)) {
                return (string) $m[0];
            }

            $fileChanges++;
            $changes[] = [
                'file' => $relativeFile,
                'kind' => 'abort',
                'original' => $raw,
            ];

            return "abort({$status}, __('".escapeSingleQuotedPhpString($raw)."'))";
        },
        $content
    ) ?? $content;

    // throw new Xxx('message')
    $content = preg_replace_callback(
        '/throw\s+new\s+([A-Za-z_\\\\][A-Za-z0-9_\\\\]*)\s*\(\s*([\'"])((?:\\\\.|(?!\2).)+)\2\s*\)/u',
        static function (array $m) use ($relativeFile, &$changes, &$fileChanges): string {
            $class = (string) ($m[1] ?? '');
            $raw = stripcslashes((string) ($m[3] ?? ''));
            if (!isLikelyHumanText($raw)) {
                return (string) $m[0];
            }

            $fileChanges++;
            $changes[] = [
                'file' => $relativeFile,
                'kind' => 'throw',
                'original' => $raw,
            ];

            return "throw new {$class}(__('".escapeSingleQuotedPhpString($raw)."'))";
        },
        $content
    ) ?? $content;

    if ($content !== $original) {
        file_put_contents($absolute, $content);
        $changedFiles++;
    }
}

$report = [
    'generated_at' => date(DATE_ATOM),
    'changed_files' => $changedFiles,
    'total_replacements' => count($changes),
    'changes' => $changes,
];

$rendered = $format === 'markdown'
    ? renderMarkdown($report)
    : json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

if ($rendered === false) {
    fwrite(STDERR, "Failed to render report.\n");
    exit(1);
}

if ($out !== null) {
    $dir = dirname($out);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
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
            [$k, $v] = explode('=', $arg, 2);
            $options[$k] = $v;
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

/**
 * @return array<int, string>
 */
function listTrackedPhpFiles(string $root): array
{
    $output = [];
    $code = 0;
    exec('git -C '.escapeshellarg($root).' ls-files', $output, $code);
    if ($code !== 0) {
        return [];
    }

    $files = [];
    foreach ($output as $line) {
        $line = str_replace('\\', '/', trim((string) $line));
        if ($line === '' || !str_ends_with($line, '.php')) {
            continue;
        }
        if (
            str_starts_with($line, 'vendor/')
            || str_starts_with($line, 'node_modules/')
            || str_starts_with($line, 'public/build/')
            || str_starts_with($line, 'public/prebuilt-build/')
            || str_starts_with($line, 'docs/')
        ) {
            continue;
        }
        $files[] = $line;
    }

    sort($files);
    return $files;
}

function isLikelyHumanText(string $text): bool
{
    $text = trim($text);
    if ($text === '' || mb_strlen($text) < 2) {
        return false;
    }
    if (!preg_match('/[\p{L}]/u', $text)) {
        return false;
    }
    if (preg_match('/^[a-z0-9_.:\\/{}-]+$/iu', $text) === 1) {
        return false;
    }

    return true;
}

function escapeSingleQuotedPhpString(string $value): string
{
    $value = str_replace('\\', '\\\\', $value);
    return str_replace("'", "\\'", $value);
}

function renderMarkdown(array $report): string
{
    $lines = [];
    $lines[] = '# PHP User-Facing Wrap Report';
    $lines[] = '';
    $lines[] = '- changed_files: '.(int) ($report['changed_files'] ?? 0);
    $lines[] = '- total_replacements: '.(int) ($report['total_replacements'] ?? 0);
    $lines[] = '';

    foreach (($report['changes'] ?? []) as $row) {
        $lines[] = sprintf(
            '- [%s] %s: `%s`',
            (string) ($row['kind'] ?? ''),
            (string) ($row['file'] ?? ''),
            (string) ($row['original'] ?? '')
        );
    }

    return implode(PHP_EOL, $lines).PHP_EOL;
}
