<?php

declare(strict_types=1);

/**
 * Build artifact consistency utility.
 *
 * Default mode: check only (no file deletions).
 * Apply mode: remove orphaned files under public/build that are not referenced in manifest.json.
 *
 * Exit codes:
 * 0 => success (no orphans, or apply completed)
 * 1 => runtime error
 * 2 => orphan files detected in check mode
 */

$options = getopt('', ['apply', 'build-dir::', 'format::']);

$apply = array_key_exists('apply', $options);
$format = strtolower((string) ($options['format'] ?? 'text'));
$buildDirInput = (string) ($options['build-dir'] ?? (__DIR__ . '/../public/build'));

$buildDir = realpath($buildDirInput);
if ($buildDir === false || !is_dir($buildDir)) {
    fwrite(STDERR, "Build directory not found: {$buildDirInput}" . PHP_EOL);
    exit(1);
}

$manifestPath = $buildDir . DIRECTORY_SEPARATOR . 'manifest.json';
if (!is_file($manifestPath)) {
    fwrite(STDERR, "manifest.json not found at: {$manifestPath}" . PHP_EOL);
    exit(1);
}

$manifestRaw = file_get_contents($manifestPath);
if ($manifestRaw === false) {
    fwrite(STDERR, "Unable to read manifest: {$manifestPath}" . PHP_EOL);
    exit(1);
}

try {
    /** @var array<mixed> $manifest */
    $manifest = json_decode($manifestRaw, true, 512, JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    fwrite(STDERR, "Invalid manifest JSON: {$e->getMessage()}" . PHP_EOL);
    exit(1);
}

/**
 * @param mixed $value
 * @param array<string, true> $collector
 */
$collectAssetPaths = static function ($value, array &$collector) use (&$collectAssetPaths): void {
    if (is_string($value)) {
        $normalized = str_replace('\\', '/', $value);
        if (str_starts_with($normalized, 'assets/')) {
            $collector[$normalized] = true;
        }
        return;
    }

    if (is_array($value)) {
        foreach ($value as $item) {
            $collectAssetPaths($item, $collector);
        }
    }
};

$referenced = ['manifest.json' => true];
$collectAssetPaths($manifest, $referenced);

$allFiles = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($buildDir, FilesystemIterator::SKIP_DOTS)
);

/** @var SplFileInfo $fileInfo */
foreach ($iterator as $fileInfo) {
    if (!$fileInfo->isFile()) {
        continue;
    }

    $fullPath = $fileInfo->getRealPath();
    if ($fullPath === false) {
        continue;
    }

    $relative = str_replace('\\', '/', substr($fullPath, strlen($buildDir) + 1));
    $allFiles[] = $relative;
}

sort($allFiles);

$orphans = [];
foreach ($allFiles as $relative) {
    if (isset($referenced[$relative])) {
        continue;
    }

    // Keep safety tight: only prune generated assets, never top-level files other than manifest gate.
    if (str_starts_with($relative, 'assets/')) {
        $orphans[] = $relative;
    }
}

$removed = [];
$removeErrors = [];

if ($apply && count($orphans) > 0) {
    foreach ($orphans as $relative) {
        $target = $buildDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        if (!is_file($target)) {
            continue;
        }

        if (@unlink($target)) {
            $removed[] = $relative;
        } else {
            $removeErrors[] = $relative;
        }
    }
}

$payload = [
    'build_dir' => str_replace('\\', '/', $buildDir),
    'manifest' => 'manifest.json',
    'mode' => $apply ? 'apply' : 'check',
    'referenced_assets' => max(0, count($referenced) - 1),
    'files_total' => count($allFiles),
    'orphans_count' => count($orphans),
    'orphans' => $orphans,
    'removed_count' => count($removed),
    'removed' => $removed,
    'remove_errors_count' => count($removeErrors),
    'remove_errors' => $removeErrors,
];

if ($format === 'json') {
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} else {
    echo "Build dir: {$payload['build_dir']}" . PHP_EOL;
    echo "Mode: {$payload['mode']}" . PHP_EOL;
    echo "Referenced assets: {$payload['referenced_assets']}" . PHP_EOL;
    echo "Total files: {$payload['files_total']}" . PHP_EOL;
    echo "Orphan assets: {$payload['orphans_count']}" . PHP_EOL;

    if ($payload['orphans_count'] > 0) {
        foreach ($orphans as $orphan) {
            echo " - {$orphan}" . PHP_EOL;
        }
    }

    if ($apply) {
        echo "Removed: {$payload['removed_count']}" . PHP_EOL;
        if ($payload['remove_errors_count'] > 0) {
            echo "Remove errors: {$payload['remove_errors_count']}" . PHP_EOL;
            foreach ($removeErrors as $errorPath) {
                echo " ! {$errorPath}" . PHP_EOL;
            }
        }
    }
}

if ($apply && count($removeErrors) > 0) {
    exit(1);
}

if (!$apply && count($orphans) > 0) {
    exit(2);
}

exit(0);
