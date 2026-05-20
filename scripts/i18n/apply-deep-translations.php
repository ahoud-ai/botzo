<?php

declare(strict_types=1);

/**
 * Apply translations for missing strings discovered by deep-untranslated-audit.php.
 *
 * Usage:
 *   php scripts/i18n/apply-deep-translations.php --report=tmp/deep-i18n-audit.json --format=json --out=tmp/deep-i18n-apply.json
 */

$root = realpath(__DIR__.'/../../');
if ($root === false) {
    fwrite(STDERR, "Unable to resolve project root.\n");
    exit(1);
}

$options = parseCliOptions($argv);
$reportPath = resolvePath($root, (string) ($options['report'] ?? 'tmp/deep-i18n-audit.json'));
$format = strtolower((string) ($options['format'] ?? 'json'));
$out = isset($options['out']) ? resolvePath($root, (string) $options['out']) : null;

if (!is_file($reportPath)) {
    fwrite(STDERR, "Report file not found: {$reportPath}\n");
    exit(1);
}

$reportRaw = file_get_contents($reportPath);
$report = is_string($reportRaw) ? json_decode($reportRaw, true) : null;
if (!is_array($report)) {
    fwrite(STDERR, "Invalid report JSON: {$reportPath}\n");
    exit(1);
}

$arPath = $root.'/lang/ar.json';
$enPath = $root.'/lang/en.json';
$defaultEnPath = $root.'/lang/default_en.json';

$ar = loadJsonMap($arPath);
$en = loadJsonMap($enPath);
$defaultEn = loadJsonMap($defaultEnPath);

$targets = [];

foreach ((array) ($report['missing_translation_keys'] ?? []) as $row) {
    $key = trim((string) ($row['key'] ?? ''));
    if ($key !== '') {
        $targets[$key] = true;
    }
}

foreach ((array) ($report['plain_text_candidates'] ?? []) as $row) {
    $text = trim((string) ($row['text'] ?? ''));
    if ($text === '') {
        continue;
    }

    // Keep all human-facing candidates, including frontend strings.
    if (isLikelyHumanText($text)) {
        $targets[$text] = true;
    }
}

$strings = array_keys($targets);
sort($strings);

$addedAr = [];
$addedEn = [];
$addedDefaultEn = [];

foreach ($strings as $sourceText) {
    if (!array_key_exists($sourceText, $en)) {
        $en[$sourceText] = normalizeEnglishValue($sourceText);
        $addedEn[$sourceText] = $en[$sourceText];
    }

    if (!array_key_exists($sourceText, $defaultEn)) {
        $defaultEn[$sourceText] = normalizeEnglishValue($sourceText);
        $addedDefaultEn[$sourceText] = $defaultEn[$sourceText];
    }

    if (!array_key_exists($sourceText, $ar)) {
        $arTranslation = translateToArabic($sourceText);
        $ar[$sourceText] = $arTranslation;
        $addedAr[$sourceText] = $arTranslation;
    }
}

writeJsonMap($arPath, $ar);
writeJsonMap($enPath, $en);
writeJsonMap($defaultEnPath, $defaultEn);

$result = [
    'generated_at' => date(DATE_ATOM),
    'source_report' => normalizePath((string) str_replace($root.DIRECTORY_SEPARATOR, '', $reportPath)),
    'strings_considered' => count($strings),
    'added' => [
        'ar' => count($addedAr),
        'en' => count($addedEn),
        'default_en' => count($addedDefaultEn),
    ],
    'added_samples' => [
        'ar' => array_slice($addedAr, 0, 40, true),
        'en' => array_slice($addedEn, 0, 40, true),
        'default_en' => array_slice($addedDefaultEn, 0, 40, true),
    ],
];

$rendered = $format === 'markdown'
    ? renderMarkdown($result)
    : json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

if ($rendered === false) {
    fwrite(STDERR, "Failed to render apply report.\n");
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

function normalizePath(string $path): string
{
    return str_replace('\\', '/', $path);
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
    $data = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($data)) {
        return [];
    }

    $map = [];
    foreach ($data as $k => $v) {
        if (is_string($k) && is_string($v)) {
            $map[$k] = $v;
        }
    }

    return $map;
}

/**
 * @param  array<string, string>  $map
 */
function writeJsonMap(string $path, array $map): void
{
    $json = json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException("Failed to encode JSON for {$path}");
    }
    file_put_contents($path, $json.PHP_EOL);
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

function normalizeEnglishValue(string $text): string
{
    return $text;
}

function translateToArabic(string $source): string
{
    // Preserve placeholders and template slots.
    $protected = [];
    $normalized = $source;
    $patterns = [
        '/:\w+/u',
        '/\{\{[^}]+\}\}/u',
        '/\{\$[^}]+\}/u',
        '/\$\{[^}]+\}/u',
        '/%[0-9]*[bcdeEfFgGosuxX]/u',
    ];

    foreach ($patterns as $pattern) {
        $normalized = preg_replace_callback($pattern, static function (array $m) use (&$protected): string {
            $token = '__PH_'.count($protected).'__';
            $protected[$token] = $m[0];
            return $token;
        }, $normalized) ?? $normalized;
    }

    $translated = googleTranslate($normalized, 'ar');
    if ($translated === null || trim($translated) === '') {
        $translated = $source;
    }

    foreach ($protected as $token => $value) {
        $translated = str_replace($token, $value, $translated);
    }

    return trim($translated) !== '' ? $translated : $source;
}

function googleTranslate(string $text, string $targetLanguage): ?string
{
    $query = http_build_query([
        'client' => 'gtx',
        'sl' => 'auto',
        'tl' => $targetLanguage,
        'dt' => 't',
        'q' => $text,
    ]);

    $url = 'https://translate.googleapis.com/translate_a/single?'.$query;

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 15,
            'header' => "User-Agent: Mozilla/5.0\r\n",
        ],
    ]);

    $raw = @file_get_contents($url, false, $context);
    if (!is_string($raw) || trim($raw) === '') {
        return null;
    }

    $json = json_decode($raw, true);
    if (!is_array($json) || !isset($json[0]) || !is_array($json[0])) {
        return null;
    }

    $parts = [];
    foreach ($json[0] as $segment) {
        if (is_array($segment) && isset($segment[0]) && is_string($segment[0])) {
            $parts[] = $segment[0];
        }
    }

    $result = trim(implode('', $parts));
    return $result !== '' ? $result : null;
}

function renderMarkdown(array $result): string
{
    $lines = [];
    $lines[] = '# Deep Translation Apply Report';
    $lines[] = '';
    $lines[] = '- strings_considered: '.(int) ($result['strings_considered'] ?? 0);
    $lines[] = '- added_ar: '.(int) ($result['added']['ar'] ?? 0);
    $lines[] = '- added_en: '.(int) ($result['added']['en'] ?? 0);
    $lines[] = '- added_default_en: '.(int) ($result['added']['default_en'] ?? 0);
    $lines[] = '';
    $lines[] = '## AR Sample';

    foreach ((array) ($result['added_samples']['ar'] ?? []) as $k => $v) {
        $lines[] = '- `'.$k.'` => `'.$v.'`';
    }

    return implode(PHP_EOL, $lines).PHP_EOL;
}
