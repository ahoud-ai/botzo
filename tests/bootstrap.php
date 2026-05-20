<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

$projectRoot = dirname(__DIR__);
$cachedConfig = $projectRoot.'/bootstrap/cache/config.php';

if (is_file($cachedConfig)) {
    fwrite(
        STDERR,
        "Unsafe test bootstrap: bootstrap/cache/config.php is present.\n"
        ."Run `php artisan config:clear` before running tests.\n"
    );
    exit(1);
}

$testingEnvFile = $projectRoot.'/.env.testing';
if (is_file($testingEnvFile)) {
    $lines = @file($testingEnvFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            continue;
        }

        if (! str_starts_with($trimmed, 'DB_DATABASE=')) {
            continue;
        }

        $database = trim(substr($trimmed, strlen('DB_DATABASE=')), " \t\n\r\0\x0B\"'");
        if (strtolower($database) === 'app') {
            fwrite(
                STDERR,
                "Unsafe test bootstrap: .env.testing uses DB_DATABASE=app.\n"
                ."Use an isolated testing database (for example: app_testing).\n"
            );
            exit(1);
        }
    }
}
