<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$trackedFiles = [];

$descriptorSpec = [
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];

$process = proc_open('git ls-files', $descriptorSpec, $pipes, $root);
if (! is_resource($process)) {
    fwrite(STDERR, "Unable to inspect tracked files via git.\n");
    exit(1);
}

$trackedOutput = stream_get_contents($pipes[1]);
$trackedError = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);

$exitCode = proc_close($process);
if ($exitCode !== 0) {
    fwrite(STDERR, "git ls-files failed.\n".$trackedError);
    exit(1);
}

$trackedFiles = array_values(array_filter(array_map('trim', explode("\n", $trackedOutput))));
$errors = [];

foreach ($trackedFiles as $trackedFile) {
    if (str_starts_with($trackedFile, 'public/build/')) {
        $errors[] = "Tracked build artifact detected: {$trackedFile}";
    }

    if (str_ends_with($trackedFile, '.DS_Store')) {
        $errors[] = "Tracked OS artifact detected: {$trackedFile}";
    }
}

$scanRoots = [
    $root.'/app',
    $root.'/routes',
    $root.'/resources/js',
    $root.'/tests',
    $root.'/config',
    $root.'/database',
];

$scanExtensions = ['php', 'js', 'vue', 'ts'];
$forbiddenMarkers = '/\b(TODO|FIXME|HACK)\b/';

foreach ($scanRoots as $scanRoot) {
    if (! is_dir($scanRoot)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($scanRoot, FilesystemIterator::SKIP_DOTS)
    );

    /** @var SplFileInfo $file */
    foreach ($iterator as $file) {
        if (! $file->isFile()) {
            continue;
        }

        $extension = strtolower($file->getExtension());
        if (! in_array($extension, $scanExtensions, true)) {
            continue;
        }

        $relativePath = ltrim(str_replace($root, '', $file->getPathname()), DIRECTORY_SEPARATOR);
        $contents = file_get_contents($file->getPathname());
        if ($contents === false) {
            $errors[] = "Unable to read {$relativePath}";

            continue;
        }

        $lines = preg_split('/\R/', $contents) ?: [];
        foreach ($lines as $index => $line) {
            if (preg_match($forbiddenMarkers, $line) === 1) {
                $errors[] = "{$relativePath}:".($index + 1).': forbidden marker found';
            }
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, "Repository hygiene check failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, " - {$error}\n");
    }

    exit(1);
}

fwrite(STDOUT, "Repository hygiene check passed.\n");
