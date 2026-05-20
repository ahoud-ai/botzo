<?php

declare(strict_types=1);

$format = in_array('--format=json', $argv, true) ? 'json' : 'text';
$strict = in_array('--strict', $argv, true);

$root = dirname(__DIR__);
$manifestPath = $root.'/public/build/manifest.json';
$config = require $root.'/config/architecture.php';
$budgets = $config['budgets']['build_chunks'] ?? [];
$maxJsKilobytes = (float) ($config['budgets']['build_assets']['max_js_kb'] ?? 550);

if (! is_file($manifestPath)) {
    output([
        'status' => 'missing_manifest',
        'manifest' => $manifestPath,
    ], $format, $strict ? 1 : 0);
}

$manifest = json_decode((string) file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);
$chunks = [];
$violations = [];

foreach (javascript_assets_from_manifest($manifest) as $asset) {
    $path = $root.'/public/build/'.$asset;
    $sizeKilobytes = is_file($path) ? round(filesize($path) / 1024, 2) : null;

    if ($sizeKilobytes === null || $sizeKilobytes > $maxJsKilobytes) {
        $violations[] = [
            'chunk' => basename($asset),
            'file' => $asset,
            'reason' => 'max_js_asset_exceeded',
            'size_kb' => $sizeKilobytes,
            'max_kb' => $maxJsKilobytes,
        ];
    }
}

foreach ($budgets as $chunkName => $maxKilobytes) {
    $asset = find_chunk_asset($manifest, (string) $chunkName);

    if ($asset === null) {
        $violations[] = [
            'chunk' => $chunkName,
            'reason' => 'missing',
            'max_kb' => $maxKilobytes,
        ];

        continue;
    }

    $path = $root.'/public/build/'.$asset;
    $sizeKilobytes = is_file($path) ? round(filesize($path) / 1024, 2) : null;

    $chunks[] = [
        'chunk' => $chunkName,
        'file' => $asset,
        'size_kb' => $sizeKilobytes,
        'max_kb' => $maxKilobytes,
    ];

    if ($sizeKilobytes === null || $sizeKilobytes > (float) $maxKilobytes) {
        $violations[] = [
            'chunk' => $chunkName,
            'file' => $asset,
            'size_kb' => $sizeKilobytes,
            'max_kb' => $maxKilobytes,
        ];
    }
}

output([
    'status' => $violations === [] ? 'ok' : 'failed',
    'manifest' => $manifestPath,
    'max_js_kb' => $maxJsKilobytes,
    'chunks' => $chunks,
    'violations' => $violations,
], $format, $strict && $violations !== [] ? 1 : 0);

/**
 * @param  array<string, mixed>  $manifest
 */
function find_chunk_asset(array $manifest, string $chunkName): ?string
{
    foreach ($manifest as $entry) {
        if (! is_array($entry) || ! isset($entry['file']) || ! is_string($entry['file'])) {
            continue;
        }

        if (preg_match('/assets\/'.preg_quote($chunkName, '/').'-[^\/]+\.js$/', $entry['file']) === 1) {
            return $entry['file'];
        }
    }

    return null;
}

/**
 * @param  array<string, mixed>  $manifest
 * @return array<int, string>
 */
function javascript_assets_from_manifest(array $manifest): array
{
    $assets = [];

    foreach ($manifest as $entry) {
        if (! is_array($entry) || ! isset($entry['file']) || ! is_string($entry['file'])) {
            continue;
        }

        if (str_ends_with($entry['file'], '.js')) {
            $assets[] = $entry['file'];
        }
    }

    return array_values(array_unique($assets));
}

/**
 * @param  array<string, mixed>  $payload
 */
function output(array $payload, string $format, int $exitCode): never
{
    if ($format === 'json') {
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
    } else {
        echo $payload['status'].PHP_EOL;
    }

    exit($exitCode);
}
