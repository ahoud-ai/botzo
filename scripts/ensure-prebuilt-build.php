<?php

declare(strict_types=1);

/**
 * Ensure frontend build assets exist on environments without npm/node.
 *
 * Behavior:
 * - Reads committed prebuilt assets from public/prebuilt-build.
 * - Syncs them into public/build when one of the following is true:
 *   1) --force is provided
 *   2) public/build/manifest.json is missing
 *   3) public/build/manifest.json hash differs from prebuilt manifest hash
 */

$options = getopt('', [
    'force',
    'quiet',
    'format::',
    'build-dir::',
    'prebuilt-dir::',
]);

$force = array_key_exists('force', $options);
$quiet = array_key_exists('quiet', $options);
$format = strtolower((string) ($options['format'] ?? 'text'));

$root = dirname(__DIR__);
$buildDirInput = (string) ($options['build-dir'] ?? ($root . '/public/build'));
$prebuiltDirInput = (string) ($options['prebuilt-dir'] ?? ($root . '/public/prebuilt-build'));

$buildDir = str_replace('\\', '/', $buildDirInput);
$prebuiltDir = str_replace('\\', '/', $prebuiltDirInput);

$buildManifest = $buildDir . '/manifest.json';
$prebuiltManifest = $prebuiltDir . '/manifest.json';

$unlockPath = static function (string $path): void {
    clearstatcache(true, $path);
    @chmod($path, is_dir($path) ? 0775 : 0666);

    if (DIRECTORY_SEPARATOR !== '\\') {
        return;
    }

    $windowsPath = str_replace('/', '\\', $path);
    @exec('attrib -R -S -H ' . escapeshellarg($windowsPath));
};

$removeWithShell = static function (string $path, bool $isDirectory): bool {
    if (DIRECTORY_SEPARATOR !== '\\') {
        return false;
    }

    $windowsPath = str_replace('/', '\\', $path);
    $command = $isDirectory
        ? 'cmd /c rmdir /s /q ' . escapeshellarg($windowsPath)
        : 'cmd /c del /f /q ' . escapeshellarg($windowsPath);

    @exec($command, $output, $exitCode);
    clearstatcache(true, $path);

    if ($exitCode === 0 && ! file_exists($path) && ! is_dir($path)) {
        return true;
    }

    $psPath = str_replace("'", "''", $windowsPath);
    $psCommand = $isDirectory
        ? 'powershell -NoProfile -Command "if (Test-Path -LiteralPath ' . "'{$psPath}'" . ') { Remove-Item -LiteralPath ' . "'{$psPath}'" . ' -Recurse -Force }"'
        : 'powershell -NoProfile -Command "if (Test-Path -LiteralPath ' . "'{$psPath}'" . ') { Remove-Item -LiteralPath ' . "'{$psPath}'" . ' -Force }"';

    @exec($psCommand, $output, $exitCode);
    clearstatcache(true, $path);

    return $exitCode === 0 && ! file_exists($path) && ! is_dir($path);
};

/**
 * @return never
 */
$finish = static function (array $payload, int $exitCode) use ($format, $quiet): void {
    if (! $quiet) {
        if ($format === 'json') {
            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        } else {
            echo 'status: ' . ($payload['status'] ?? 'unknown') . PHP_EOL;
            echo 'reason: ' . ($payload['reason'] ?? 'n/a') . PHP_EOL;
            if (isset($payload['synced_files'])) {
                echo 'synced_files: ' . $payload['synced_files'] . PHP_EOL;
            }
            if (isset($payload['prebuilt_manifest_sha1'])) {
                echo 'prebuilt_manifest_sha1: ' . $payload['prebuilt_manifest_sha1'] . PHP_EOL;
            }
            if (isset($payload['build_manifest_sha1_before'])) {
                echo 'build_manifest_sha1_before: ' . $payload['build_manifest_sha1_before'] . PHP_EOL;
            }
        }
    }

    exit($exitCode);
};

/**
 * @return int
 */
$deleteDirectory = static function (string $dir) use ($unlockPath, $removeWithShell): int {
    if (! is_dir($dir)) {
        return 0;
    }

    if (DIRECTORY_SEPARATOR === '\\') {
        $windowsDir = str_replace('/', '\\', $dir);
        $psDir = str_replace("'", "''", $windowsDir);
        $command = 'powershell -NoProfile -Command "if (Test-Path -LiteralPath ' . "'{$psDir}'" . ') { Remove-Item -LiteralPath ' . "'{$psDir}'" . ' -Recurse -Force }"';
        @exec($command, $output, $exitCode);
        clearstatcache(true, $dir);

        if ($exitCode === 0 && ! is_dir($dir)) {
            return 0;
        }
    }

    $deleted = 0;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    /** @var SplFileInfo $item */
    foreach ($iterator as $item) {
        $path = $item->getPathname();
        if ($item->isDir()) {
            $unlockPath($path);
            if (! @rmdir($path)) {
                if (! $removeWithShell($path, true)) {
                    throw new RuntimeException("Unable to remove directory: {$path}");
                }
            }
            continue;
        }

        $unlockPath($path);
        if (! @unlink($path)) {
            if (! $removeWithShell($path, false)) {
                throw new RuntimeException("Unable to remove file: {$path}");
            }
        }
        $deleted++;
    }

    $unlockPath($dir);
    if (! @rmdir($dir)) {
        if (! $removeWithShell($dir, true)) {
            throw new RuntimeException("Unable to remove root directory: {$dir}");
        }
    }

    return $deleted;
};

/**
 * @return int
 */
$copyDirectory = static function (string $source, string $target): int {
    if (! is_dir($source)) {
        throw new RuntimeException("Source directory does not exist: {$source}");
    }

    if (! is_dir($target) && ! @mkdir($target, 0775, true) && ! is_dir($target)) {
        throw new RuntimeException("Unable to create target directory: {$target}");
    }

    $copied = 0;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS)
    );

    /** @var SplFileInfo $item */
    foreach ($iterator as $item) {
        $sourcePath = $item->getPathname();
        $relativePath = substr($sourcePath, strlen($source) + 1);
        $targetPath = $target . DIRECTORY_SEPARATOR . $relativePath;

        if ($item->isDir()) {
            if (! is_dir($targetPath) && ! @mkdir($targetPath, 0775, true) && ! is_dir($targetPath)) {
                throw new RuntimeException("Unable to create subdirectory: {$targetPath}");
            }
            continue;
        }

        $parent = dirname($targetPath);
        if (! is_dir($parent) && ! @mkdir($parent, 0775, true) && ! is_dir($parent)) {
            throw new RuntimeException("Unable to create parent directory: {$parent}");
        }

        if (! @copy($sourcePath, $targetPath)) {
            throw new RuntimeException("Unable to copy {$sourcePath} to {$targetPath}");
        }

        $copied++;
    }

    return $copied;
};

$payload = [
    'status' => 'skipped',
    'reason' => 'no_action_needed',
    'force' => $force,
    'build_dir' => $buildDir,
    'prebuilt_dir' => $prebuiltDir,
    'build_manifest_exists' => is_file($buildManifest),
    'prebuilt_manifest_exists' => is_file($prebuiltManifest),
];

if (! is_file($prebuiltManifest)) {
    $payload['reason'] = 'prebuilt_manifest_missing';
    $finish($payload, 0);
}

$prebuiltHash = sha1_file($prebuiltManifest) ?: null;
$buildHash = is_file($buildManifest) ? (sha1_file($buildManifest) ?: null) : null;

$payload['prebuilt_manifest_sha1'] = $prebuiltHash;
$payload['build_manifest_sha1_before'] = $buildHash;

$shouldSync = $force || ! is_file($buildManifest) || $prebuiltHash !== $buildHash;

if (! $shouldSync) {
    $payload['reason'] = 'already_in_sync';
    $finish($payload, 0);
}

try {
    $deletedFiles = $deleteDirectory($buildDir);
    $copiedFiles = $copyDirectory($prebuiltDir, $buildDir);
} catch (Throwable $e) {
    $payload['status'] = 'error';
    $payload['reason'] = $e->getMessage();
    $finish($payload, 1);
}

$payload['status'] = 'synced';
$payload['reason'] = $force ? 'forced_sync' : 'manifest_mismatch_or_missing';
$payload['deleted_files'] = $deletedFiles;
$payload['synced_files'] = $copiedFiles;
$payload['build_manifest_sha1_after'] = is_file($buildManifest) ? (sha1_file($buildManifest) ?: null) : null;

$finish($payload, 0);
