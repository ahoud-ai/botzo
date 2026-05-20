<?php

declare(strict_types=1);

$options = getopt('', [
    'format::',
    'strict',
    'build-manifest::',
    'prebuilt-manifest::',
]);

$root = dirname(__DIR__);
$format = strtolower((string) ($options['format'] ?? 'text'));
$strict = array_key_exists('strict', $options);

$resolvePath = static function (string $path) use ($root): string {
    if ($path === '') {
        return $path;
    }

    if (
        str_starts_with($path, DIRECTORY_SEPARATOR)
        || preg_match('/^[a-zA-Z]:[\\\\\\/]/', $path) === 1
    ) {
        return $path;
    }

    return $root.DIRECTORY_SEPARATOR.$path;
};

$buildManifest = $resolvePath((string) ($options['build-manifest'] ?? 'public/build/manifest.json'));
$prebuiltManifest = $resolvePath((string) ($options['prebuilt-manifest'] ?? 'public/prebuilt-build/manifest.json'));

$payload = [
    'status' => 'unknown',
    'strict' => $strict,
    'build_manifest' => $buildManifest,
    'prebuilt_manifest' => $prebuiltManifest,
    'reason' => null,
];

$finish = static function (array $payload, int $exitCode) use ($format): void {
    if ($format === 'json') {
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
    } else {
        echo 'status: '.($payload['status'] ?? 'unknown').PHP_EOL;
        echo 'reason: '.($payload['reason'] ?? 'n/a').PHP_EOL;
        echo 'build_manifest: '.($payload['build_manifest'] ?? 'n/a').PHP_EOL;
        echo 'prebuilt_manifest: '.($payload['prebuilt_manifest'] ?? 'n/a').PHP_EOL;
        if (isset($payload['build_sha1'])) {
            echo 'build_sha1: '.$payload['build_sha1'].PHP_EOL;
        }
        if (isset($payload['prebuilt_sha1'])) {
            echo 'prebuilt_sha1: '.$payload['prebuilt_sha1'].PHP_EOL;
        }
        if (! empty($payload['entry_diffs']) && is_array($payload['entry_diffs'])) {
            echo 'entry_diffs: '.count($payload['entry_diffs']).PHP_EOL;
        }
    }

    exit($exitCode);
};

if (! is_file($buildManifest)) {
    $payload['status'] = 'error';
    $payload['reason'] = 'build_manifest_missing';
    $finish($payload, $strict ? 1 : 0);
}

if (! is_file($prebuiltManifest)) {
    $payload['status'] = 'error';
    $payload['reason'] = 'prebuilt_manifest_missing';
    $finish($payload, $strict ? 1 : 0);
}

$buildRaw = file_get_contents($buildManifest);
$prebuiltRaw = file_get_contents($prebuiltManifest);

if ($buildRaw === false || $prebuiltRaw === false) {
    $payload['status'] = 'error';
    $payload['reason'] = 'manifest_read_failed';
    $finish($payload, 1);
}

$buildJson = json_decode($buildRaw, true);
$prebuiltJson = json_decode($prebuiltRaw, true);

if (! is_array($buildJson) || ! is_array($prebuiltJson)) {
    $payload['status'] = 'error';
    $payload['reason'] = 'manifest_json_invalid';
    $finish($payload, 1);
}

$buildSha1 = sha1_file($buildManifest) ?: null;
$prebuiltSha1 = sha1_file($prebuiltManifest) ?: null;

$payload['build_sha1'] = $buildSha1;
$payload['prebuilt_sha1'] = $prebuiltSha1;

$inspectKeys = [
    'resources/js/app.js',
    'resources/js/Pages/User/Automation/Flows/Builder.vue',
    'resources/js/Pages/User/Automation/Flows/Index.vue',
];

$entryDiffs = [];
foreach ($inspectKeys as $key) {
    $buildFile = (string) ($buildJson[$key]['file'] ?? '');
    $prebuiltFile = (string) ($prebuiltJson[$key]['file'] ?? '');

    if ($buildFile === '' && $prebuiltFile === '') {
        continue;
    }

    if ($buildFile !== $prebuiltFile) {
        $entryDiffs[] = [
            'key' => $key,
            'build_file' => $buildFile,
            'prebuilt_file' => $prebuiltFile,
        ];
    }
}

$payload['entry_diffs'] = $entryDiffs;

if ($buildSha1 === $prebuiltSha1) {
    $payload['status'] = 'in_sync';
    $payload['reason'] = 'manifest_hash_match';
    $finish($payload, 0);
}

$payload['status'] = 'drifted';
$payload['reason'] = 'manifest_hash_mismatch';
$finish($payload, $strict ? 1 : 0);
