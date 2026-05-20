<?php

declare(strict_types=1);

/**
 * Refresh committed prebuilt assets from the current local Vite build.
 *
 * Source: public/build
 * Target: public/prebuilt-build
 */

$options = getopt('', [
    'source-dir::',
    'target-dir::',
    'format::',
]);

$format = strtolower((string) ($options['format'] ?? 'text'));
$root = dirname(__DIR__);
$sourceDir = str_replace('\\', '/', (string) ($options['source-dir'] ?? ($root . '/public/build')));
$targetDir = str_replace('\\', '/', (string) ($options['target-dir'] ?? ($root . '/public/prebuilt-build')));

$sourceManifest = $sourceDir . '/manifest.json';
$targetManifest = $targetDir . '/manifest.json';

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
$finish = static function (array $payload, int $exitCode) use ($format): void {
    if ($format === 'json') {
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    } else {
        echo 'status: ' . ($payload['status'] ?? 'unknown') . PHP_EOL;
        echo 'source: ' . ($payload['source_dir'] ?? 'n/a') . PHP_EOL;
        echo 'target: ' . ($payload['target_dir'] ?? 'n/a') . PHP_EOL;
        echo 'reason: ' . ($payload['reason'] ?? 'n/a') . PHP_EOL;
        if (isset($payload['copied_files'])) {
            echo 'copied_files: ' . $payload['copied_files'] . PHP_EOL;
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
    'status' => 'error',
    'source_dir' => $sourceDir,
    'target_dir' => $targetDir,
    'reason' => 'unknown_error',
];

if (! is_file($sourceManifest)) {
    $payload['reason'] = "source_manifest_missing: {$sourceManifest}";
    $finish($payload, 1);
}

try {
    $deletedFiles = $deleteDirectory($targetDir);
    $copiedFiles = $copyDirectory($sourceDir, $targetDir);
} catch (Throwable $e) {
    $payload['reason'] = $e->getMessage();
    $finish($payload, 1);
}

$payload['status'] = 'ok';
$payload['reason'] = 'prebuilt_refreshed';
$payload['deleted_files'] = $deletedFiles;
$payload['copied_files'] = $copiedFiles;
$payload['source_manifest_sha1'] = sha1_file($sourceManifest) ?: null;
$payload['target_manifest_sha1'] = is_file($targetManifest) ? (sha1_file($targetManifest) ?: null) : null;

$finish($payload, 0);
